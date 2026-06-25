<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$driftId = isset($_GET['fartdrift_id']) ? (int)$_GET['fartdrift_id'] : 0;
$skrogId = isset($_GET['fartskrog_id']) ? (int)$_GET['fartskrog_id'] : 0;
$funkId  = isset($_GET['fartfunk_id'])  ? (int)$_GET['fartfunk_id']  : 0;
$orient  = isset($_GET['orient']) ? strtoupper((string)$_GET['orient']) : 'P';
$bodyClass = ($orient === 'L') ? 'print-landscape' : 'print-portrait';

/** @var mysqli $mysqli */
$db = $mysqli ?? ($conn ?? null);
if (!$db) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler'; exit; }

$where = [];$types='';$vals=[];
if ($driftId > 0) { $where[]='s.FartDrift_ID = ?'; $types.='i'; $vals[]=$driftId; }
if ($skrogId > 0) { $where[]='s.FartSkrog_ID = ?'; $types.='i'; $vals[]=$skrogId; }
if ($funkId  > 0) { $where[]='s.FartFunk_ID  = ?'; $types.='i'; $vals[]=$funkId; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = [];
$sql = "
  SELECT t.FartNavn, COALESCE(s.YearSpes, t.YearTid) AS YearShow, ft.FartType,
         s.Materiale, s.Lengde, s.Bredde, s.Dypg, s.Tonnasje, te.TonnFork, s.Drektigh, de.DrektFork
  FROM tblfartspes s
  JOIN tblfarttid t ON t.FartSpes_ID = s.FartSpes_ID
  LEFT JOIN tblzfarttype ft ON t.FartType_ID = ft.FartType_ID
  LEFT JOIN tblztonnenh  te ON s.TonnEnh_ID  = te.TonnEnh_ID
  LEFT JOIN tblzdrektenh de ON s.DrektEnh_ID = de.DrektEnh_ID
  $whereSql
  GROUP BY t.FartNavn, YearShow, ft.FartType, s.Materiale, s.Lengde, s.Bredde, s.Dypg, s.Tonnasje, te.TonnFork, s.Drektigh, de.DrektFork
  ORDER BY t.FartNavn, YearShow
  LIMIT 2000";
$stmt = $db->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$vals); }
$stmt->execute(); $res = $stmt->get_result();
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r; $stmt->close();

$title = 'Fartøy – søk på spesifikasjoner'; $ts = date('Y-m-d H:i');

// Resolve criteria labels for print header
$driftLabel = 'Alle';
$skrogLabel = 'Alle';
$funkLabel  = 'Alle';
if ($driftId > 0) {
  $st = $db->prepare('SELECT DriftMiddel FROM tblzfartdrift WHERE FartDrift_ID = ? LIMIT 1');
  $st->bind_param('i', $driftId); $st->execute(); $r=$st->get_result();
  if ($r && ($row=$r->fetch_assoc())) { $driftLabel = (string)$row['DriftMiddel']; } else { $driftLabel = 'ID ' . (int)$driftId; }
  $st->close();
}
if ($skrogId > 0) {
  $st = $db->prepare('SELECT TypeSkrog FROM tblzfartskrog WHERE FartSkrog_ID = ? LIMIT 1');
  $st->bind_param('i', $skrogId); $st->execute(); $r=$st->get_result();
  if ($r && ($row=$r->fetch_assoc())) { $skrogLabel = (string)$row['TypeSkrog']; } else { $skrogLabel = 'ID ' . (int)$skrogId; }
  $st->close();
}
if ($funkId > 0) {
  $st = $db->prepare('SELECT TypeFunksjon FROM tblzfartfunk WHERE FartFunk_ID = ? LIMIT 1');
  $st->bind_param('i', $funkId); $st->execute(); $r=$st->get_result();
  if ($r && ($row=$r->fetch_assoc())) { $funkLabel = (string)$row['TypeFunksjon']; } else { $funkLabel = 'ID ' . (int)$funkId; }
  $st->close();
}
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
    <div class="print-meta">Generert: <?= h($ts) ?> | Kriterier: Drift=<?= h($driftLabel) ?>, Skrog=<?= h($skrogLabel) ?>, Funksjon=<?= h($funkLabel) ?></div>
    <div class="print-criteria" style="margin: 4mm 0;">
      <table class="print-table"><tbody>
        <tr><th>Drift</th><td><?= h($driftLabel) ?></td></tr>
        <tr><th>Skrog</th><td><?= h($skrogLabel) ?></td></tr>
        <tr><th>Funksjon</th><td><?= h($funkLabel) ?></td></tr>
      </tbody></table>
    </div>
    <table class="table print-table"><thead>
      <tr><th>Navn</th><th>År</th><th>Type</th><th>Materiale</th><th>Dimensjoner (LxBxD)</th><th>Tonnasje</th><th>Drektighet</th></tr>
    </thead><tbody>
      <?php if (!$rows): ?><tr><td colspan="7">Ingen treff.</td></tr>
      <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['FartNavn'] ?? '') ?></td>
        <td><?= h($r['YearShow'] ?? '') ?></td>
        <td><?= h($r['FartType'] ?? '') ?></td>
        <td><?= h($r['Materiale'] ?? '') ?></td>
        <td><?php $dims=[]; if(!empty($r['Lengde']))$dims[]=h($r['Lengde']).' m'; if(!empty($r['Bredde']))$dims[]=h($r['Bredde']).' m'; if(!empty($r['Dypg']))$dims[]=h($r['Dypg']).' m'; echo $dims?implode(' x ',$dims):''; ?></td>
        <td><?php $tonn=trim((string)($r['Tonnasje']??'')); $tf=trim((string)($r['TonnFork']??'')); echo h(trim($tonn.($tf?' '.$tf:''))); ?></td>
        <td><?php $dr=trim((string)($r['Drektigh']??'')); $df=trim((string)($r['DrektFork']??'')); echo h(trim($dr.($df?' '.$df:''))); ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody></table>
  </div>
  <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),200));</script>
</body></html>
