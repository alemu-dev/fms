<?php
// ============================================================
//  users.php  –  Manage system users (admin only)
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireRole('admin');

$pdo    = getDB();
$errors = [];
$success = '';


// Delete
if (isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid !== currentUser()['id']) {
        $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uid]);
        logAudit('DEACTIVATE_USER', 'users', $uid, 'User deactivated');
        $success = 'User deactivated.';
    }
}

// Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']  ?? '');
    $username   = trim($_POST['username']   ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? '';
    $role       = $_POST['role']            ?? 'staff';
    $dept_id    = (int)($_POST['department_id'] ?? 0);

    if ($full_name === '' || $username === '' || $email === '' || $password === '')
        $errors[] = 'All fields are required.';
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $pdo->prepare("
                INSERT INTO users (department_id, full_name, username, email, password_hash, role)
                VALUES (?,?,?,?,?,?)
            ")->execute([$dept_id ?: null, $full_name, $username, $email, $hash, $role]);
            logAudit('CREATE_USER', 'users', (int)$pdo->lastInsertId(), "Created user: $username");
            $success = "User '$username' created successfully.";
        } catch (PDOException $e) {
            $errors[] = 'Username or email already exists.';
        }
    }
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$users = $pdo->query("
    SELECT u.*, d.name AS dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY u.created_at DESC
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1>User Management</h1>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>• " . htmlspecialchars($e) . "</div>"; ?></div>
<?php endif; ?>

<div class="detail-grid">
    <!-- User List -->
    <div class="detail-main">
        <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr><th>Name</th><th>Username</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                <td><span class="badge badge-role-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span></td>
                <td><?= htmlspecialchars($u['dept_name'] ?? '—') ?></td>
                <td><?= $u['is_active'] ? '<span class="badge badge-open">ACTIVE</span>' : '<span class="badge badge-closed">INACTIVE</span>' ?></td>
                <td>
                    <?php if ($u['id'] !== currentUser()['id'] && $u['is_active']): ?>
                    <a href="users.php?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Deactivate this user?')">Deactivate</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Add User Form -->
    <div class="detail-sidebar">
        <div class="detail-card">
            <h3>Add New User</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id">
                        <option value="">— None —</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Create User</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
