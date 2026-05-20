<?php
// ============================================================
//  config/auth.php  –  Session & Authentication Helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        header('Location: /dashboard.php?err=forbidden');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']   ?? 0,
        'name'     => $_SESSION['user_name'] ?? '',
        'role'     => $_SESSION['user_role'] ?? '',
        'dept_id'  => $_SESSION['dept_id']   ?? null,
    ];
}

function logAudit(string $action, string $table = '', int $recordId = 0, string $details = ''): void {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        currentUser()['id'],
        $action,
        $table,
        $recordId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
}
