<?php
// ============================================================
//  logout.php
// ============================================================
require_once 'config/auth.php';
require_once 'config/db.php';

if (isLoggedIn()) {
    logAudit('LOGOUT', 'users', currentUser()['id'], 'User logged out');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
