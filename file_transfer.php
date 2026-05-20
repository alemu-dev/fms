<?php
// ============================================================
//  file_transfer.php  –  Transfer a file to another dept/user
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireRole('admin', 'manager');

$pdo    = getDB();
$user   = currentUser();
$errors = [];
$success = '';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT f.*, d.name AS dept_name, u.full_name AS assignee_name
    FROM files f
    LEFT JOIN departments d ON f.department_id = d.id
    LEFT JOIN users u ON f.assigned_to = u.id
    WHERE f.id = ?
");
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    header('Location: files_list.php');
    exit;
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$staffList   = $pdo->query("SELECT id, full_name, role FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();

// Transfer history for this file
$history = $pdo->prepare("
    SELECT fm.*, fu.full_name AS from_user, tu.full_name AS to_user,
           fd.name AS from_dept, td.name AS to_dept
    FROM file_movements fm
    LEFT JOIN users fu ON fm.from_user_id = fu.id
    LEFT JOIN users tu ON fm.to_user_id   = tu.id
    LEFT JOIN departments fd ON fm.from_dept_id = fd.id
    LEFT JOIN departments td ON fm.to_dept_id   = td.id
    WHERE fm.file_id = ?
    ORDER BY fm.moved_at DESC
");
$history->execute([$id]);
$history = $history->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_dept_id  = (int)($_POST['to_dept_id']  ?? 0);
    $to_user_id  = (int)($_POST['to_user_id']  ?? 0);
    $action      = trim($_POST['action']        ?? '');
    $remarks     = trim($_POST['remarks']       ?? '');

    if ($action === '')    $errors[] = 'Action / movement type is required.';
    if (!$to_dept_id && !$to_user_id) $errors[] = 'Select a destination department or user.';

    if (empty($errors)) {
        // Log movement
        $pdo->prepare("
            INSERT INTO file_movements
              (file_id, from_user_id, to_user_id, from_dept_id, to_dept_id, action, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $id,
            $user['id'],
            $to_user_id  ?: null,
            $file['department_id'],
            $to_dept_id  ?: null,
            $action,
            $remarks,
        ]);

        // Update file's department & assigned user
        $pdo->prepare("
            UPDATE files SET
                department_id = COALESCE(?, department_id),
                assigned_to   = COALESCE(?, assigned_to)
            WHERE id = ?
        ")->execute([$to_dept_id ?: null, $to_user_id ?: null, $id]);

        logAudit('TRANSFER_FILE', 'files', $id, "Transferred file {$file['file_number']}: $action");

        $success = 'File transferred successfully.';

        // Refresh file data
        $stmt->execute([$id]);
        $file = $stmt->fetch();

        // Refresh history
        $history = $pdo->prepare("
            SELECT fm.*, fu.full_name AS from_user, tu.full_name AS to_user,
                   fd.name AS from_dept, td.name AS to_dept
            FROM file_movements fm
            LEFT JOIN users fu ON fm.from_user_id = fu.id
            LEFT JOIN users tu ON fm.to_user_id   = tu.id
            LEFT JOIN departments fd ON fm.from_dept_id = fd.id
            LEFT JOIN departments td ON fm.to_dept_id   = td.id
            WHERE fm.file_id = ?
            ORDER BY fm.moved_at DESC
        ");
        $history->execute([$id]);
        $history = $history->fetchAll();
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Transfer File</h1>
    <div>
        <a href="files_view.php?id=<?= $id ?>" class="btn btn-outline">← Back to File</a>
    </div>
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
    <div class="detail-main">

        <!-- Current file info -->
        <div class="detail-card">
            <h3>File Being Transferred</h3>
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <code class="file-number"><?= htmlspecialchars($file['file_number']) ?></code>
                    <div style="font-size:1.05rem;font-weight:700;margin-top:6px;"><?= htmlspecialchars($file['title']) ?></div>
                    <div style="font-size:0.82rem;color:var(--text-secondary);margin-top:4px;">
                        Currently in: <strong style="color:var(--text-primary);"><?= htmlspecialchars($file['dept_name'] ?? 'No Department') ?></strong>
                        &nbsp;·&nbsp; Assigned to: <strong style="color:var(--text-primary);"><?= htmlspecialchars($file['assignee_name'] ?? 'Unassigned') ?></strong>
                    </div>
                </div>
                <div>
                    <span class="badge badge-<?= $file['status'] ?>"><?= strtoupper(str_replace('_',' ',$file['status'])) ?></span>
                    <span class="badge badge-priority-<?= $file['priority'] ?>"><?= strtoupper($file['priority']) ?></span>
                </div>
            </div>
        </div>

        <!-- Transfer Form -->
        <div class="detail-card">
            <h3>Transfer Details</h3>
            <form method="POST">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Action / Movement Type <span class="req">*</span></label>
                        <select name="action">
                            <option value="">— Select Action —</option>
                            <?php foreach ([
                                'Forwarded',
                                'Returned',
                                'Escalated',
                                'Reassigned',
                                'Sent for Approval',
                                'Sent for Review',
                                'Dispatched',
                                'Recalled',
                            ] as $a): ?>
                            <option value="<?= $a ?>" <?= (($_POST['action'] ?? '') === $a) ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Transfer To Department</label>
                        <select name="to_dept_id">
                            <option value="">— Keep Current —</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"
                                <?= ($d['id'] == $file['department_id']) ? 'disabled style="opacity:.4"' : '' ?>
                                <?= (($_POST['to_dept_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?>
                                <?= ($d['id'] == $file['department_id']) ? ' (current)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Reassign To User</label>
                        <select name="to_user_id">
                            <option value="">— Keep Current —</option>
                            <?php foreach ($staffList as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                <?= ($s['id'] == $file['assigned_to']) ? 'disabled style="opacity:.4"' : '' ?>
                                <?= (($_POST['to_user_id'] ?? '') == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['full_name']) ?> (<?= strtoupper($s['role']) ?>)
                                <?= ($s['id'] == $file['assigned_to']) ? ' (current)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Remarks / Reason</label>
                        <textarea name="remarks" rows="3" placeholder="Add transfer reason or instructions…"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Transfer File</button>
                    <a href="files_view.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Movement History -->
    <div class="detail-sidebar">
        <div class="detail-card">
            <h3>Movement History (<?= count($history) ?>)</h3>
            <?php if (empty($history)): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;">No movements recorded yet.</p>
            <?php else: ?>
            <div class="timeline">
            <?php foreach ($history as $m): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <strong><?= htmlspecialchars($m['action']) ?></strong>
                        <div class="timeline-meta">
                            <?php if ($m['from_user']): ?>
                                <span style="color:var(--text-muted);">From:</span> <?= htmlspecialchars($m['from_user']) ?><br>
                            <?php endif; ?>
                            <?php if ($m['to_user']): ?>
                                <span style="color:var(--text-muted);">To:</span> <?= htmlspecialchars($m['to_user']) ?><br>
                            <?php endif; ?>
                            <?php if ($m['from_dept']): ?>
                                <span style="color:var(--text-muted);">Dept:</span> <?= htmlspecialchars($m['from_dept']) ?>
                                <?php if ($m['to_dept']): ?> → <?= htmlspecialchars($m['to_dept']) ?><?php endif; ?><br>
                            <?php endif; ?>
                            <?php if ($m['remarks']): ?>
                                <em style="color:var(--text-muted);"><?= htmlspecialchars($m['remarks']) ?></em>
                            <?php endif; ?>
                        </div>
                        <time><?= date('d M Y H:i', strtotime($m['moved_at'])) ?></time>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
