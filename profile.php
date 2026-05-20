<?php
// ============================================================
//  profile.php  –  View & edit own profile, change password
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireLogin();

$pdo     = getDB();
$user    = currentUser();
$errors  = [];
$success = '';

$stmt = $pdo->prepare("SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// ── Update profile ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');

    if ($full_name === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (empty($errors)) {
        // Check email not taken by another user
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $user['id']]);
        if ($check->fetch()) {
            $errors[] = 'That email is already in use by another account.';
        } else {
            $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?")
                ->execute([$full_name, $email, $user['id']]);
            // Update session name
            $_SESSION['user_name'] = $full_name;
            logAudit('UPDATE_PROFILE', 'users', $user['id'], 'Profile updated');
            $success = 'Profile updated successfully.';
            $profile['full_name'] = $full_name;
            $profile['email']     = $email;
        }
    }
}

// ── Change password ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (!password_verify($current, $profile['password_hash']))
        $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 8)
        $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $confirm)
        $errors[] = 'New passwords do not match.';

    if (empty($errors)) {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([$hash, $user['id']]);
        logAudit('CHANGE_PASSWORD', 'users', $user['id'], 'Password changed');
        $success = 'Password changed successfully.';
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>My Profile</h1>
    <a href="dashboard.php" class="btn btn-outline">← Dashboard</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="detail-grid">
    <!-- Profile Info -->
    <div class="detail-main">
        <div class="detail-card">
            <h3>Account Information</h3>
            <form method="POST">
                <div class="form-grid-2">
                    <div class="form-group full-width">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($profile['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?= htmlspecialchars($profile['username']) ?>" disabled style="opacity:0.5;cursor:not-allowed;">
                        <small style="color:var(--text-muted);">Username cannot be changed.</small>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?= strtoupper($profile['role']) ?>" disabled style="opacity:0.5;cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="<?= htmlspecialchars($profile['dept_name'] ?? 'Not Assigned') ?>" disabled style="opacity:0.5;cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?= date('d M Y', strtotime($profile['created_at'])) ?>" disabled style="opacity:0.5;cursor:not-allowed;">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="detail-card">
            <h3>Change Password</h3>
            <form method="POST">
                <div class="form-grid-2">
                    <div class="form-group full-width">
                        <label>Current Password <span class="req">*</span></label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password <span class="req">*</span></label>
                        <input type="password" name="new_password" required minlength="8">
                        <small style="color:var(--text-muted);">Minimum 8 characters.</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password <span class="req">*</span></label>
                        <input type="password" name="confirm_password" required minlength="8">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar: avatar + stats -->
    <div class="detail-sidebar">
        <div class="detail-card" style="text-align:center;">
            <div style="width:72px;height:72px;border-radius:50%;background:var(--accent-dim);color:var(--accent);font-size:2rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <?= strtoupper(substr($profile['full_name'], 0, 1)) ?>
            </div>
            <div style="font-size:1rem;font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($profile['full_name']) ?></div>
            <div style="margin-top:6px;"><span class="badge badge-role-<?= $profile['role'] ?>"><?= strtoupper($profile['role']) ?></span></div>
            <div style="margin-top:8px;font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($profile['dept_name'] ?? 'No Department') ?></div>
        </div>

        <div class="detail-card">
            <h3>My Activity</h3>
            <?php
            $myCreated  = $pdo->prepare("SELECT COUNT(*) FROM files WHERE created_by = ?"); $myCreated->execute([$user['id']]); $myCreated = $myCreated->fetchColumn();
            $myAssigned = $pdo->prepare("SELECT COUNT(*) FROM files WHERE assigned_to = ? AND status NOT IN ('closed','archived')"); $myAssigned->execute([$user['id']]); $myAssigned = $myAssigned->fetchColumn();
            $myComments = $pdo->prepare("SELECT COUNT(*) FROM file_comments WHERE user_id = ?"); $myComments->execute([$user['id']]); $myComments = $myComments->fetchColumn();
            $myLogins   = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id = ? AND action = 'LOGIN'"); $myLogins->execute([$user['id']]); $myLogins = $myLogins->fetchColumn();
            ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ([
                    ['Files Created',  $myCreated,  'var(--accent)'],
                    ['Files Assigned', $myAssigned, 'var(--green)'],
                    ['Comments Posted',$myComments, 'var(--yellow)'],
                    ['Total Logins',   $myLogins,   'var(--text-secondary)'],
                ] as [$label, $val, $color]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:0.82rem;color:var(--text-secondary);"><?= $label ?></span>
                    <span style="font-family:var(--font-mono);font-weight:700;color:<?= $color ?>;"><?= number_format($val) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
