<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$regHavn  = isset($_GET['reghavn']) ? trim((string)$_GET['reghavn']) : '';
$nasjonIdParam = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0;
$orient = isset($_GET['orient']) ? strtoupper((string)$_GET['orient']) : 'P';
$bodyClass = ($orient === 'L') ? 'print-landscape' : 'print-portrait';

$dbh = $db ?? ($mysqli ?? ($conn ?? null));
if (!$dbh) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler.'; exit; }

$rows = [];
$sql = "
  SELECT ft.FartNavn, ft.YearTid, ty.FartType, ft.RegHavn, ns.Nasjon, ft.Kallesignal, ft.MMSI, ft.PennantTiln
  FROM tblfarttid ft
  LEFT JOIN tblzfarttype ty ON ty.FartType_ID = ft.FartType_ID
  LEFT JOIN tblznasjon   ns ON ns.Nasjon_ID   = ft.Nasjon_ID
  WHERE 1
    AND (? = '' OR ft.FartNavn LIKE ?)
    AND (? = '' OR ft.RegHavn  LIKE ?)
    AND (? = 0  OR ft.Nasjon_ID = ?)
  ORDER BY ft.FartNavn ASC, ft.YearTid ASC
  LIMIT 200";
$stmt = $dbh->prepare($sql);
$likeQ = '%' . $q . '%'; $likeR = '%' . $regHavn . '%';
$stmt->bind_param('ssssii', $q, $likeQ, $regHavn, $likeR, $nasjonIdParam, $nasjonIdParam);
$stmt->execute(); $res = $stmt->get_result();
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r; $stmt->close();

$title = 'Fartøysnavn – søkeresultat'; $ts = date('Y-m-d H:i');
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
      <?php
        $nasjonLabel = 'Alle';
        if ($nasjonIdParam > 0) {
          $ns = $dbh->prepare('SELECT Nasjon FROM tblznasjon WHERE Nasjon_ID = ? LIMIT 1');
          $ns->bind_param('i',$nasjonIdParam); $ns->execute(); $nres=$ns->get_result();
          if ($nres && ($nr=$nres->fetch_assoc())) { $nasjonLabel = (string)$nr['Nasjon']; }
          $ns->close();
        }
      ?>
      | Kriterier: Navn=<?= h($q) ?>, Reg.havn=<?= h($regHavn) ?>, Nasjon=<?= h($nasjonLabel) ?>
    </div>
    <div class="print-criteria" style="margin: 4mm 0;">
      <table class="print-table">
        <tbody>
          <tr><th>Navn</th><td><?= h($q) ?></td></tr>
          <tr><th>Reg.havn</th><td><?= h($regHavn) ?></td></tr>
          <tr><th>Nasjon</th><td><?= h($nasjonLabel) ?></td></tr>
        </tbody>
      </table>
    </div>
    <table class="table print-table"><thead>
      <tr><th>Navn</th><th>År</th><th>Type</th><th>Reg.havn</th><th>Nasjon</th><th>Kallesignal</th><th>MMSI</th><th>Pennant/Tilnavn</th></tr>
    </thead><tbody>
      <?php if (!$rows): ?><tr><td colspan="8">Ingen treff.</td></tr>
      <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['FartNavn'] ?? '') ?></td>
        <td><?= h($r['YearTid'] ?? '') ?></td>
        <td><?= h($r['FartType'] ?? '') ?></td>
        <td><?= h($r['RegHavn'] ?? '') ?></td>
        <td><?= h($r['Nasjon'] ?? '') ?></td>
        <td><?= h($r['Kallesignal'] ?? '') ?></td>
        <td><?= h($r['MMSI'] ?? '') ?></td>
        <td><?= h($r['PennantTiln'] ?? '') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody></table>
  </div>
  <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),200));</script>
</body></html>
