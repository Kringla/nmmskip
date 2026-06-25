<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$obj_id  = isset($_GET['obj_id']) ? (int)$_GET['obj_id'] : 0;
$tid_id  = isset($_GET['tid_id']) ? (int)$_GET['tid_id'] : 0;
$orient  = isset($_GET['orient']) ? strtoupper((string)$_GET['orient']) : 'P';
$bodyClass = ($orient === 'L') ? 'print-landscape' : 'print-portrait';

/** @var mysqli $conn */
$db = $conn ?? ($mysqli ?? null);
if (!$db) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler'; exit; }
if ($obj_id <= 0 || $tid_id <= 0) { http_response_code(400); header('Content-Type: text/plain; charset=UTF-8'); echo 'Mangler parametre'; exit; }

$stmt = $db->prepare("SELECT t.*, zft.TypeFork, zn.Nasjon AS NasjonNavn FROM tblfarttid t LEFT JOIN tblzfarttype zft ON zft.FartType_ID = t.FartType_ID LEFT JOIN tblznasjon zn ON zn.Nasjon_ID = t.Nasjon_ID WHERE t.FartTid_ID = ? LIMIT 1");
$stmt->bind_param('i',$tid_id); $stmt->execute(); $res=$stmt->get_result(); $main = $res?$res->fetch_assoc():null; $stmt->close();
if (!$main) { http_response_code(404); header('Content-Type:text/plain; charset=UTF-8'); echo 'Fant ingen detalj.'; exit; }

$typeFork = trim((string)($main['TypeFork'] ?? ''));
if ($typeFork === '') {
  $stmt = $db->prepare("SELECT zft.TypeFork FROM tblfarttid t LEFT JOIN tblfartspes fs ON fs.FartSpes_ID = t.FartSpes_ID LEFT JOIN tblzfarttype zft ON zft.FartType_ID = fs.FartType_ID WHERE t.FartTid_ID = ? LIMIT 1");
  $stmt->bind_param('i',$tid_id); $stmt->execute(); $res2=$stmt->get_result(); if ($res2){ $r=$res2->fetch_assoc(); if ($r) $typeFork = trim((string)$r['TypeFork']); } $stmt->close();
}

$objRow=null; $stmt=$db->prepare("SELECT o.*, CONCAT_WS(', ', v1.VerftNavn, v1.Sted) AS LeverandorNavn, CONCAT_WS(', ', v2.VerftNavn, v2.Sted) AS SkrogbyggerNavn, zs.Strok AS StroketNavn FROM tblfartobj o LEFT JOIN tblverft v1 ON v1.Verft_ID=o.LeverID LEFT JOIN tblverft v2 ON v2.Verft_ID=o.SkrogID LEFT JOIN tblzstroket zs ON zs.Stroket_ID=o.StroketID WHERE o.FartObj_ID = ? LIMIT 1");
$stmt->bind_param('i',$obj_id); $stmt->execute(); $res3=$stmt->get_result(); if($res3){$objRow=$res3->fetch_assoc();} $stmt->close();

$mm = (int)($main['MndTid'] ?? 0); $mmS = $mm > 0 ? str_pad((string)$mm,2,'0',STR_PAD_LEFT) : '00';
$tidStr = (string)($main['YearTid'] ?? '') . '/' . $mmS;
$navn    = (string)($main['FartNavn'] ?? '(ukjent navn)');
$visNavn = $typeFork !== '' ? trim($typeFork . ' ' . $navn) : $navn;
$ts = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="no"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($visNavn) ?></title>
  <link rel="stylesheet" href="<?= h(rtrim(BASE_URL,'/')) ?>/assets/css/app.css">
</head>
<body class="print-page <?= h($bodyClass) ?>">
  <div class="print-controls no-print">
    <a class="btn" href="#" onclick="window.print(); return false;">Skriv ut</a>
    <a class="btn" href="#" onclick="history.back(); return false;">Tilbake</a>
  </div>
  <div class="print-container">
    <h1 class="print-title"><?= h($visNavn) ?></h1>
    <div class="print-meta">Generert: <?= h($ts) ?></div>
    <h3>Objektinformasjon</h3>
    <table class="table print-table"><tbody>
      <?php if (!empty($objRow['Bygget'])): ?><tr><th>Bygget (år)</th><td><?= h($objRow['Bygget']) ?></td></tr><?php endif; ?>
      <tr><th>Navn gitt ved bygging</th><td><?= h($objRow['NavnObj'] ?? '') ?></td></tr>
      <?php if ($typeFork !== ''): ?><tr><th>Fartøytype</th><td><?= h($typeFork) ?></td></tr><?php endif; ?>
      <?php if (!empty($objRow['IMO'])): ?><tr><th>IMO</th><td><?= h($objRow['IMO']) ?></td></tr><?php endif; ?>
      <?php if (!empty($objRow['Kontrahert'])): ?><tr><th>Kontrahert</th><td><?= h($objRow['Kontrahert']) ?></td></tr><?php endif; ?>
      <?php if (!empty($objRow['Kjolstrukket'])): ?><tr><th>Kjolstrukket</th><td><?= h($objRow['Kjolstrukket']) ?></td></tr><?php endif; ?>
      <?php if (!empty($objRow['Sjosatt'])): ?><tr><th>Sjosatt</th><td><?= h($objRow['Sjosatt']) ?></td></tr><?php endif; ?>
      <?php if (!empty($objRow['Levert'])): ?><tr><th>Levert</th><td><?= h($objRow['Levert']) ?></td></tr><?php endif; ?>
      <?php $levNavn = trim((string)($objRow['LeverandorNavn'] ?? '')); $levID = trim((string)($objRow['LeverID'] ?? '')); if ($levNavn!=='' || $levID!==''): ?><tr><th>Leverandør</th><td><?= h($levNavn!==''?$levNavn:('ID: '.$levID)) ?></td></tr><?php endif; ?>
      <?php if (!empty($objRow['ByggeNr'])): ?><tr><th>Byggenr</th><td><?= h($objRow['ByggeNr']) ?></td></tr><?php endif; ?>
    </tbody></table>
    <h3>Navneoppføring</h3>
    <table class="table print-table"><tbody>
      <tr><th>Navn</th><td><?= h($visNavn) ?></td></tr>
      <tr><th>Tidspunkt</th><td><?= h($tidStr) ?></td></tr>
      <?php if (!empty($main['NasjonNavn'])): ?><tr><th>Nasjon</th><td><?= h($main['NasjonNavn']) ?></td></tr><?php endif; ?>
      <?php if (!empty($main['RegHavn'])): ?><tr><th>Reg.havn</th><td><?= h($main['RegHavn']) ?></td></tr><?php endif; ?>
      <?php if (!empty($main['Rederi'])): ?><tr><th>Rederi</th><td><?= h($main['Rederi']) ?></td></tr><?php endif; ?>
      <?php if (!empty($main['Kallesignal'])): ?><tr><th>Kallesignal</th><td><?= h($main['Kallesignal']) ?></td></tr><?php endif; ?>
      <?php if (!empty($main['PennantTiln'])): ?><tr><th>Tilnavn/Pennant nr</th><td><?= h($main['PennantTiln']) ?></td></tr><?php endif; ?>
      <?php if (!empty($main['MMSI'])): ?><tr><th>MMSI</th><td><?= h($main['MMSI']) ?></td></tr><?php endif; ?>
      <?php if (!empty($main['Fiskerinr'])): ?><tr><th>Fiskerinr</th><td><?= h($main['Fiskerinr']) ?></td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),200));</script>
</body></html>
