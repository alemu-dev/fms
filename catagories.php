<?php
// ============================================================
//  categories.php  –  Manage file categories (admin only)
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
    $cid = (int)$_GET['delete'];
    $usedFiles = $pdo->prepare("SELECT COUNT(*) FROM files WHERE category_id = ?"); $usedFiles->execute([$cid]); $usedFiles = $usedFiles->fetchColumn();
    if ($usedFiles > 0) {
        $errors[] = "Cannot delete: $usedFiles file(s) are linked to this category.";
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$cid]);
        logAudit('DELETE_CATEGORY', 'categories', $cid, 'Category deleted');
        $success = 'Category deleted.';
    }
}

// ── Load for edit ──────────────────────────────────────────
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $editRow = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $editRow->execute([$eid]);
    $editRow = $editRow->fetch();
}

// ── Create / Update ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $editId      = (int)($_POST['edit_id']    ?? 0);

    if ($name === '') $errors[] = 'Category name is required.';

    if (empty($errors)) {
        // Check name uniqueness
        $check = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $check->execute([$name, $editId]);
        if ($check->fetch()) {
            $errors[] = "Category \"$name\" already exists.";
        } else {
            if ($editId > 0) {
                $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?")
                    ->execute([$name, $description ?: null, $editId]);
                logAudit('UPDATE_CATEGORY', 'categories', $editId, "Updated: $name");
                $success = "Category \"$name\" updated.";
            } else {
                $pdo->prepare("INSERT INTO categories (name, description) VALUES (?,?)")
                    ->execute([$name, $description ?: null]);
                $newId = (int)$pdo->lastInsertId();
                logAudit('CREATE_CATEGORY', 'categories', $newId, "Created: $name");
                $success = "Category \"$name\" created.";
            }
            $editRow = null;
        }
    }
}

// ── List ───────────────────────────────────────────────────
$categories = $pdo->query("
    SELECT c.*, COUNT(f.id) AS file_count
    FROM categories c
    LEFT JOIN files f ON f.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1>File Categories</h1>
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
    <!-- Category List -->
    <div class="detail-main">
        <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Files</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($categories)): ?>
                <tr><td colspan="6" class="empty-row">No categories found.</td></tr>
            <?php else: ?>
            <?php foreach ($categories as $c): ?>
            <tr>
                <td style="color:var(--text-muted);font-family:var(--font-mono);"><?= $c['id'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></td>
                <td style="color:var(--text-secondary);font-size:0.82rem;">
                    <?= htmlspecialchars(mb_strimwidth($c['description'] ?? '—', 0, 60, '…')) ?>
                </td>
                <td><span style="font-family:var(--font-mono);color:var(--accent);"><?= $c['file_count'] ?></span></td>
                <td><small><?= date('d M Y', strtotime($c['created_at'])) ?></small></td>
                <td class="actions">
                    <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                    <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete category \'<?= htmlspecialchars(addslashes($c['name'])) ?>\'?')">Delete</a>
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
            <h3><?= $editRow ? 'Edit Category' : 'Add New Category' ?></h3>
            <form method="POST">
                <?php if ($editRow): ?>
                    <input type="hidden" name="edit_id" value="<?= $editRow['id'] ?>">
                <?php endif; ?>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Category Name <span class="req">*</span></label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? ($_POST['name'] ?? '')) ?>" required placeholder="e.g. Contracts">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Description</label>
                    <textarea name="description" rows="4" placeholder="Brief description of this category…"><?= htmlspecialchars($editRow['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                </div>
                <div class="form-actions" style="margin-top:16px;padding-top:14px;">
                    <button type="submit" class="btn btn-primary"><?= $editRow ? 'Save Changes' : 'Create Category' ?></button>
                    <?php if ($editRow): ?>
                        <a href="categories.php" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="detail-card">
            <h3>Quick Stats</h3>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:0.82rem;color:var(--text-secondary);">Total Categories</span>
                    <span style="font-family:var(--font-mono);font-weight:700;color:var(--accent);"><?= count($categories) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:0.82rem;color:var(--text-secondary);">Categories With Files</span>
                    <span style="font-family:var(--font-mono);font-weight:700;color:var(--green);"><?= count(array_filter($categories, fn($c) => $c['file_count'] > 0)) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;">
                    <span style="font-size:0.82rem;color:var(--text-secondary);">Empty Categories</span>
                    <span style="font-family:var(--font-mono);font-weight:700;color:var(--text-muted);"><?= count(array_filter($categories, fn($c) => $c['file_count'] == 0)) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
