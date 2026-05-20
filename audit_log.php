<?php
// ============================================================
//  audit_log.php  –  View system audit trail
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireRole('admin', 'manager');

$pdo  = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$total = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$logs = $pdo->query("
    SELECT al.*, u.full_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Audit Log</h1>
</div>

<div class="table-wrapper">
<table class="data-table">
    <thead>
        <tr><th>#</th><th>User</th><th>Action</th><th>Table</th><th>Record ID</th><th>Details</th><th>IP</th><th>Time</th></tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
    <tr>
        <td><?= $log['id'] ?></td>
        <td><?= htmlspecialchars($log['full_name'] ?? 'System') ?></td>
        <td><code><?= htmlspecialchars($log['action']) ?></code></td>
        <td><?= htmlspecialchars($log['table_name'] ?? '') ?></td>
        <td><?= $log['record_id'] ?: '—' ?></td>
        <td class="log-details"><?= htmlspecialchars($log['details'] ?? '') ?></td>
        <td><small><?= htmlspecialchars($log['ip_address'] ?? '') ?></small></td>
        <td><small><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></small></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
