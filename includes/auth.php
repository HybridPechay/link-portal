<?php
require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (empty($_SESSION['user']['is_admin'])) {
        http_response_code(403);
        die('Access denied.');
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_locked_out(string $username, string $ip): bool
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE (username = :u OR ip_address = :ip)
           AND success = 0
           AND attempted_at > (NOW() - INTERVAL :mins MINUTE)"
    );
    $stmt->bindValue(':u', $username);
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':mins', LOGIN_LOCKOUT_MINUTES, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
}

function record_attempt(string $username, string $ip, bool $success): void
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)');
    $stmt->execute([$username, $ip, $success ? 1 : 0]);
}

/**
 * @return array{ok: bool, message: string}
 */
function attempt_login(string $username, string $password): array
{
    $ip = client_ip();
    $username = trim($username);

    if ($username === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter your username and password.'];
    }

    if (is_locked_out($username, $ip)) {
        return ['ok' => false, 'message' => 'Too many failed attempts. Please wait ' . LOGIN_LOCKOUT_MINUTES . ' minutes and try again.'];
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
        record_attempt($username, $ip, false);
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }

    record_attempt($username, $ip, true);

    // New session id on every privilege change — prevents session fixation.
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'       => (int) $user['id'],
        'username' => $user['username'],
        'is_admin' => (bool) $user['is_admin'],
    ];
    $_SESSION['last_activity'] = time();

    // Transparently upgrade older hashes if the cost/algorithm default changes.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$newHash, $user['id']]);
    }

    return ['ok' => true, 'message' => 'Welcome back.'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Returns true if the current session is still within the idle window
 * (and refreshes the activity timestamp). Returns false, after logging the
 * user out, if they've been idle too long.
 */
function session_touch_or_expire(): bool
{
    if (!current_user()) {
        return false;
    }
    $maxIdle = IDLE_TIMEOUT_MINUTES * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $maxIdle) {
        logout_user();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/** Idle timeout for normal page loads: redirect to login when expired. */
function enforce_idle_timeout(): void
{
    if (!current_user()) {
        return;
    }
    if (!session_touch_or_expire()) {
        header('Location: /login.php?timeout=1');
        exit;
    }
}
