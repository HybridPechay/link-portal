<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
enforce_idle_timeout();
$me = current_user();
$pdo = get_pdo();

$error = '';
$success = '';

function is_strong_password(string $pw): bool
{
    return strlen($pw) >= 10;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $isAdmin = isset($_POST['is_admin']) ? 1 : 0;

            if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
                $error = 'Username must be 3-50 characters: letters, numbers, dot, dash, underscore only.';
            } elseif (!is_strong_password($password)) {
                $error = 'Password must be at least 10 characters.';
            } else {
                $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
                $check->execute([$username]);
                if ((int) $check->fetchColumn() > 0) {
                    $error = 'That username is already taken.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, is_admin, is_active) VALUES (?, ?, ?, 1)');
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $isAdmin]);
                    $success = 'User "' . $username . '" created.';
                }
            }
        } elseif ($action === 'reset_password') {
            $id = (int) ($_POST['user_id'] ?? 0);
            $password = $_POST['password'] ?? '';
            if (!is_strong_password($password)) {
                $error = 'Password must be at least 10 characters.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                $success = 'Password updated.';
            }
        } elseif ($action === 'toggle_active') {
            $id = (int) ($_POST['user_id'] ?? 0);
            if ($id === (int) $me['id']) {
                $error = 'You cannot deactivate your own account.';
            } else {
                $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
                $success = 'User updated.';
            }
        } elseif ($action === 'toggle_admin') {
            $id = (int) ($_POST['user_id'] ?? 0);
            if ($id === (int) $me['id']) {
                $error = 'You cannot change your own admin status.';
            } else {
                $pdo->prepare('UPDATE users SET is_admin = NOT is_admin WHERE id = ?')->execute([$id]);
                $success = 'User updated.';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['user_id'] ?? 0);
            if ($id === (int) $me['id']) {
                $error = 'You cannot delete your own account.';
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                $success = 'User deleted.';
            }
        }
    } catch (PDOException $e) {
        error_log('users.php error: ' . $e->getMessage());
        $error = 'Something went wrong. Please try again.';
    }
}

$users = $pdo->query('SELECT id, username, is_admin, is_active, created_at FROM users ORDER BY username')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users &mdash; <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand"><span class="brand-mark" aria-hidden="true"></span><span class="brand-name"><?= e(APP_NAME) ?> <span class="badge">Admin</span></span></div>
        <div class="topbar-right">
            <a class="btn btn-ghost" href="/index.php">View portal</a>
            <a class="btn btn-ghost" href="/logout.php">Sign out</a>
        </div>
    </div>
</header>
<main class="page">
    <a class="back-link" href="/admin/index.php">&larr; Admin home</a>
    <h1>Users</h1>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="panel">
        <h2>Add a user</h2>
        <form method="post" class="inline-form" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="50" autocomplete="off">
            </div>
            <div>
                <label for="password">Temporary password</label>
                <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password">
            </div>
            <div style="flex: 0 0 auto;">
                <label style="margin-top: 1.9em;"><input type="checkbox" name="is_admin" style="width:auto;"> Admin</label>
            </div>
            <div style="flex: 0 0 auto;">
                <button type="submit" class="btn btn-primary" style="margin-top: 0.35em;">Add user</button>
            </div>
        </form>
    </div>

    <table class="data-table">
        <thead>
        <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['username']) ?><?= ((int) $u['id'] === (int) $me['id']) ? ' <span class="badge">You</span>' : '' ?></td>
                <td><?= $u['is_admin'] ? 'Admin' : 'Standard' ?></td>
                <td><?= $u['is_active'] ? 'Active' : 'Disabled' ?></td>
                <td class="muted"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-ghost btn-sm" href="/admin/permissions.php?user_id=<?= (int) $u['id'] ?>">Permissions</a>

                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_admin">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" <?= (int) $u['id'] === (int) $me['id'] ? 'disabled' : '' ?>>
                                <?= $u['is_admin'] ? 'Remove admin' : 'Make admin' ?>
                            </button>
                        </form>

                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" <?= (int) $u['id'] === (int) $me['id'] ? 'disabled' : '' ?>>
                                <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>

                        <details style="display:inline-block;">
                            <summary class="btn btn-ghost btn-sm" style="display:inline-flex; cursor:pointer; list-style:none;">Reset password</summary>
                            <form method="post" style="margin-top:0.5em; display:flex; gap:0.4em;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <input type="password" name="password" placeholder="New password" required minlength="10" style="width:auto;">
                                <button type="submit" class="btn btn-primary btn-sm">Set</button>
                            </form>
                        </details>

                        <form method="post" data-confirm="Delete user &quot;<?= e($u['username']) ?>&quot;? This cannot be undone." style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" <?= (int) $u['id'] === (int) $me['id'] ? 'disabled' : '' ?>>Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<script src="/assets/js/app.js"></script>
</body>
</html>
