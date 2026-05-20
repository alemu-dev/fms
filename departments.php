<?php
// ============================================================
//  departments.php  –  Manage departments (admin only)
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireRole('admin');

$pdo     = getDB();
$errors  = [];
$success = '';
$editRow = null;

// ── Delete ─────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    // Check if any files or users belong to this dept
    $usedFiles = $pdo->prepare("SELECT COUNT(*) FROM files WHERE department_id = ?"); $usedFiles->execute([$did]); $usedFiles = $usedFiles->fetchColumn();
    $usedUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?"); $usedUsers->execute([$did]); $usedUsers = $usedUsers->fetchColumn();
    if ($usedFiles > 0 || $usedUsers > 0) {
        $errors[] = "Cannot delete: this department has $usedFiles file(s) and $usedUsers user(s) linked to it.";
    } else {
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$did]);
        logAudit('DELETE_DEPARTMENT', 'departments', $did, 'Department deleted');
        $success = 'Department deleted.';
    }
}

// ── Load for edit ──────────────────────────────────────────
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $editRow = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $editRow->execute([$eid]);
    $editRow = $editRow->fetch();
}

// ── Create / Update ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']      ?? '');
    $code      = strtoupper(trim($_POST['code']      ?? ''));
    $head_name = trim($_POST['head_name'] ?? '');
    $editId    = (int)($_POST['edit_id']  ?? 0);

    if ($name === '') $errors[] = 'Department name is required.';
    if ($code === '') $errors[] = 'Department code is required.';

    if (empty($errors)) {
        // Check code uniqueness
        $check = $pdo->prepare("SELECT id FROM departments WHERE code = ? AND id != ?");
        $check->execute([$code, $editId]);
        if ($check->fetch()) {
            $errors[] = "Code \"$code\" is already used by another department.";
        } else {
            if ($editId > 0) {
                $pdo->prepare("UPDATE departments SET name=?, code=?, head_name=? WHERE id=?")
                    ->execute([$name, $code, $head_name ?: null, $editId]);
                logAudit('UPDATE_DEPARTMENT', 'departments', $editId, "Updated: $name");
                $success = "Department \"$name\" updated.";
            } else {
                $pdo->prepare("INSERT INTO departments (name, code, head_name) VALUES (?,?,?)")
                    ->execute([$name, $code, $head_name ?: null]);
                $newId = (int)$pdo->lastInsertId();
                logAudit('CREATE_DEPARTMENT', 'departments', $newId, "Created: $name");
                $success = "Department \"$name\" created.";
            }
            $editRow = null;
        }
    }
}

// ── List ───────────────────────────────────────────────────
$departments = $pdo->query("
    SELECT d.*,
           COUNT(DISTINCT f.id) AS file_count,
           COUNT(DISTINCT u.id) AS user_count
    FROM departments d
    LEFT JOIN files f ON f.department_id = d.id
    LEFT JOIN users u ON u.department_id = d.id AND u.is_active = 1
    GROUP BY d.id
    ORDER BY d.name
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Departments</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="detail-grid">
    <!-- Department List -->
    <div class="detail-main">
        <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Head</th>
                    <th>Files</th>
                    <th>Active Users</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($departments)): ?>
                <tr><td colspan="6" class="empty-row">No departments found.</td></tr>
            <?php else: ?>
            <?php foreach ($departments as $d): ?>
            <tr>
                <td><code><?= htmlspecialchars($d['code']) ?></code></td>
                <td style="font-weight:600;"><?= htmlspecialchars($d['name']) ?></td>
                <td><?= htmlspecialchars($d['head_name'] ?? '—') ?></td>
                <td><span style="font-family:var(--font-mono);color:var(--accent);"><?= $d['file_count'] ?></span></td>
                <td><span style="font-family:var(--font-mono);color:var(--green);"><?= $d['user_count'] ?></span></td>
                <td class="actions">
                    <a href="?edit=<?= $d['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                    <a href="?delete=<?= $d['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete department \'<?= htmlspecialchars(addslashes($d['name'])) ?>\'? This cannot be undone.')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Add / Edit Form -->
    <div class="detail-sidebar">
        <div class="detail-card">
            <h3><?= $editRow ? 'Edit Department' : 'Add New Department' ?></h3>
            <form method="POST">
                <?php if ($editRow): ?>
                    <input type="hidden" name="edit_id" value="<?= $editRow['id'] ?>">
                <?php endif; ?>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Department Name <span class="req">*</span></label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? ($_POST['name'] ?? '')) ?>" required placeholder="e.g. Finance & Accounts">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Code <span class="req">*</span></label>
                    <input type="text" name="code" value="<?= htmlspecialchars($editRow['code'] ?? ($_POST['code'] ?? '')) ?>" required placeholder="e.g. FIN" maxlength="20" style="text-transform:uppercase;">
                    <small style="color:var(--text-muted);">Short unique identifier (auto-uppercased).</small>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Head / Director</label>
                    <input type="text" name="head_name" value="<?= htmlspecialchars($editRow['head_name'] ?? ($_POST['head_name'] ?? '')) ?>" placeholder="e.g. Chief Finance Officer">
                </div>
                <div class="form-actions" style="margin-top:16px;padding-top:14px;">
                    <button type="submit" class="btn btn-primary"><?= $editRow ? 'Save Changes' : 'Create Department' ?></button>
                    <?php if ($editRow): ?>
                        <a href="departments.php" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
