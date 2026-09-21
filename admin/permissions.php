<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
enforce_idle_timeout();
$pdo = get_pdo();

$error = '';
$success = '';

$users = $pdo->query('SELECT id, username, is_admin FROM users ORDER BY username')->fetchAll();

$selectedUserId = (int) ($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_permissions') {
    csrf_verify();
    $userId = (int) ($_POST['user_id'] ?? 0);

    $userCheck = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $userCheck->execute([$userId]);
    if (!$userCheck->fetch()) {
        $error = 'User not found.';
    } else {
        $selections = $_POST['perm'] ?? [];
        $folderIds = [];
        $linkIds = [];
        foreach ($selections as $raw) {
            if (!is_string($raw) || strpos($raw, ':') === false) {
                continue;
            }
            [$type, $id] = explode(':', $raw, 2);
            $id = (int) $id;
            if ($type === 'folder' && $id > 0) {
                $folderIds[] = $id;
            } elseif ($type === 'link' && $id > 0) {
                $linkIds[] = $id;
            }
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM permissions WHERE user_id = ?')->execute([$userId]);
            $insert = $pdo->prepare('INSERT IGNORE INTO permissions (user_id, target_type, target_id) VALUES (?, ?, ?)');
            foreach ($folderIds as $fid) {
                $insert->execute([$userId, 'folder', $fid]);
            }
            foreach ($linkIds as $lid) {
                $insert->execute([$userId, 'link', $lid]);
            }
            $pdo->commit();
            $success = 'Permissions saved.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('permissions.php error: ' . $e->getMessage());
            $error = 'Something went wrong saving permissions.';
        }
    }
    $selectedUserId = $userId;
}

$selectedUser = null;
$tree = [];
if ($selectedUserId > 0) {
    foreach ($users as $u) {
        if ((int) $u['id'] === $selectedUserId) {
            $selectedUser = $u;
            break;
        }
    }
    if ($selectedUser) {
        $tree = build_full_tree($selectedUserId);
    }
}

function render_permission_tree(array $nodes): string
{
    $html = '<ul>';
    foreach ($nodes as $node) {
        $fid = (int) $node['id'];
        $checked = !empty($node['granted']) ? ' checked' : '';
        $html .= '<li>';
        $html .= '<label><input type="checkbox" class="folder-check" name="perm[]" value="folder:' . $fid . '"' . $checked . '> ' . e($node['name']) . '</label>';

        $hasContent = !empty($node['children']) || !empty($node['links']);
        if ($hasContent) {
            $html .= '<ul>';
            foreach ($node['links'] as $link) {
                $lid = (int) $link['id'];
                $lchecked = !empty($link['granted']) ? ' checked' : '';
                $html .= '<li><label class="link-label"><input type="checkbox" class="link-check" name="perm[]" value="link:' . $lid . '"' . $lchecked . '> ' . e($link['title']) . '</label></li>';
            }
            $html .= '</ul>';
            if (!empty($node['children'])) {
                $html .= render_permission_tree($node['children']);
            }
            $html .= '</ul>';
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Permissions &mdash; <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-keepalive="1">
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand"><?= brand_logo() ?><span class="brand-name"><?= e(APP_NAME) ?> <span class="badge">Admin</span></span></div>
        <div class="topbar-right">
            <a class="btn btn-ghost" href="/index.php">View portal</a>
            <a class="btn btn-ghost" href="/logout.php">Sign out</a>
        </div>
    </div>
</header>
<main class="page">
    <a class="back-link" href="/admin/index.php">&larr; Admin home</a>
    <h1>Permissions</h1>
    <p class="muted">Check a folder to grant everything nested inside it. Check a single link to grant just that link.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="panel">
        <form method="get" class="inline-form">
            <div>
                <label for="user_id">Choose a user</label>
                <select id="user_id" name="user_id" onchange="this.form.submit()">
                    <option value="">Select a user&hellip;</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= $selectedUserId === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['username']) ?><?= $u['is_admin'] ? ' (admin — sees everything)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($selectedUser): ?>
        <?php if ($selectedUser['is_admin']): ?>
            <div class="panel">
                <p><strong><?= e($selectedUser['username']) ?></strong> is an admin and automatically sees the entire tree. Remove admin status on the <a href="/admin/users.php">Users</a> page to set specific permissions instead.</p>
            </div>
        <?php elseif (empty($tree)): ?>
            <div class="panel">
                <p class="muted">No folders exist yet. Add some on the <a href="/admin/folders.php">Folders &amp; links</a> page first.</p>
            </div>
        <?php else: ?>
            <div class="panel">
                <h2>Access for <?= e($selectedUser['username']) ?></h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_permissions">
                    <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                    <div class="perm-tree"><?= render_permission_tree($tree) ?></div>
                    <button type="submit" class="btn btn-primary" style="margin-top:1.2em;">Save permissions</button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script src="/assets/js/app.js"></script>
</body>
</html>
