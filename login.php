<?php
// login.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

// Allerede innlogget? Viderekoble basert på rolle
if (is_logged_in()) {
    if (is_admin() || is_super()) {
        header('Location: ' . $BASE . '/admin/dashboard.php');
    } else {
        header('Location: ' . $BASE . '/index.php');
    }
    exit;
}

$nextRaw = isset($_REQUEST['next']) && is_string($_REQUEST['next']) ? trim($_REQUEST['next']) : '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    $stmt = $conn->prepare("SELECT user_id, role, password FROM tblzuser WHERE email = ? AND IsActive = 1");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($uid, $role, $hash);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found && password_verify($password, (string)$hash)) {
            $_SESSION['user_id']   = (int)$uid;
            $_SESSION['user_role'] = (string)$role;

            if ($remember) {
                auth_set_remember_token((int)$uid);
            }

            $isAdminRole = in_array($role, ['admin', 'super'], true);
            if (!$isAdminRole) {
                // user-rolle: gi beskjed om eksport, send til forsiden
                $_SESSION['flash_success'] = 'Du er nå innlogget og kan eksportere søkeresultater.';
                header('Location: ' . $BASE . '/index.php');
            } elseif ($nextRaw !== '') {
                header('Location: ' . _login_safe_redirect($nextRaw, $BASE));
            } else {
                header('Location: ' . $BASE . '/admin/dashboard.php');
            }
            exit;
        } else {
            $error = 'Feil e-post eller passord.';
        }
    } else {
        $error = 'Teknisk feil: kunne ikke forberede spørring.';
    }
}

/** Bygg trygt redirect-mål – kun relative paths med korrekt BASE_URL-prefiks */
function _login_safe_redirect(string $rawNext, string $base): string {
    $path  = parse_url($rawNext, PHP_URL_PATH) ?? '';
    $query = parse_url($rawNext, PHP_URL_QUERY) ?? '';
    if ($path === '' || $path === '/') {
        $path = '/';
    }
    if ($base !== '' && !str_starts_with($path, $base . '/') && $path !== $base) {
        $path = $base . '/' . ltrim($path, '/');
    }
    return $path . ($query !== '' ? ('?' . $query) : '');
}
?>
<!DOCTYPE html>
<html lang="no">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Innlogging på SkipsWeb</title>
  <link rel="stylesheet" href="<?= h($BASE) ?>/assets/css/app.css">
</head>
<body class="page-login">
  <div class="topbar">
    <img class="logo" src="<?= h(($BASE !== '' ? $BASE : '') . '/assets/img/skipsweb-logo.jpg') ?>" alt="SkipsWeb">
  </div>

  <div class="wrap">
    <h1>Innlogging på SkipsWeb</h1>

    <?php if (!empty($error)): ?>
      <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form class="card" method="post" action="">
      <input type="hidden" name="next" value="<?= h($nextRaw) ?>">

      <div class="field">
        <label class="label" for="email">E-post</label>
        <input class="input" type="email" id="email" name="email" autocomplete="username" required autofocus>
      </div>

      <div class="field">
        <label class="label" for="password">Passord</label>
        <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
      </div>

      <div class="check-row">
        <input type="checkbox" id="remember" name="remember" value="1">
        <label for="remember">Husk meg (30 dager)</label>
      </div>

      <div class="mt-2l">
        <button class="mdc-button mdc-button--raised btn" type="submit"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Logg inn</span></button>
      </div>
    </form>
  </div>
</body>
</html>
