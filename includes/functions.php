<?php
require_once __DIR__ . '/db.php';

function fetch_all_folders(): array
{
    return get_pdo()->query('SELECT * FROM folders ORDER BY parent_id IS NULL DESC, sort_order, name')->fetchAll();
}

function fetch_all_links(): array
{
    return get_pdo()->query('SELECT * FROM links ORDER BY sort_order, title')->fetchAll();
}

/**
 * Indexes raw folder/link rows for fast tree traversal.
 * @return array{0: array, 1: array, 2: array} [foldersById, childrenOf(parentId=>[folderId]), linksOf(folderId=>[link])]
 */
function index_tree(array $folders, array $links): array
{
    $foldersById = [];
    $childrenOf = [];
    foreach ($folders as $f) {
        $foldersById[(int) $f['id']] = $f;
        $parentKey = $f['parent_id'] === null ? 0 : (int) $f['parent_id'];
        $childrenOf[$parentKey][] = (int) $f['id'];
    }
    $linksOf = [];
    foreach ($links as $l) {
        $linksOf[(int) $l['folder_id']][] = $l;
    }
    return [$foldersById, $childrenOf, $linksOf];
}

/**
 * @return array{0: array<int,bool>, 1: array<int,bool>} [permittedFolderIds, permittedLinkIds]
 */
function get_user_permissions(int $userId): array
{
    $stmt = get_pdo()->prepare('SELECT target_type, target_id FROM permissions WHERE user_id = ?');
    $stmt->execute([$userId]);
    $folderIds = [];
    $linkIds = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['target_type'] === 'folder') {
            $folderIds[(int) $row['target_id']] = true;
        } else {
            $linkIds[(int) $row['target_id']] = true;
        }
    }
    return [$folderIds, $linkIds];
}

/**
 * Recursively renders a folder node if it (or an ancestor) is permitted,
 * or if it merely sits on the path leading down to a permitted descendant.
 */
function render_visible_node(
    int $folderId,
    array $foldersById,
    array $childrenOf,
    array $linksOf,
    array $permittedFolders,
    array $permittedLinks,
    array $pathFolders,
    bool $fullAccessFromParent
): ?array {
    $fullAccess = $fullAccessFromParent || isset($permittedFolders[$folderId]);
    $visible = $fullAccess || isset($pathFolders[$folderId]);
    if (!$visible) {
        return null;
    }

    $node = $foldersById[$folderId];
    $node['children'] = [];
    $node['links'] = [];

    foreach (($childrenOf[$folderId] ?? []) as $childId) {
        $childNode = render_visible_node($childId, $foldersById, $childrenOf, $linksOf, $permittedFolders, $permittedLinks, $pathFolders, $fullAccess);
        if ($childNode !== null) {
            $node['children'][] = $childNode;
        }
    }

    foreach (($linksOf[$folderId] ?? []) as $link) {
        if ($fullAccess || isset($permittedLinks[(int) $link['id']])) {
            $node['links'][] = $link;
        }
    }

    return $node;
}

/**
 * Builds the tree of folders/links a given user is allowed to see.
 * Admins see everything. Everyone else sees only what's been granted to
 * them, plus the "path" folders needed to navigate down to a granted item.
 */
function build_visible_tree(int $userId, bool $isAdmin): array
{
    $folders = fetch_all_folders();
    $links = fetch_all_links();
    [$foldersById, $childrenOf, $linksOf] = index_tree($folders, $links);

    if ($isAdmin) {
        $permittedFolders = array_fill_keys(array_keys($foldersById), true);
        $permittedLinks = [];
        foreach ($links as $l) {
            $permittedLinks[(int) $l['id']] = true;
        }
    } else {
        [$permittedFolders, $permittedLinks] = get_user_permissions($userId);
    }

    $parentOf = [];
    foreach ($folders as $f) {
        $parentOf[(int) $f['id']] = $f['parent_id'] === null ? null : (int) $f['parent_id'];
    }

    $pathFolders = [];
    $markAncestors = function (int $folderId) use (&$pathFolders, $parentOf): void {
        $current = $parentOf[$folderId] ?? null;
        while ($current !== null) {
            if (isset($pathFolders[$current])) {
                break;
            }
            $pathFolders[$current] = true;
            $current = $parentOf[$current] ?? null;
        }
    };

    foreach (array_keys($permittedFolders) as $fid) {
        $markAncestors($fid);
    }

    $linksById = [];
    foreach ($links as $l) {
        $linksById[(int) $l['id']] = $l;
    }
    foreach (array_keys($permittedLinks) as $lid) {
        if (isset($linksById[$lid])) {
            $folderId = (int) $linksById[$lid]['folder_id'];
            $pathFolders[$folderId] = true;
            $markAncestors($folderId);
        }
    }

    $tree = [];
    foreach (($childrenOf[0] ?? []) as $rootId) {
        $node = render_visible_node($rootId, $foldersById, $childrenOf, $linksOf, $permittedFolders, $permittedLinks, $pathFolders, false);
        if ($node !== null) {
            $tree[] = $node;
        }
    }
    return $tree;
}

/**
 * Builds the FULL tree (used by the admin permissions/folders screens),
 * annotating each node with whether it's directly granted to $userId (or
 * simply present, if $userId is null).
 */
function build_full_tree(?int $userId = null): array
{
    $folders = fetch_all_folders();
    $links = fetch_all_links();
    [$foldersById, $childrenOf, $linksOf] = index_tree($folders, $links);

    $permittedFolders = [];
    $permittedLinks = [];
    if ($userId !== null) {
        [$permittedFolders, $permittedLinks] = get_user_permissions($userId);
    }

    $build = function (int $folderId) use (&$build, $foldersById, $childrenOf, $linksOf, $permittedFolders, $permittedLinks): array {
        $node = $foldersById[$folderId];
        $node['granted'] = isset($permittedFolders[$folderId]);
        $node['children'] = [];
        $node['links'] = [];
        foreach (($childrenOf[$folderId] ?? []) as $childId) {
            $node['children'][] = $build($childId);
        }
        foreach (($linksOf[$folderId] ?? []) as $link) {
            $link['granted'] = isset($permittedLinks[(int) $link['id']]);
            $node['links'][] = $link;
        }
        return $node;
    };

    $tree = [];
    foreach (($childrenOf[0] ?? []) as $rootId) {
        $tree[] = $build($rootId);
    }
    return $tree;
}

/**
 * Renders a visible tree (from build_visible_tree) as nested collapsible
 * HTML. $depth controls open-by-default behaviour (top level open).
 */
function render_tree_html(array $nodes, int $depth = 0): string
{
    if (empty($nodes)) {
        return '';
    }
    $html = '<ul class="tree-level" role="list">';
    foreach ($nodes as $node) {
        $hasChildren = !empty($node['children']);
        $hasLinks = !empty($node['links']);
        $openAttr = $depth === 0 ? ' open' : '';
        $html .= '<li class="tree-node">';
        if ($hasChildren || $hasLinks) {
            $html .= '<details' . $openAttr . '>';
            $html .= '<summary><span class="folder-icon" aria-hidden="true"></span>' . e($node['name']) . '</summary>';
            $html .= '<div class="tree-node-body">';
            if ($hasLinks) {
                $html .= '<ul class="link-list" role="list">';
                foreach ($node['links'] as $link) {
                    $html .= '<li class="link-item">';
                    $html .= '<a href="' . e($link['url']) . '" target="_blank" rel="noopener noreferrer">';
                    $html .= '<span class="link-icon" aria-hidden="true"></span>' . e($link['title']);
                    $html .= '</a></li>';
                }
                $html .= '</ul>';
            }
            if ($hasChildren) {
                $html .= render_tree_html($node['children'], $depth + 1);
            }
            $html .= '</div></details>';
        } else {
            $html .= '<span class="empty-folder"><span class="folder-icon" aria-hidden="true"></span>' . e($node['name']) . ' <em class="muted">(empty)</em></span>';
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function validate_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** True if $folderId is a descendant of (or equal to) $ancestorId. */
function folder_is_within(int $folderId, int $ancestorId, array $parentOf): bool
{
    $current = $folderId;
    while ($current !== null) {
        if ($current === $ancestorId) {
            return true;
        }
        $current = $parentOf[$current] ?? null;
    }
    return false;
}

/**
 * Brand logo. Uses the image at LOGO_PATH if it exists, otherwise falls back
 * to the original green placeholder mark. $large = bigger version for the
 * sign-in page.
 */
function brand_logo(bool $large = false): string
{
    $file = __DIR__ . '/..' . LOGO_PATH;
    $class = $large ? 'brand-logo brand-logo-lg' : 'brand-logo';
    if (is_file($file)) {
        $v = filemtime($file); // cache-bust when you replace the file
        return '<img class="' . $class . '" src="' . e(LOGO_PATH) . '?v=' . $v . '" alt="' . e(APP_NAME) . ' logo">';
    }
    return '<span class="brand-mark" aria-hidden="true"></span>';
}
