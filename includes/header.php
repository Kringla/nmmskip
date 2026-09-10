<?php require_once __DIR__ . '/bootstrap.php'; ?>
<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$role_class = 'role-guest';
if (!empty($_SESSION['user_role'])) {
    $role_class = ($_SESSION['user_role'] === 'admin') ? 'role-admin' : 'role-user';
}

$page_class = isset($page_class) && is_string($page_class) ? $page_class : 'page';
$body_class = trim($role_class . ' ' . $page_class);
$page_title = isset($page_title) && is_string($page_title) ? $page_title : 'SkipsWeb';

$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
?>
<!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="https://unpkg.com/material-components-web@latest/dist/material-components-web.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?php echo $BASE; ?>/assets/css/app.css?v=4">
  <script defer src="<?php echo $BASE; ?>/assets/js/hero-rotator.js"></script>
</head>
<body<?= !empty($body_class) ? ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

<header class="mdc-top-app-bar site-header">
  <div class="mdc-top-app-bar__row site-header-inner">
    <section class="mdc-top-app-bar__section mdc-top-app-bar__section--align-start">
      <a class="mdc-top-app-bar__title brand" href="<?php echo $BASE; ?>/">
        <img
          src="<?php echo $BASE; ?>/assets/img/SkipsWeb_logo_white.svg"
          alt="SkipsWeb" class="logo">
      </a>
    </section>
    <section class="mdc-top-app-bar__section mdc-top-app-bar__section--align-end site-header-auth">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
          <a class="site-header-auth__btn" href="<?= $BASE ?>/admin/sw_admin.php">Admin</a>
        <?php else: ?>
          <span class="site-header-auth__label">Innlogget</span>
        <?php endif; ?>
        <a class="mdc-button mdc-button--raised btn sw-audio-btn" href="<?= $BASE ?>/logout.php">
          <span class="mdc-button__ripple"></span>
          <span class="mdc-button__label">Logg ut</span>
        </a>
      <?php else: ?>
        <a class="mdc-button mdc-button--raised btn sw-audio-btn" href="<?= $BASE ?>/login.php">
          <span class="mdc-button__ripple"></span>
          <span class="mdc-button__label">Logg inn</span>
        </a>
      <?php endif; ?>
    </section>
  </div>
</header>
