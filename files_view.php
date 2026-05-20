<?php
// ============================================================
//  files_view.php  –  View file details, history & comments
// ============================================================
// Adjusted paths dynamically to directory root folder configs
require_once 'config/db.php';
require_once 'config/auth.php';
requireLogin();
 
$pdo  = getDB();
$user = currentUser();
$id   = (int)($_GET['id'] ?? 0);
$msg  = $_GET['msg'] ?? '';
 
$stmt = $pdo->prepare("
    SELECT f.*, ub.full_name AS creator, d.name AS dept_name, c.name AS cat_name,
           ua.full_name AS assignee
    FROM files f
    LEFT JOIN users ub ON f.created_by = ub.id
    LEFT JOIN users ua ON f.assigned_to = ua.id
    LEFT JOIN departments d ON f.department_id = d.id
    LEFT JOIN categories  c ON f.category_id   = c.id
    WHERE f.id = ?
");
$stmt->execute([$id]);
$file = $stmt->fetch();
 
if (!$file) {
    header('Location: files_list.php');
    exit;
}
 
// Add comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment'] ?? '');
    if ($comment !== '') {
        $pdo->prepare("INSERT INTO file_comments (file_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$id, $user['id'], $comment]);
        logAudit('ADD_COMMENT', 'files', $id, 'Comment added');
        header("Location: files_view.php?id=$id");
        exit;
    }
}
 
// Movement history
$movements = $pdo->prepare("
    SELECT fm.*, fu.full_name AS from_user, tu.full_name AS to_user,
           fd.name AS from_dept, td.name AS to_dept
    FROM file_movements fm
    LEFT JOIN users fu ON fm.from_user_id = fu.id
    LEFT JOIN users tu ON fm.to_user_id   = tu.id
    LEFT JOIN departments fd ON fm.from_dept_id = fd.id
    LEFT JOIN departments td ON fm.to_dept_id   = td.id
    WHERE fm.file_id = ?
    ORDER BY fm.moved_at ASC
");
$movements->execute([$id]);
$movements = $movements->fetchAll();
 
// Comments
$comments = $pdo->prepare("
    SELECT fc.*, u.full_name
    FROM file_comments fc
    LEFT JOIN users u ON fc.user_id = u.id
    WHERE fc.file_id = ?
    ORDER BY fc.created_at ASC
");
$comments->execute([$id]);
$comments = $comments->fetchAll();
 
include 'includes/header.php';
?>
 
<div class="page-header">
    <h1>File Details</h1>
    <div>
        <?php if (in_array($user['role'], ['admin','manager']) || $file['created_by'] == $user['id'] || $file['assigned_to'] == $user['id']): ?>
        <a href="files_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
        <?php if (in_array($user['role'], ['admin','manager'])): ?>
        <a href="file_transfer.php?id=<?= $id ?>" class="btn btn-outline">⇄ Transfer</a>
        <?php endif; ?>
        <?php endif; ?>
        <a href="files_list.php" class="btn btn-outline">← Back</a>
    </div>
</div>
 
<?php if ($msg === 'created'): ?>
    <div class="alert alert-success">File registered successfully.</div>
<?php elseif ($msg === 'updated'): ?>
    <div class="alert alert-success">File updated successfully.</div>
<?php endif; ?>
<?php if (($_GET['err'] ?? '') === 'forbidden'): ?>
    <div class="alert alert-danger">You do not have permission to edit this file.</div>
<?php endif; ?>
 
<div class="detail-grid">
    <div class="detail-main">
        <div class="detail-card">
            <div class="detail-header">
                <div>
                    <code class="file-number"><?= htmlspecialchars($file['file_number']) ?></code>
                    <?php if ($file['confidential']): ?><span class="badge badge-confidential">CONFIDENTIAL</span><?php endif; ?>
                </div>
                <div>
                    <span class="badge badge-<?= $file['status'] ?>"><?= strtoupper(str_replace('_',' ',$file['status'])) ?></span>
                    <span class="badge badge-priority-<?= $file['priority'] ?>"><?= strtoupper($file['priority']) ?></span>
                </div>
            </div>
            <h2 class="file-title-large"><?= htmlspecialchars($file['title']) ?></h2>
            
            <!-- FIXED DESCRIPTION DISPLAY WINDOW FOR TINYMCE HTML RENDERING -->
            <?php if ($file['description']): ?>
                <div class="file-desc">
                    <?= strip_tags($file['description'], '<p><br><strong><em><span><ul><ol><li><table><tbody><tr><td><th><colgroup><col>') ?>
                </div>
            <?php endif; ?>
 
            <div class="meta-grid">
                <div class="meta-item"><span>Category</span><strong><?= htmlspecialchars($file['cat_name'] ?? '—') ?></strong></div>
                <div class="meta-item"><span>Department</span><strong><?= htmlspecialchars($file['dept_name'] ?? '—') ?></strong></div>
                <div class="meta-item"><span>Created By</span><strong><?= htmlspecialchars($file['creator'] ?? '—') ?></strong></div>
                <div class="meta-item"><span>Assigned To</span><strong><?= htmlspecialchars($file['assignee'] ?? 'Unassigned') ?></strong></div>
                <div class="meta-item"><span>Created</span><strong><?= date('d M Y H:i', strtotime($file['created_at'])) ?></strong></div>
                <div class="meta-item"><span>Due Date</span><strong><?= $file['due_date'] ? date('d M Y', strtotime($file['due_date'])) : '—' ?></strong></div>
            </div>
 
            <?php if ($file['file_path']): ?>
            <div class="attachment-box">
                <span>📎 Attachment:</span>
                <a href="<?= htmlspecialchars($file['file_path']) ?>" target="_blank" class="attachment-link">
                    <?= htmlspecialchars($file['original_name']) ?>
                    <small>(<?= round($file['file_size'] / 1024, 1) ?> KB)</small>
                </a>
            </div>
            <?php endif; ?>
        </div>
 
        <!-- Comments -->
        <div class="detail-card">
            <h3>Comments & Notes</h3>
            <?php if (empty($comments)): ?>
                <p class="empty-note">No comments yet.</p>
            <?php else: ?>
            <div class="comments-list">
                <?php foreach ($comments as $c): ?>
                <div class="comment-item">
                    <div class="comment-meta">
                        <strong><?= htmlspecialchars($c['full_name'] ?? 'Unknown') ?></strong>
                        <span><?= date('d M Y H:i', strtotime($c['created_at'])) ?></span>
                    </div>
                    <p><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
 
            <form method="POST" class="comment-form">
                <textarea name="comment" placeholder="Add a comment or note…" rows="3" required></textarea>
                <button type="submit" name="add_comment" class="btn btn-primary">Post Comment</button>
            </form>
        </div>
    </div>
 
    <!-- Movement Timeline -->
    <div class="detail-sidebar">
        <div class="detail-card">
            <h3>Movement History</h3>
            <div class="timeline">
            <?php foreach ($movements as $m): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <strong><?= htmlspecialchars($m['action']) ?></strong>
                        <div class="timeline-meta">
                            <?php if ($m['to_user']): ?>To: <?= htmlspecialchars($m['to_user']) ?><br><?php endif; ?>
                            <?php if ($m['to_dept']): ?><?= htmlspecialchars($m['to_dept']) ?><br><?php endif; ?>
                            <?php if ($m['remarks']): ?><em><?= htmlspecialchars($m['remarks']) ?></em><?php endif; ?>
                        </div>
                        <time><?= date('d M Y H:i', strtotime($m['moved_at'])) ?></time>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
 
<?php include 'includes/footer.php'; ?>