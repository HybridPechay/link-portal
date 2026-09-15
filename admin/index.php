<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
enforce_idle_timeout();
$user = current_user();

$pdo = get_pdo();
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$folderCount = (int) $pdo->query('SELECT COUNT(*) FROM folders')->fetchColumn();
$linkCount = (int) $pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin &mdash; <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true"></span>
            <span class="brand-name"><?= e(APP_NAME) ?> <span class="badge">Admin</span></span>
        </div>
        <div class="topbar-right">
            <span class="who">Signed in as <strong><?= e($user['username']) ?></strong></span>
            <a class="btn btn-ghost" href="/index.php">View portal</a>
            <a class="btn btn-ghost" href="/logout.php">Sign out</a>
        </div>
    </div>
</header>

<main class="page">
    <h1>Admin</h1>
    <p class="muted">Manage users, the folder/link tree, and who can see what.</p>

    <div class="admin-grid">
        <a class="admin-card" href="/admin/users.php" style="color:inherit; text-decoration:none;">
            <h3>Users</h3>
            <p class="muted"><?= $userCount ?> account<?= $userCount === 1 ? '' : 's' ?></p>
            <span class="btn btn-ghost btn-sm">Manage users &rarr;</span>
        </a>
        <a class="admin-card" href="/admin/folders.php" style="color:inherit; text-decoration:none;">
            <h3>Folders &amp; links</h3>
            <p class="muted"><?= $folderCount ?> folder<?= $folderCount === 1 ? '' : 's' ?> &middot; <?= $linkCount ?> link<?= $linkCount === 1 ? '' : 's' ?></p>
            <span class="btn btn-ghost btn-sm">Manage structure &rarr;</span>
        </a>
        <a class="admin-card" href="/admin/permissions.php" style="color:inherit; text-decoration:none;">
            <h3>Permissions</h3>
            <p class="muted">Choose what each user can see</p>
            <span class="btn btn-ghost btn-sm">Manage access &rarr;</span>
        </a>
    </div>
</main>
</body>
</html>
