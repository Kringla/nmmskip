<?php
// logout.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$uid = (int)($_SESSION['user_id'] ?? 0);

if ($uid > 0) {
    auth_clear_remember_token($uid);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}
session_destroy();

$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
header('Location: ' . $BASE . '/index.php');
exit;
