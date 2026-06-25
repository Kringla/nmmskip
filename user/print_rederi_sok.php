<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$selRederi = isset($_GET['rederi']) ? trim((string)$_GET['rederi']) : '';
$section = isset($_GET['section']) ? strtolower((string)$_GET['section']) : 'rederi';
$orient  = isset($_GET['orient']) ? strtoupper((string)$_GET['orient']) : 'P';
$bodyClass = ($orient === 'L') ? 'print-landscape' : 'print-portrait';

/** @var mysqli $conn */
$db = $conn ?? ($mysqli ?? null);
if (!$db) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler'; exit; }
$db->set_charset('utf8mb4');

$RE_T = "RTRIM( TRIM(TRAILING CONCAT(' ', t.RegHavn) FROM SUBSTRING( t.Rederi, 1, GREATEST( CASE WHEN LOCATE(', ', t.Rederi) > 0 THEN CHAR_LENGTH(t.Rederi) - LOCATE(', ', REVERSE(t.Rederi)) - 1 ELSE 0 END, CASE WHEN LOCATE(') ', t.Rederi) > 0 THEN CHAR_LENGTH(t.Rederi) - LOCATE(') ', REVERSE(t.Rederi)) - 1 ELSE 0 END, CHAR_LENGTH(t.Rederi)))) )";
$RE_FT = "RTRIM( TRIM(TRAILING CONCAT(' ', ft.RegHavn) FROM SUBSTRING( ft.Rederi, 1, GREATEST( CASE WHEN LOCATE(', ', ft.Rederi) > 0 THEN CHAR_LENGTH(ft.Rederi) - LOCATE(', ', REVERSE(ft.Rederi)) - 1 ELSE 0 END, CASE WHEN LOCATE(') ', ft.Rederi) > 0 THEN CHAR_LENGTH(ft.Rederi) - LOCATE(') ', REVERSE(ft.Rederi)) - 1 ELSE 0 END, CHAR_LENGTH(ft.Rederi)))) )";

$rows=[]; $title='Rederisøk';
if ($section==='rederi') {
  $title='Rederier funnet';
  if ($q !== '' && mb_strlen($q) >= 2) {
    $sql = "SELECT DISTINCT $RE_T AS Rederi FROM tblfarttid t WHERE $RE_T LIKE CONCAT('%', ?, '%') AND t.Rederi IS NOT NULL AND t.Rederi <> '' ORDER BY Rederi LIMIT 500";
    $stmt=$db->prepare($sql); $stmt->bind_param('s',$q); $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
  }
}
if ($section==='fartoy') {
  $title='Fartøyer eid av valgt rederi';
  if ($selRederi !== '') {
    $sql = "SELECT ft.FartNavn, ft.RegHavn, ft.YearTid, ft.MndTid, zt.TypeFork, CASE WHEN fo.FartObj_ID IS NOT NULL THEN 1 ELSE 0 END AS Objekt FROM tblfarttid ft LEFT JOIN tblzfarttype zt ON zt.FartType_ID = ft.FartType_ID LEFT JOIN tblfartobj fo ON fo.FartObj_ID = ft.FartObj_ID WHERE $RE_FT = ? ORDER BY ft.YearTid, ft.MndTid, ft.FartNavn LIMIT 1000";
    $stmt=$db->prepare($sql); $stmt->bind_param('s',$selRederi); $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
  }
}

$ts = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="no"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <link rel="stylesheet" href="<?= h(rtrim(BASE_URL,'/')) ?>/assets/css/app.css">
</head>
<body class="print-page <?= h($bodyClass) ?>">
  <div class="print-controls no-print">
    <a class="btn" href="#" onclick="window.print(); return false;">Skriv ut</a>
    <a class="btn" href="#" onclick="history.back(); return false;">Tilbake</a>
  </div>
  <div class="print-container">
    <h1 class="print-title"><?= h($title) ?></h1>
    <div class="print-meta">
      Generert: <?= h($ts) ?>
      <?php if ($section==='rederi'): ?>
        | Kriterier: Søk=<?= h($q) ?>
      <?php else: ?>
        | Kriterier: Rederi=<?= h($selRederi) ?>
      <?php endif; ?>
    </div>
    <div class="print-criteria" style="margin: 4mm 0;">
      <table class="print-table"><tbody>
        <?php if ($section==='rederi'): ?>
          <tr><th>Søk</th><td><?= h($q) ?></td></tr>
        <?php else: ?>
          <tr><th>Rederi</th><td><?= h($selRederi) ?></td></tr>
        <?php endif; ?>
      </tbody></table>
    </div>
    <table class="table print-table"><thead>
      <?php if ($section==='rederi'): ?>
        <tr><th>Rederi</th></tr>
      <?php else: ?>
        <tr><th>Navn</th><th>Reg. havn</th><th>Fra år/mnd</th><th>Objekt</th></tr>
      <?php endif; ?>
    </thead><tbody>
      <?php if (!$rows): ?><tr><td colspan="4">Ingen data.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php if ($section==='rederi'): ?>
          <tr><td><?= h($r['Rederi']??'') ?></td></tr>
        <?php else: ?>
          <?php $ym=(isset($r['YearTid'])?(int)$r['YearTid']:0); $ym.=isset($r['MndTid'])? '/'.str_pad((string)(int)$r['MndTid'],2,'0',STR_PAD_LEFT):''; $navn=trim((string)($r['TypeFork']??'')); $navn=($navn!==''?$navn.' ':'').(string)($r['FartNavn']??''); ?>
          <tr><td><?= h($navn) ?></td><td><?= h($r['RegHavn']??'') ?></td><td><?= h($ym) ?></td><td><?= isset($r['Objekt'])&&(int)$r['Objekt']===1?'1':'0' ?></td></tr>
        <?php endif; ?>
      <?php endforeach; endif; ?>
    </tbody></table>
  </div>
  <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),200));</script>
</body></html>
