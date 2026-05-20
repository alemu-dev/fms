<?php
// ============================================================
//  files_list.php  –  Browse / Search all files
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireLogin();
 
$pdo = getDB();
 
// Filters
$search   = trim($_GET['search']  ?? '');
$status   = $_GET['status']       ?? '';
$priority = $_GET['priority']     ?? '';
$deptId   = (int)($_GET['dept']   ?? 0);
$catId    = (int)($_GET['cat']    ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;
 
$where  = ['1=1'];
$params = [];
 
if ($search !== '') {
    $where[]  = '(f.title LIKE ? OR f.file_number LIKE ? OR f.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status !== '')   { $where[] = 'f.status = ?';        $params[] = $status; }
if ($priority !== '') { $where[] = 'f.priority = ?';      $params[] = $priority; }
if ($deptId > 0)      { $where[] = 'f.department_id = ?'; $params[] = $deptId; }
if ($catId > 0)       { $where[] = 'f.category_id = ?';   $params[] = $catId; }
 
$whereStr = implode(' AND ', $where);
 
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM files f WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
 
$stmt = $pdo->prepare("
    SELECT f.*, u.full_name AS creator, d.name AS dept_name, c.name AS cat_name,
           a.full_name AS assignee
    FROM files f
    LEFT JOIN users u ON f.created_by = u.id
    LEFT JOIN users a ON f.assigned_to = a.id
    LEFT JOIN departments d ON f.department_id = d.id
    LEFT JOIN categories c ON f.category_id = c.id
    WHERE $whereStr
    ORDER BY f.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$files = $stmt->fetchAll();
 
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$categories  = $pdo->query("SELECT * FROM categories  ORDER BY name")->fetchAll();
 
include 'includes/header.php';
?>
 
<div class="page-header">
    <h1>File Registry</h1>
    <a href="files_add.php" class="btn btn-primary">+ New File</a>
</div>
 
<!-- Filter Bar -->
<form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search file no., title…" value="<?= htmlspecialchars($search) ?>">
    <select name="status">
        <option value="">All Status</option>
        <?php foreach (['open','in_review','approved','rejected','archived','closed'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="priority">
        <option value="">All Priority</option>
        <?php foreach (['low','normal','high','urgent'] as $p): ?>
        <option value="<?= $p ?>" <?= $priority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="dept">
        <option value="0">All Departments</option>
        <?php foreach ($departments as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $deptId === $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="cat">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $catId === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="files_list.php" class="btn btn-outline">Reset</a>
</form>
 
<div class="table-meta">Showing <?= count($files) ?> of <?= $total ?> files</div>
 
<div class="table-wrapper">
<table class="data-table">
    <thead>
        <tr>
            <th>File No.</th>
            <th>Title</th>
            <th>Category</th>
            <th>Department</th>
            <th>Assigned To</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Due Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($files)): ?>
        <tr><td colspan="9" class="empty-row">No files found.</td></tr>
    <?php else: ?>
    <?php foreach ($files as $f): ?>
        <tr>
            <td><code><?= htmlspecialchars($f['file_number']) ?></code></td>
            <td class="file-title"><?= htmlspecialchars($f['title']) ?></td>
            <td><?= htmlspecialchars($f['cat_name']  ?? '—') ?></td>
            <td><?= htmlspecialchars($f['dept_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($f['assignee']  ?? '—') ?></td>
            <td><span class="badge badge-<?= $f['status'] ?>"><?= strtoupper(str_replace('_',' ',$f['status'])) ?></span></td>
            <td><span class="badge badge-priority-<?= $f['priority'] ?>"><?= strtoupper($f['priority']) ?></span></td>
            <td><?= $f['due_date'] ? date('d M Y', strtotime($f['due_date'])) : '—' ?></td>
            <td class="actions">
                <a href="files_view.php?id=<?= $f['id'] ?>" class="btn btn-sm">View</a>
                <?php if (in_array($user['role'], ['admin','manager']) || $f['created_by'] == $user['id'] || $f['assigned_to'] == $user['id']): ?>
                <a href="files_edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin'): ?>
                <a href="files_delete.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete this file?')">Del</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>
 
<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&priority=<?= $priority ?>&dept=<?= $deptId ?>&cat=<?= $catId ?>"
           class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
 
<?php include 'includes/footer.php'; ?>