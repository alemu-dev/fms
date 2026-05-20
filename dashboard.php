<?php
// ============================================================
//  dashboard.php
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireLogin();

$pdo  = getDB();
$user = currentUser();

// Stats
$totalFiles   = $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();
$openFiles    = $pdo->query("SELECT COUNT(*) FROM files WHERE status='open'")->fetchColumn();
$urgentFiles  = $pdo->query("SELECT COUNT(*) FROM files WHERE priority='urgent' AND status NOT IN ('archived','closed')")->fetchColumn();
$myFiles      = $pdo->prepare("SELECT COUNT(*) FROM files WHERE assigned_to = ?");
$myFiles->execute([$user['id']]);
$myFiles = $myFiles->fetchColumn();

// Recent files
$recent = $pdo->query("
    SELECT f.*, u.full_name AS creator, d.name AS dept_name, c.name AS cat_name
    FROM files f
    LEFT JOIN users u ON f.created_by = u.id
    LEFT JOIN departments d ON f.department_id = d.id
    LEFT JOIN categories c ON f.category_id = c.id
    ORDER BY f.created_at DESC LIMIT 8
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <a href="files_add.php" class="btn btn-primary">+ New File</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📁</div>
        <div class="stat-value"><?= number_format($totalFiles) ?></div>
        <div class="stat-label">Total Files</div>
    </div>
    <div class="stat-card accent-green">
        <div class="stat-icon">📂</div>
        <div class="stat-value"><?= number_format($openFiles) ?></div>
        <div class="stat-label">Open Files</div>
    </div>
    <div class="stat-card accent-red">
        <div class="stat-icon">🚨</div>
        <div class="stat-value"><?= number_format($urgentFiles) ?></div>
        <div class="stat-label">Urgent</div>
    </div>
    <div class="stat-card accent-blue">
        <div class="stat-icon">👤</div>
        <div class="stat-value"><?= number_format($myFiles) ?></div>
        <div class="stat-label">Assigned to Me</div>
    </div>
</div>

<div class="section-title">Recent Files</div>
<div class="table-wrapper">
<table class="data-table">
    <thead>
        <tr>
            <th>File No.</th>
            <th>Title</th>
            <th>Category</th>
            <th>Department</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Created</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($recent as $f): ?>
        <tr>
            <td><code><?= htmlspecialchars($f['file_number']) ?></code></td>
            <td><?= htmlspecialchars($f['title']) ?></td>
            <td><?= htmlspecialchars($f['cat_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($f['dept_name'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $f['status'] ?>"><?= strtoupper($f['status']) ?></span></td>
            <td><span class="badge badge-priority-<?= $f['priority'] ?>"><?= strtoupper($f['priority']) ?></span></td>
            <td><?= date('d M Y', strtotime($f['created_at'])) ?></td>
            <td>
                <a href="files_view.php?id=<?= $f['id'] ?>" class="btn btn-sm">View</a>
                <a href="files_edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
