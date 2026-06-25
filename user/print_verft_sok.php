<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$includeSted = isset($_GET['include_sted']) && (string)$_GET['include_sted'] !== '';
$verftId = isset($_GET['verft_id']) ? (int)$_GET['verft_id'] : 0;
$section = isset($_GET['section']) ? strtolower((string)$_GET['section']) : 'leveranse';
$orient  = isset($_GET['orient']) ? strtoupper((string)$_GET['orient']) : 'P';
$bodyClass = ($orient === 'L') ? 'print-landscape' : 'print-portrait';

/** @var mysqli $conn */
$db = $conn ?? ($mysqli ?? null);
if (!$db) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler'; exit; }
$db->set_charset('utf8mb4');

$title = 'Verftsøk'; $rows = [];
if ($section === 'verft') {
  $title = 'Verft funnet';
  if ($q !== '' && mb_strlen($q) >= 2) {
    if ($includeSted) { $sql="SELECT v.VerftNavn, v.Sted, n.Nasjon FROM tblverft v LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID WHERE v.VerftNavn LIKE CONCAT('%', ?, '%') OR v.Sted LIKE CONCAT('%', ?, '%') ORDER BY v.VerftNavn LIMIT 1000"; $stmt=$db->prepare($sql); $stmt->bind_param('ss',$q,$q); }
    else { $sql="SELECT v.VerftNavn, v.Sted, n.Nasjon FROM tblverft v LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID WHERE v.VerftNavn LIKE CONCAT('%', ?, '%') ORDER BY v.VerftNavn LIMIT 1000"; $stmt=$db->prepare($sql); $stmt->bind_param('s',$q); }
    $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
  }
}
if ($section === 'leveranse') {
  $title = 'Levert av valgt verft';
  $sql = "SELECT fs.Byggenr, fs.YearSpes, fs.MndSpes, tid.FartNavn, tid.RegHavn, tid.Rederi, zt.TypeFork FROM tblfartspes fs LEFT JOIN ( SELECT t1.FartSpes_ID, t1.FartTid_ID, t1.FartNavn, t1.RegHavn, t1.Rederi, t1.FartType_ID FROM ( SELECT FartSpes_ID, MAX(FartTid_ID) AS FartTid_ID FROM tblfarttid GROUP BY FartSpes_ID ) tmx JOIN tblfarttid t1 ON t1.FartTid_ID = tmx.FartTid_ID ) tid ON tid.FartSpes_ID = fs.FartSpes_ID LEFT JOIN tblzfarttype zt ON zt.FartType_ID = tid.FartType_ID WHERE fs.Verft_ID = ? ORDER BY fs.YearSpes, fs.MndSpes, tid.FartNavn LIMIT 1000";
  $stmt=$db->prepare($sql); $stmt->bind_param('i',$verftId); $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
}
if ($section === 'skrog') {
  $title = 'Skrog bygget ved valgt verft';
  $sql = "SELECT fs.Byggenr, fs.YearSpes, fs.MndSpes, tid.FartNavn, tid.RegHavn, tid.Rederi, zt.TypeFork, CASE WHEN fo.FartObj_ID IS NOT NULL THEN 1 ELSE 0 END AS Objekt FROM tblfartspes fs LEFT JOIN tblfartobj fo ON fo.FartObj_ID = fs.FartObj_ID LEFT JOIN ( SELECT t1.FartSpes_ID, t1.FartTid_ID, t1.FartNavn, t1.RegHavn, t1.Rederi, t1.FartType_ID FROM ( SELECT FartSpes_ID, MAX(FartTid_ID) AS FartTid_ID FROM tblfarttid GROUP BY FartSpes_ID ) t2 JOIN tblfarttid t1 ON t1.FartTid_ID = t2.FartTid_ID ) tid ON tid.FartSpes_ID = fs.FartSpes_ID LEFT JOIN tblzfarttype zt ON zt.FartType_ID = tid.FartType_ID WHERE fo.SkrogID = ? AND fo.SkrogID IS NOT NULL AND (fs.Verft_ID IS NULL OR fo.SkrogID <> fs.Verft_ID) ORDER BY fs.YearSpes, fs.MndSpes, tid.FartNavn LIMIT 1000";
  $stmt=$db->prepare($sql); $stmt->bind_param('i',$verftId); $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
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
      <?php if ($section==='verft'): ?>
        | Kriterier: Søk=<?= h($q) ?><?= $includeSted ? ', Inkluder sted' : '' ?>
      <?php elseif ($section==='leveranse' || $section==='skrog'): ?>
        <?php
          $verftLabel = '';
          if ($verftId > 0) {
            $vs = $db->prepare('SELECT VerftNavn FROM tblverft WHERE Verft_ID = ? LIMIT 1');
            $vs->bind_param('i',$verftId); $vs->execute(); $vr=$vs->get_result();
            if ($vr && ($row=$vr->fetch_assoc())) { $verftLabel = (string)$row['VerftNavn']; }
            $vs->close();
          }
        ?>
        | Kriterier: Verft=<?= h($verftLabel !== '' ? $verftLabel : ('ID '.$verftId)) ?>
      <?php endif; ?>
    </div>
    <div class="print-criteria" style="margin: 4mm 0;">
      <table class="print-table"><tbody>
        <?php if ($section==='verft'): ?>
          <tr><th>Søk</th><td><?= h($q) ?></td></tr>
          <tr><th>Inkluder sted</th><td><?= $includeSted ? 'Ja' : 'Nei' ?></td></tr>
        <?php elseif ($section==='leveranse' || $section==='skrog'): ?>
          <tr><th>Verft</th><td><?= h($verftLabel !== '' ? $verftLabel : ('ID ' . (int)$verftId)) ?></td></tr>
        <?php endif; ?>
      </tbody></table>
    </div>
    <table class="table print-table"><thead>
      <?php if ($section==='verft'): ?>
        <tr><th>Verft</th><th>Sted</th><th>Nasjon</th></tr>
      <?php elseif ($section==='leveranse'): ?>
        <tr><th>Byggenr</th><th>År/mnd</th><th>Navn</th><th>Reg.havn</th><th>Rederi</th><th>Type</th></tr>
      <?php else: ?>
        <tr><th>Byggenr</th><th>År/mnd</th><th>Navn</th><th>Reg.havn</th><th>Rederi</th><th>Objekt</th></tr>
      <?php endif; ?>
    </thead><tbody>
      <?php if (!$rows): ?><tr><td colspan="6">Ingen data.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php if ($section==='verft'): ?>
          <tr><td><?= h($r['VerftNavn']??'') ?></td><td><?= h($r['Sted']??'') ?></td><td><?= h($r['Nasjon']??'') ?></td></tr>
        <?php elseif ($section==='leveranse'): ?>
          <?php $ym=($r['YearSpes']?(int)$r['YearSpes']:0); $ym.=($r['MndSpes']? '/'.str_pad((string)(int)$r['MndSpes'],2,'0',STR_PAD_LEFT):''); $navn=trim((string)($r['TypeFork']??'')); $navn=($navn!==''?$navn.' ':'').(string)($r['FartNavn']??''); ?>
          <tr><td><?= h($r['Byggenr']??'') ?></td><td><?= h($ym) ?></td><td><?= h($navn) ?></td><td><?= h($r['RegHavn']??'') ?></td><td><?= h($r['Rederi']??'') ?></td><td><?= h($r['TypeFork']??'') ?></td></tr>
        <?php else: ?>
          <?php $ym=($r['YearSpes']?(int)$r['YearSpes']:0); $ym.=($r['MndSpes']? '/'.str_pad((string)(int)$r['MndSpes'],2,'0',STR_PAD_LEFT):''); $navn=trim((string)($r['TypeFork']??'')); $navn=($navn!==''?$navn.' ':'').(string)($r['FartNavn']??''); ?>
          <tr><td><?= h($r['Byggenr']??'') ?></td><td><?= h($ym) ?></td><td><?= h($navn) ?></td><td><?= h($r['RegHavn']??'') ?></td><td><?= h($r['Rederi']??'') ?></td><td><?= isset($r['Objekt'])&&(int)$r['Objekt']===1?'1':'0' ?></td></tr>
        <?php endif; ?>
      <?php endforeach; endif; ?>
    </tbody></table>
  </div>
  <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),200));</script>
</body></html>
