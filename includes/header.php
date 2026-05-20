<?php
// ============================================================
//  includes/header.php
// ============================================================
if (!isset($user)) $user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>STICA-FMS</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/files/style.css">

<!-- ============================================================ -->
<!-- TinyMCE Rich Text Editor Integration                         -->
<!-- ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: 'textarea.wysiwyg', // Targets textareas with class="wysiwyg"
    menubar: false,               // Keeps the layout clean and modern
    plugins: 'lists link image code table wordcount',
    toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table link image | code',
    height: 300,
    branding: false               // Removes the TinyMCE watermark logo
  });
</script>
<!-- ============================================================ -->

</head>
<body>

<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-hex">⬡</span>
            <div>
                <div class="brand-name">STICA<span>FMS</span></div>
                <div class="brand-sub">File Management</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="dashboard.php"  class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php'  ? 'active' : '' ?>">
                <span class="nav-icon">⬜</span> Dashboard
            </a>
            <a href="files_list.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'files_list.php' ? 'active' : '' ?>">
                <span class="nav-icon">📁</span> File Registry
            </a>
            <a href="files_add.php"  class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'files_add.php'  ? 'active' : '' ?>">
                <span class="nav-icon">➕</span> Register File
            </a>

            <?php if (in_array($user['role'], ['admin','manager'])): ?>
            <div class="nav-section-label">Administration</div>
            <a href="users.php"     class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'users.php'     ? 'active' : '' ?>">
                <span class="nav-icon">👥</span> Users
            </a>
            <a href="audit_log.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'audit_log.php' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span> Audit Log
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <!-- Fallback safety check in case username string properties are empty -->
                <div class="user-avatar"><?= !empty($user['name']) ? strtoupper(substr($user['name'], 0, 1)) : 'U' ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                    <div class="user-role"><?= strtoupper($user['role'] ?? 'STAFF') ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn-logout" title="Sign out">⏻</a>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">