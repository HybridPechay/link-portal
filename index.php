<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
enforce_idle_timeout();

$user = current_user();
$tree = build_visible_tree($user['id'], $user['is_admin']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true"></span>
            <span class="brand-name"><?= e(APP_NAME) ?></span>
        </div>
        <div class="topbar-right">
            <span class="who">Signed in as <strong><?= e($user['username']) ?></strong><?= $user['is_admin'] ? ' <span class="badge">Admin</span>' : '' ?></span>
            <?php if ($user['is_admin']): ?>
                <a class="btn btn-ghost" href="/admin/index.php">Admin</a>
            <?php endif; ?>
            <a class="btn btn-ghost" href="/logout.php">Sign out</a>
        </div>
    </div>
</header>

<main class="page">
    <div class="page-head">
        <h1>Accreditation 2026</h1>
        <input type="search" id="tree-search" class="search-input" placeholder="Search links and folders&hellip;" aria-label="Search links and folders">
    </div>

    <?php if (empty($tree)): ?>
        <div class="empty-state">
            <p>No links have been shared with your account yet.</p>
            <p class="muted">If you think this is a mistake, contact your administrator.</p>
        </div>
    <?php else: ?>
        <div id="tree-root"><?= render_tree_html($tree) ?></div>
    <?php endif; ?>
</main>

<script src="/assets/js/app.js"></script>
</body>
</html>
