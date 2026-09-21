<?php
// Called in the background by assets/js/app.js while someone is actively
// using a page. Refreshes the session's last-activity time.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/auth.php';

header('Cache-Control: no-store');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

if (!session_touch_or_expire()) {
    http_response_code(401);
    echo '{"ok":false,"reason":"expired"}';
    exit;
}

echo '{"ok":true}';
