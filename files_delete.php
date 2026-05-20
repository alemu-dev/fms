<?php
// ============================================================
//  files_delete.php  –  Delete a file (admin/manager only)
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireRole('admin');
 
$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
 
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch();
 
if ($file) {
    // Remove physical file
    if ($file['file_path'] && file_exists(__DIR__ . '/' . $file['file_path'])) {
        unlink(__DIR__ . '/' . $file['file_path']);
    }
    $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$id]);
    logAudit('DELETE_FILE', 'files', $id, "Deleted file: {$file['file_number']}");
}
 
header('Location: files_list.php?msg=deleted');
exit;