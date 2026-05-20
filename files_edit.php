<?php
// ============================================================
//  files_edit.php  –  Edit an existing file record
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireLogin();
 
$pdo  = getDB();
$user = currentUser();
$id   = (int)($_GET['id'] ?? 0);
 
$file = $pdo->prepare("SELECT * FROM files WHERE id = ?");
$file->execute([$id]);
$file = $file->fetch();
 
if (!$file) {
    header('Location: files_list.php');
    exit;
}
 
// Permission: staff can only edit files they created or are assigned to
$isAdminOrManager = in_array($user['role'], ['admin', 'manager']);
$isOwner = ($file['created_by'] == $user['id'] || $file['assigned_to'] == $user['id']);
if (!$isAdminOrManager && !$isOwner) {
    header('Location: files_view.php?id=' . $id . '&err=forbidden');
    exit;
}
 
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$categories  = $pdo->query("SELECT * FROM categories  ORDER BY name")->fetchAll();
$staffList   = $pdo->query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
$errors      = [];
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']        ?? '');
    $description = trim($_POST['description']  ?? ''); // Captures edited TinyMCE HTML data
    $category_id = (int)($_POST['category_id']  ?? 0);
    $dept_id     = (int)($_POST['department_id'] ?? 0);
    $assigned_to = (int)($_POST['assigned_to']   ?? 0);
    $priority    = $_POST['priority'] ?? 'normal';
    $status      = $_POST['status']   ?? 'open';
    // Staff cannot set approved/rejected — enforce server-side
    if (!$isAdminOrManager && in_array($status, ['approved','rejected','closed'])) {
        $status = $file['status']; // revert to original
    }
    $due_date     = $_POST['due_date'] ?? null;
    $confidential = isset($_POST['confidential']) ? 1 : 0;
 
    if ($title === '') $errors[] = 'Title is required.';
 
    $filePath = $file['file_path'];
    $originalName = $file['original_name'];
    $fileSize = $file['file_size'];
    $mimeType = $file['mime_type'];
 
    if (!empty($_FILES['attachment']['name'])) {
        $allowed = ['pdf','doc','docx','xls','xlsx','txt','jpg','jpeg','png'];
        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = 'File type not allowed.';
        } elseif ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
            $errors[] = 'File too large (max 10 MB).';
        } else {
            $uploadDir = __DIR__ . '/uploads/files/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newName  = uniqid('file_') . '.' . $ext;
            $newPath  = 'uploads/files/' . $newName;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $newName)) {
                // Remove old file
                if ($file['file_path'] && file_exists(__DIR__ . '/' . $file['file_path'])) {
                    unlink(__DIR__ . '/' . $file['file_path']);
                }
                $filePath     = $newPath;
                $originalName = $_FILES['attachment']['name'];
                $fileSize     = $_FILES['attachment']['size'];
                $mimeType     = $_FILES['attachment']['type'];
            } else {
                $errors[] = 'File upload failed.';
            }
        }
    }
 
    if (empty($errors)) {
        $oldStatus = $file['status'];
        $pdo->prepare("
            UPDATE files SET title=?, description=?, category_id=?, department_id=?,
            assigned_to=?, status=?, priority=?, due_date=?, confidential=?,
            file_path=?, file_size=?, original_name=?, mime_type=?
            WHERE id=?
        ")->execute([
            $title, $description,
            $category_id ?: null, $dept_id ?: null,
            $assigned_to ?: null, $status, $priority,
            $due_date ?: null, $confidential,
            $filePath, $fileSize, $originalName, $mimeType,
            $id
        ]);
        logAudit('UPDATE_FILE', 'files', $id, "Updated file {$file['file_number']}");
 
        if ($oldStatus !== $status) {
            $pdo->prepare("
                INSERT INTO file_movements (file_id, from_user_id, action, remarks)
                VALUES (?, ?, 'Status Changed', ?)
            ")->execute([$id, $user['id'], "Status: $oldStatus → $status"]);
        }
 
        header("Location: files_view.php?id=$id&msg=updated");
        exit;
    }
    // Merge POST back into $file for re-display
    $file = array_merge($file, $_POST);
}
 
include 'includes/header.php';
?>
 
<div class="page-header">
    <h1>Edit File</h1>
    <div>
        <a href="files_view.php?id=<?= $id ?>" class="btn btn-outline">← Back</a>
    </div>
</div>
 
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
 
<div class="form-card">
<div class="form-info-bar">
    File No: <code><?= htmlspecialchars($file['file_number']) ?></code>
</div>
<form method="POST" enctype="multipart/form-data">
    <div class="form-grid-2">
        <div class="form-group full-width">
            <label>Title <span class="req">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($file['title']) ?>" required>
        </div>
        
        <!-- TINYMCE RICH TEXT INTEGRATED HERE -->
        <div class="form-group full-width">
            <label>Description</label>
            <!-- Added class="wysiwyg" below to initialize TinyMCE layout wrapper -->
            <textarea name="description" class="wysiwyg" rows="5"><?= htmlspecialchars($file['description'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Category</label>
            <select name="category_id">
                <option value="">— Select —</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($file['category_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Department</label>
            <select name="department_id">
                <option value="">— Select —</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($file['department_id'] == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Assign To</label>
            <select name="assigned_to">
                <option value="">— Unassigned —</option>
                <?php foreach ($staffList as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($file['assigned_to'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Priority</label>
            <select name="priority">
                <?php foreach (['low','normal','high','urgent'] as $p): ?>
                <option value="<?= $p ?>" <?= ($file['priority'] === $p) ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <?php
                $allStatuses = ['open','in_review','approved','rejected','archived','closed'];
                $staffStatuses = ['open','in_review','archived']; // staff cannot approve/reject
                $allowedStatuses = $isAdminOrManager ? $allStatuses : $staffStatuses;
                foreach ($allowedStatuses as $s): ?>
                <option value="<?= $s ?>" <?= ($file['status'] === $s) ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$isAdminOrManager): ?>
            <small style="color:var(--text-muted)">Approve/Reject requires Manager or Admin role.</small>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Due Date</label>
            <input type="date" name="due_date" value="<?= htmlspecialchars($file['due_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Replace Attachment <small>(optional)</small></label>
            <?php if ($file['file_path']): ?>
                <p class="current-file">Current: <?= htmlspecialchars($file['original_name']) ?></p>
            <?php endif; ?>
            <input type="file" name="attachment">
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="confidential" value="1" <?= $file['confidential'] ? 'checked' : '' ?>>
                Mark as Confidential
            </label>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="files_view.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
    </div>
</form>
</div>
 
<?php include 'includes/footer.php'; ?>