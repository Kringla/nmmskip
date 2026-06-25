<?php
// admin/dashboard.php
// Videresender til sw_admin.php som er det operative admin-grensesnittet.
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
header('Location: ' . $base . '/admin/sw_admin.php');
exit;
