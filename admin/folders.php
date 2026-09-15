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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_folder') {
            $name = trim($_POST['name'] ?? '');
            $parentId = ($_POST['parent_id'] ?? '') === '' ? null : (int) $_POST['parent_id'];
            if ($name === '' || mb_strlen($name) > 150) {
                $error = 'Folder name must be 1-150 characters.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO folders (parent_id, name) VALUES (?, ?)');
                $stmt->execute([$parentId, $name]);
                $success = 'Folder "' . $name . '" created.';
            }
        } elseif ($action === 'add_link') {
            $folderId = (int) ($_POST['folder_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $url = trim($_POST['url'] ?? '');
            if ($title === '' || mb_strlen($title) > 200) {
                $error = 'Link title must be 1-200 characters.';
            } elseif (!validate_url($url)) {
                $error = 'Please enter a valid http:// or https:// URL.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO links (folder_id, title, url) VALUES (?, ?, ?)');
                $stmt->execute([$folderId, $title, $url]);
                $success = 'Link "' . $title . '" added.';
            }
        } elseif ($action === 'rename_folder') {
            $id = (int) ($_POST['folder_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '' || mb_strlen($name) > 150) {
                $error = 'Folder name must be 1-150 characters.';
            } else {
                $pdo->prepare('UPDATE folders SET name = ? WHERE id = ?')->execute([$name, $id]);
                $success = 'Folder renamed.';
            }
        } elseif ($action === 'edit_link') {
            $id = (int) ($_POST['link_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $url = trim($_POST['url'] ?? '');
            if ($title === '' || mb_strlen($title) > 200) {
                $error = 'Link title must be 1-200 characters.';
            } elseif (!validate_url($url)) {
                $error = 'Please enter a valid http:// or https:// URL.';
            } else {
                $pdo->prepare('UPDATE links SET title = ?, url = ? WHERE id = ?')->execute([$title, $url, $id]);
                $success = 'Link updated.';
            }
        } elseif ($action === 'delete_folder') {
            $id = (int) ($_POST['folder_id'] ?? 0);
            $pdo->prepare('DELETE FROM folders WHERE id = ?')->execute([$id]);
            $success = 'Folder deleted (along with anything nested inside it).';
        } elseif ($action === 'delete_link') {
            $id = (int) ($_POST['link_id'] ?? 0);
            $pdo->prepare('DELETE FROM links WHERE id = ?')->execute([$id]);
            $success = 'Link deleted.';
        }
    } catch (PDOException $e) {
        error_log('folders.php error: ' . $e->getMessage());
        $error = 'Something went wrong. Please try again.';
    }
}

$tree = build_full_tree();
$allFolders = fetch_all_folders();

/**
 * Renders the admin tree: each folder gets rename/delete controls plus
 * inline "add subfolder" / "add link" forms; each link gets edit/delete.
 */
function render_admin_tree(array $nodes, int $depth = 0): string
{
    if (empty($nodes)) {
        return '';
    }
    $html = '<ul class="tree-level" role="list">';
    foreach ($nodes as $node) {
        $fid = (int) $node['id'];
        $openAttr = $depth === 0 ? ' open' : '';
        $html .= '<li class="tree-node">';
        $html .= '<details' . $openAttr . '>';
        $html .= '<summary>' . e($node['name']) . '</summary>';
        $html .= '<div class="tree-node-body">';

        // Rename / delete this folder
        $html .= '<div class="row-actions" style="margin-bottom:0.8em;">';
        $html .= '<form method="post" style="display:flex; gap:0.4em;">' . csrf_field()
            . '<input type="hidden" name="action" value="rename_folder">'
            . '<input type="hidden" name="folder_id" value="' . $fid . '">'
            . '<input type="text" name="name" value="' . e($node['name']) . '" style="width:auto;" required maxlength="150">'
            . '<button type="submit" class="btn btn-ghost btn-sm">Rename</button></form>';
        $html .= '<form method="post" data-confirm="Delete &quot;' . e($node['name']) . '&quot; and everything nested inside it?">' . csrf_field()
            . '<input type="hidden" name="action" value="delete_folder">'
            . '<input type="hidden" name="folder_id" value="' . $fid . '">'
            . '<button type="submit" class="btn btn-danger btn-sm">Delete folder</button></form>';
        $html .= '</div>';

        // Links in this folder
        if (!empty($node['links'])) {
            $html .= '<table class="data-table" style="margin-bottom:0.8em;"><tbody>';
            foreach ($node['links'] as $link) {
                $lid = (int) $link['id'];
                $html .= '<tr><td>'
                    . '<form method="post" class="inline-form" style="gap:0.4em;">' . csrf_field()
                    . '<input type="hidden" name="action" value="edit_link">'
                    . '<input type="hidden" name="link_id" value="' . $lid . '">'
                    . '<input type="text" name="title" value="' . e($link['title']) . '" style="width:auto; min-width:140px;" required maxlength="200">'
                    . '<input type="url" name="url" value="' . e($link['url']) . '" style="width:auto; min-width:220px;" required>'
                    . '<button type="submit" class="btn btn-ghost btn-sm">Save</button>'
                    . '</form></td><td style="width:1%;">'
                    . '<form method="post" data-confirm="Delete link &quot;' . e($link['title']) . '&quot;?">' . csrf_field()
                    . '<input type="hidden" name="action" value="delete_link">'
                    . '<input type="hidden" name="link_id" value="' . $lid . '">'
                    . '<button type="submit" class="btn btn-danger btn-sm">Delete</button></form>'
                    . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        // Add subfolder / add link forms
        $html .= '<div class="inline-form" style="margin-bottom:0.6em;">' . csrf_field()
            . '<form method="post" class="inline-form" style="gap:0.4em;">' . csrf_field()
            . '<input type="hidden" name="action" value="add_folder">'
            . '<input type="hidden" name="parent_id" value="' . $fid . '">'
            . '<input type="text" name="name" placeholder="New subfolder name" style="width:auto; min-width:160px;" required maxlength="150">'
            . '<button type="submit" class="btn btn-ghost btn-sm">+ Subfolder</button></form>';
        $html .= '<form method="post" class="inline-form" style="gap:0.4em;">' . csrf_field()
            . '<input type="hidden" name="action" value="add_link">'
            . '<input type="hidden" name="folder_id" value="' . $fid . '">'
            . '<input type="text" name="title" placeholder="Link title" style="width:auto; min-width:140px;" required maxlength="200">'
            . '<input type="url" name="url" placeholder="https://&hellip;" style="width:auto; min-width:200px;" required>'
            . '<button type="submit" class="btn btn-ghost btn-sm">+ Link</button></form>';
        $html .= '</div>';

        if (!empty($node['children'])) {
            $html .= render_admin_tree($node['children'], $depth + 1);
        }

        $html .= '</div></details></li>';
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
<title>Folders &amp; links &mdash; <?= e(APP_NAME) ?></title>
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
    <h1>Folders &amp; links</h1>
    <p class="muted">Build the tree here &mdash; programs, areas inside them, and the links inside those. Nesting can go as deep as you need.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="panel">
        <h2>Add a top-level folder</h2>
        <p class="muted" style="margin-top:-0.6em;">e.g. a program like BSBA, BSFT, BSIT.</p>
        <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_folder">
            <input type="hidden" name="parent_id" value="">
            <div>
                <label for="new-root-name">Folder name</label>
                <input type="text" id="new-root-name" name="name" required maxlength="150">
            </div>
            <div style="flex: 0 0 auto;"><button type="submit" class="btn btn-primary" style="margin-top:0.35em;">Add folder</button></div>
        </form>
    </div>

    <div class="panel">
        <h2>Structure</h2>
        <?php if (empty($tree)): ?>
            <p class="muted">No folders yet. Add your first one above.</p>
        <?php else: ?>
            <?= render_admin_tree($tree) ?>
        <?php endif; ?>
    </div>
</main>
<script src="/assets/js/app.js"></script>
</body>
</html>
