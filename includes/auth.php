<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Auto-restore session from remember-me cookie if not already logged in
_auth_try_remember_me();

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'admin');
}

function is_super(): bool {
    return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'super');
}

function require_login(): void {
    if (!is_logged_in()) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        header('Location: ' . $base . '/login.php?next=' . $next);
        exit;
    }
}

function require_admin_or_super(): void {
    if (!(is_admin() || is_super())) {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $next = $_SERVER['REQUEST_URI'] ?? '/admin/sw_admin.php';

        $nextPath  = parse_url($next, PHP_URL_PATH) ?? '/';
        $nextQuery = parse_url($next, PHP_URL_QUERY) ?? '';
        if ($base !== '' && !str_starts_with($nextPath, $base . '/')) {
            $nextPath = $base . '/' . ltrim($nextPath, '/');
        }
        $nextFinal = $nextPath . ($nextQuery ? ('?' . $nextQuery) : '');

        header('Location: ' . $base . '/login.php?next=' . urlencode($nextFinal));
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $next = $_SERVER['REQUEST_URI'] ?? '/admin/sw_admin.php';

        $nextPath  = parse_url($next, PHP_URL_PATH) ?? '/';
        $nextQuery = parse_url($next, PHP_URL_QUERY) ?? '';
        if ($base !== '' && !str_starts_with($nextPath, $base . '/')) {
            $nextPath = $base . '/' . ltrim($nextPath, '/');
        }
        $nextFinal = $nextPath . ($nextQuery ? ('?' . $nextQuery) : '');

        header('Location: ' . $base . '/login.php?next=' . urlencode($nextFinal));
        exit;
    }
}

/** Auto-restore session from remember-me cookie */
function _auth_try_remember_me(): void {
    if (isset($_SESSION['user_id'])) return;
    if (!isset($_COOKIE['sw_remember'])) return;

    $raw = (string)$_COOKIE['sw_remember'];
    $sep = strpos($raw, ':');
    if ($sep === false) return;

    $uid   = (int)substr($raw, 0, $sep);
    $token = substr($raw, $sep + 1);
    if ($uid <= 0 || strlen($token) < 32) return;

    $token_hash = hash('sha256', $token);

    try {
        $db   = db();
        $stmt = $db->prepare(
            "SELECT user_id, role FROM tblzuser WHERE user_id = ? AND remember_token = ? AND remember_expires > NOW() AND IsActive = 1 LIMIT 1"
        );
        if (!$stmt) return;
        $stmt->bind_param('is', $uid, $token_hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return;

        $_SESSION['user_id']   = (int)$row['user_id'];
        $_SESSION['user_role'] = (string)$row['role'];
        _auth_set_remember_cookie((int)$row['user_id'], $token);
    } catch (Throwable $e) {
        // Stille feil – cookie er bare en komfort-funksjon
    }
}

/** Lagrer nytt remember-me token i DB og setter cookie (kalles ved innlogging) */
function auth_set_remember_token(int $user_id): void {
    $token      = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires    = date('Y-m-d H:i:s', strtotime('+30 days'));

    with_rw(function (\mysqli $rw) use ($user_id, $token_hash, $expires) {
        $stmt = $rw->prepare("UPDATE tblzuser SET remember_token = ?, remember_expires = ? WHERE user_id = ?");
        $stmt->bind_param('ssi', $token_hash, $expires, $user_id);
        $stmt->execute();
        $stmt->close();
    });

    _auth_set_remember_cookie($user_id, $token);
}

/** Sletter remember-me token fra DB og fjerner cookie (kalles ved utlogging) */
function auth_clear_remember_token(int $user_id): void {
    try {
        with_rw(function (\mysqli $rw) use ($user_id) {
            $stmt = $rw->prepare("UPDATE tblzuser SET remember_token = NULL, remember_expires = NULL WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
        });
    } catch (Throwable $e) {
        // Ignorer feil ved sletting – cookie fjernes uansett
    }

    setcookie('sw_remember', '', [
        'expires'  => time() - 3600,
        'path'     => _auth_cookie_path(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function _auth_set_remember_cookie(int $user_id, string $token): void {
    setcookie('sw_remember', $user_id . ':' . $token, [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => _auth_cookie_path(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function _auth_cookie_path(): string {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    return $base !== '' ? $base . '/' : '/';
}
