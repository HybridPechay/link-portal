<?php
/**
 * One-time setup. Creates the first admin account. Refuses to run again
 * once any admin exists — delete this file after use if you want to be
 * extra tidy, but it's safe to leave since it self-locks.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_pdo();

// Make sure the schema exists (in case someone forgot to import schema.sql).
try {
    $pdo->query('SELECT 1 FROM users LIMIT 1');
} catch (PDOException $e) {
    $schemaPath = __DIR__ . '/schema.sql';
    if (is_readable($schemaPath)) {
        $pdo->exec(file_get_contents($schemaPath));
    } else {
        die('Database tables are missing and schema.sql could not be read. Please import schema.sql manually.');
    }
}

$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();

$error = '';
$done = false;

if ($adminCount > 0) {
    http_response_code(403);
    die('Setup already completed — an admin account exists. Log in at <a href="/login.php">/login.php</a>. If you need a new admin, create one from the Users screen while logged in as an existing admin.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 10) {
        $error = 'Password must be at least 10 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, is_admin, is_active) VALUES (?, ?, 1, 1)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup &mdash; <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<?= favicon_tags() ?>
</head>
<body class="auth-page">
<main class="auth-card">
    <h1><?= e(APP_NAME) ?></h1>
    <p class="muted">First-time setup</p>

    <?php if ($done): ?>
        <div class="alert alert-success">
            Admin account created. <a href="/login.php">Log in now &rarr;</a>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <label for="username">Admin username</label>
            <input type="text" id="username" name="username" required minlength="3" maxlength="50" autocomplete="username">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password">

            <label for="confirm">Confirm password</label>
            <input type="password" id="confirm" name="confirm" required minlength="10" autocomplete="new-password">

            <button type="submit" class="btn btn-primary">Create admin account</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
