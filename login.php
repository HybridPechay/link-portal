<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    header('Location: /index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $result = attempt_login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        header('Location: /index.php');
        exit;
    }
    $error = $result['message'];
}
$timedOut = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in &mdash; <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<?= favicon_tags() ?>
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="auth-logo"><?= brand_logo(true) ?></div>
    <h1><?= e(APP_NAME) ?></h1>
    <p class="muted">Sign in to see your links</p>

    <?php if ($timedOut): ?>
        <div class="alert alert-error">You were signed out after being idle. Please sign in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit" class="btn btn-primary">Sign in</button>
    </form>
</main>
</body>
</html>
