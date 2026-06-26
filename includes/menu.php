<?php
// includes/menu.php

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

$current  = basename($_SERVER['SCRIPT_NAME'] ?? '');
$req      = $_SERVER['REQUEST_URI'] ?? '';
$loggedIn = !empty($_SESSION['user_id']);
$isIndex  = ($current === 'index.php');
$inAdmin  = (strpos($req, '/admin/') !== false);

$showHome   = false;
$showLogin  = false;
$showLogout = false;

if ($loggedIn) {
    $showHome   = true;
    $showLogout = true;
} elseif ($isIndex) {
    // Logg inn-knapp er i sw-audio-raden på index.php – ikke vis i tab-menyen
} else {
    $showHome  = true;
    $showLogin = true;
}

if ($inAdmin) {
    $showLogin = false; // aldri vis Logg inn-lenke inne i admin-seksjonen
}

$base       = rtrim(BASE_URL, '/');
$homeHref   = $base . '/index.php';
$loginHref  = $base . '/login.php';
$logoutHref = $base . '/logout.php';
?>
<div class="mdc-tab-bar" role="tablist" aria-label="Hovedmeny">
  <div class="mdc-tab-scroller">
    <div class="mdc-tab-scroller__scroll-area">
      <div class="mdc-tab-scroller__scroll-content">

        <?php if ($showHome): ?>
        <a class="mdc-tab<?= $isIndex ? ' mdc-tab--active' : '' ?>"
           role="tab" href="<?= $homeHref ?>"
           <?= $isIndex ? 'aria-current="page"' : '' ?>>
          <span class="mdc-tab__content">
            <span class="mdc-tab__text-label"><?= h('Hjem') ?></span>
          </span>
          <span class="mdc-tab-indicator<?= $isIndex ? ' mdc-tab-indicator--active' : '' ?>">
            <span class="mdc-tab-indicator__content mdc-tab-indicator__content--underline"></span>
          </span>
          <span class="mdc-tab__ripple"></span>
        </a>
        <?php endif; ?>

        <?php if ($showLogin): ?>
        <a class="mdc-tab<?= $current === 'login.php' ? ' mdc-tab--active' : '' ?>"
           role="tab" href="<?= $loginHref ?>"
           <?= $current === 'login.php' ? 'aria-current="page"' : '' ?>>
          <span class="mdc-tab__content">
            <span class="mdc-tab__text-label"><?= h('Logg inn') ?></span>
          </span>
          <span class="mdc-tab-indicator<?= $current === 'login.php' ? ' mdc-tab-indicator--active' : '' ?>">
            <span class="mdc-tab-indicator__content mdc-tab-indicator__content--underline"></span>
          </span>
          <span class="mdc-tab__ripple"></span>
        </a>
        <?php endif; ?>

        <?php if ($showLogout): ?>
        <a class="mdc-tab mdc-tab--logout" role="tab" href="<?= $logoutHref ?>">
          <span class="mdc-tab__content">
            <span class="mdc-tab__text-label"><?= h('Logg ut') ?></span>
          </span>
          <span class="mdc-tab-indicator">
            <span class="mdc-tab-indicator__content mdc-tab-indicator__content--underline"></span>
          </span>
          <span class="mdc-tab__ripple"></span>
        </a>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
