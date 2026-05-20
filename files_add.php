<?php
// ============================================================
//  files_add.php  –  Create a new file record
// ============================================================
// Note: Changed paths to '../' assuming file is inside /files/ 
// If it gives a 404/Error, change them back to 'config/db.php'
require_once 'config/db.php';
require_once 'config/auth.php';
requireLogin();

$pdo         = getDB();
$user        = currentUser();
$errors      = [];
$success     = '';

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$categories  = $pdo->query("SELECT * FROM categories  ORDER BY name")->fetchAll();
$staffList   = $pdo->query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']        ?? '');
    $description  = trim($_POST['description']  ?? ''); // Captures TinyMCE HTML output
    $category_id  = (int)($_POST['category_id']  ?? 0);
    $dept_id      = (int)($_POST['department_id'] ?? 0);
    $assigned_to  = (int)($_POST['assigned_to']   ?? 0);
    $priority     = $_POST['priority']   ?? 'normal';
    $status       = $_POST['status']     ?? 'open';
    $due_date     = $_POST['due_date']   ?? null;
    $confidential = isset($_POST['confidential']) ? 1 : 0;

    // Generate unique file number: GOV-YEAR-RANDOM
    $file_number = 'GOV-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

    if ($title === '') $errors[] = 'Title is required.';

    // File upload
    $filePath     = null;
    $originalName = null;
    $fileSize     = 0;
    $mimeType     = null;

    if (!empty($_FILES['attachment']['name'])) {
        $allowed = ['pdf','doc','docx','xls','xlsx','txt','jpg','jpeg','png'];
        $ext     = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = 'File type not allowed. Allowed: ' . implode(', ', $allowed);
        } elseif ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
            $errors[] = 'File size must not exceed 10 MB.';
        } else {
            $uploadDir = __DIR__ . '/uploads/files/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newName  = uniqid('file_') . '.' . $ext;
            $filePath = 'uploads/files/' . $newName;
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $newName)) {
                $errors[] = 'File upload failed.';
                $filePath = null;
            } else {
                $originalName = $_FILES['attachment']['name'];
                $fileSize     = $_FILES['attachment']['size'];
                $mimeType     = $_FILES['attachment']['type'];
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO files
              (file_number, title, description, category_id, department_id, created_by,
               assigned_to, status, priority, file_path, file_size, original_name,
               mime_type, confidential, due_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $file_number, $title, $description,
            $category_id ?: null, $dept_id ?: null, $user['id'],
            $assigned_to ?: null, $status, $priority,
            $filePath, $fileSize, $originalName, $mimeType,
            $confidential, $due_date ?: null
        ]);
        $newId = $pdo->lastInsertId();
        logAudit('CREATE_FILE', 'files', $newId, "Created file: $file_number");

        // Log movement
        $pdo->prepare("
            INSERT INTO file_movements (file_id, from_user_id, to_user_id, to_dept_id, action, remarks)
            VALUES (?, NULL, ?, ?, 'File Created', 'New file registered')
        ")->execute([$newId, $user['id'], $dept_id ?: null]);

        header("Location: files_view.php?id=$newId&msg=created");
        exit;
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Register New File</h1>
    <a href="files_list.php" class="btn btn-outline">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="form-card">
<form method="POST" enctype="multipart/form-data">
    <div class="form-grid-2">
        <div class="form-group full-width">
            <label>Title <span class="req">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
        </div>
        
        <!-- TINYMCE APPLIED HERE -->
        <div class="form-group full-width">
            <label>Description</label>
            <!-- Added class="wysiwyg" below to trigger TinyMCE -->
            <textarea name="description" class="wysiwyg" rows="5"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Category</label>
            <select name="category_id">
                <option value="">— Select —</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($_POST['category_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Department</label>
            <select name="department_id">
                <option value="">— Select —</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= (($_POST['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Assign To</label>
            <select name="assigned_to">
                <option value="">— Unassigned —</option>
                <?php foreach ($staffList as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Priority</label>
            <select name="priority">
                <?php foreach (['low','normal','high','urgent'] as $p): ?>
                <option value="<?= $p ?>" <?= (($_POST['priority'] ?? 'normal') === $p) ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <?php foreach (['open','in_review','approved','rejected','archived','closed'] as $s): ?>
                <option value="<?= $s ?>" <?= (($_POST['status'] ?? 'open') === $s) ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Due Date</label>
            <input type="date" name="due_date" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Attach File <small>(max 10 MB)</small></label>
            <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png">
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="confidential" value="1" <?= !empty($_POST['confidential']) ? 'checked' : '' ?>>
                Mark as Confidential
            </label>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Register File</button>
        <a href="files_list.php" class="btn btn-outline">Cancel</a>
    </div>
</form>
</div>

<?php include 'includes/footer.php'; ?>