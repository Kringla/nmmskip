<?php
/**
 * /user/fart_spes.php
 * Viser tekniske spesifikasjoner for et fartøy (tblFartSpes) basert på ?spes_id=...
 */

require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }
require_once __DIR__ . '/../includes/auth.php'; // for meny/rolle

// === Konfig: grupper og rekkefølge ===
$GROUPS = [
  '-' => [
    'Aarmnd'     => ['label' => 'År/mnd for spes'],
    'ObjektBool' => ['label' => 'Objekt?'],
  ],
  'Hovedspesifikasjon' => [
    'Lengde'      => ['label' => 'Lengde (fot)'],
    'Bredde'      => ['label' => 'Bredde (fot)'],
    'Dypg'        => ['label' => 'Dypgående (fot)'],
    'TonnasjeFmt' => ['label' => 'Tonnasje'],
    'DrektFmt'    => ['label' => 'Drektighet'],
    'MaxFart'     => ['label' => 'Maks fart (knop)'],
  ],
  'Bygg & skrog' => [
    'Byggeverft' => ['label' => 'Byggeverft'],
    'Byggenr'    => ['label' => 'Byggenr'],
    'Skrogverft' => ['label' => 'Skrog verft'],
    'BnrSkrog'   => ['label' => 'Byggenr for verft'],
    'Materiale'  => ['label' => 'Materiale'],
    'Skrogtype'  => ['label' => 'Skrogtype'],
    'RiggDetalj' => ['label' => 'Rigg'],
    'KlasseNavn' => ['label' => 'Klasse'],
    'Fartklasse' => ['label' => 'Klassedetalj'],
  ],
  'Funksjon' => [
    'Funksjon'   => ['label' => 'Funksjon'],
    'FunkDetalj' => ['label' => 'Funksjonsbeskrivelse'],
    'Kapasitet'  => ['label' => 'Kapasitet'],
  ],
  'Fremdrift' => [
    'DriftMiddel' => ['label' => 'Fremdriftsmiddel'],
    'Motortype'   => ['label' => 'Motortype'],
    'MotorDetalj' => ['label' => 'Motordetalj'],
    'MotorEff'    => ['label' => 'Effekt (BHK)'],
  ],
];

// helpers come from includes/functions.php
function nonempty($v){ return isset($v) && $v !== '' && $v !== null; }

// Parametre
$spesId = isset($_GET['spes_id']) ? (int)$_GET['spes_id'] : 0;
if ($spesId <= 0) {
  http_response_code(400);
  echo "<p>Mangler eller ugyldig parameter: spes_id må være &gt; 0.</p>";
  exit;
}

// Hent spes + oppslag + (seneste) navn
$sql = "
SELECT
  fs.FartSpes_ID,
  fs.FartObj_ID,
  fs.YearSpes,
  fs.MndSpes,
  fs.Byggenr,
  fo.BnrSkrog AS BnrSkrog,
  fs.Kapasitet,
  fs.MotorDetalj           AS MotorDetalj_free,   -- fritekst
  fs.MotorEff,
  fs.MaxFart,
  fs.Lengde,
  fs.Bredde,
  fs.Dypg,
  fs.Tonnasje,
  fs.Drektigh,
  fs.DrektEnh_ID,                                  -- FK til kodeliste
  fs.Objekt,
  fs.FartType_ID           AS FartTypeSpes_ID,
  fs.FunkDetalj,
  fs.FartKlasse_ID,
  CONCAT_WS(', ', vb.VerftNavn, vb.Sted) AS Byggeverft,
  CONCAT_WS(', ', vs.VerftNavn, vs.Sted) AS Skrogverft,
  zmat.MatFork             AS Materiale,
  zskrog.TypeSkrog         AS Skrogtype,
  zd.DriftMiddel,
  zm.MotorDetalj           AS Motortype,          -- kodebeskrivelse
  zr.RiggDetalj            AS RiggDetalj,         -- kodebeskrivelse
  zf.TypeFunksjon          AS Funksjon,
  zk.KlasseNavn            AS KlasseNavn,
  zt.TonnFork              AS TonnFork,           -- enhet fra tblzTonnEnh
  zde.DrektFork            AS DrektFork,          -- enhet fra tblzDrektEnh (riktig kolonnenavn)
  tn.FartNavn,
  t.TypeFork               AS TypeFork
FROM tblfartspes fs
LEFT JOIN tblfartobj      fo     ON fo.FartObj_ID       = fs.FartObj_ID
LEFT JOIN tblverft        vb     ON vb.Verft_ID         = fs.Verft_ID
LEFT JOIN tblverft        vs     ON vs.Verft_ID         = fo.SkrogID
LEFT JOIN tblzfartmat     zmat   ON zmat.FartMat_ID     = fs.FartMat_ID
LEFT JOIN tblzfartskrog   zskrog ON zskrog.FartSkrog_ID = fs.FartSkrog_ID
LEFT JOIN tblzfartdrift   zd     ON zd.FartDrift_ID     = fs.FartDrift_ID
LEFT JOIN tblzfartmotor   zm     ON zm.FartMotor_ID     = fs.FartMotor_ID
LEFT JOIN tblzfartrigg    zr     ON zr.FartRigg_ID      = fs.FartRigg_ID
LEFT JOIN tblzfartfunk    zf     ON zf.FartFunk_ID      = fs.FartFunk_ID
LEFT JOIN tblzfartklasse  zk     ON zk.FartKlasse_ID    = fs.FartKlasse_ID
LEFT JOIN tblztonnenh     zt     ON zt.TonnEnh_ID       = fs.TonnEnh_ID
LEFT JOIN tblzdrektenh    zde    ON zde.DrektEnh_ID     = fs.DrektEnh_ID   -- **viktig: riktig tabell/kolonne**
LEFT JOIN (
  SELECT t2.FartObj_ID, t2.FartNavn, t2.FartType_ID
  FROM tblfarttid t2
  INNER JOIN (
    SELECT FartObj_ID, MAX(FartTid_ID) AS max_id
    FROM tblfarttid
    GROUP BY FartObj_ID
  ) m2 ON m2.FartObj_ID = t2.FartObj_ID AND t2.FartTid_ID = m2.max_id
) tn ON tn.FartObj_ID = fs.FartObj_ID
LEFT JOIN tblzfarttype t ON t.FartType_ID = COALESCE(fs.FartType_ID, tn.FartType_ID)
WHERE fs.FartSpes_ID = ?
LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(500); echo "<p>DB-feil (prepare): ".h($conn->error)."</p>"; exit; }
$stmt->bind_param('i', $spesId);
$stmt->execute();
$res  = $stmt->get_result();
$row  = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
  echo "<p>Fant ingen spesifikasjoner for spes_id " . h($spesId) . ".</p>";
  exit;
}

// --- Bilde basert på seneste FartTid_ID for objektet (ta første forekomst) ---
$imageSrc   = '/assets/img/skip/placeholder.jpg';
$latestTidId = 0;
$fid = (int)($row['FartObj_ID'] ?? 0);
if ($fid > 0) {
  if ($stmt = $conn->prepare("SELECT MAX(FartTid_ID) AS max_id FROM tblfarttid WHERE FartObj_ID = ?")) {
    $stmt->bind_param('i', $fid);
    $stmt->execute();
    if ($resN = $stmt->get_result()) {
      if ($tidRow = $resN->fetch_assoc()) { $latestTidId = (int)($tidRow['max_id'] ?? 0); }
      $resN->free();
    }
    $stmt->close();
  }
}
// Bildeoppslag fjernet (tblxnmmfoto er utgått)
$imgRel = (substr($imageSrc, 0, 1) === '/') ? ('..' . $imageSrc) : $imageSrc;

// --- Bygg visningsdata ---
$data = [];
$data['FartSpes_ID'] = $row['FartSpes_ID'] ?? null;
$data['FartObj_ID']  = $row['FartObj_ID']  ?? null;

// Topptekst: TypeFork + FartNavn
$typeFork = isset($row['TypeFork']) ? trim((string)$row['TypeFork']) : '';
$navn     = isset($row['FartNavn']) ? trim((string)$row['FartNavn']) : '';
$topLine  = ($typeFork !== '') ? trim($typeFork . ' ' . $navn) : $navn;

// Bygg $data basert på $GROUPS og $row
foreach ($GROUPS as $section => $fields) {
  foreach ($fields as $key => $_cfg) {
    // Avledede/syntetiske felter
    if ($key === 'TonnasjeFmt') {
      $val  = $row['Tonnasje'] ?? null;
      $enh  = trim((string)($row['TonnFork'] ?? ''));  // fra tblzTonnEnh
      $data['TonnasjeFmt'] = ($val !== null && $val !== '') ? h($val) . ($enh !== '' ? ' ' . h($enh) : '') : '';
      continue;
    }
    if ($key === 'DrektFmt') {
      $val  = $row['Drektigh'] ?? null;
      $enh  = trim((string)($row['DrektFork'] ?? '')); // fra tblzDrektEnh
      $data['DrektFmt'] = ($val !== null && $val !== '') ? h($val) . ($enh !== '' ? ' ' . h($enh) : '') : '';
      continue;
    }
    if ($key === 'ObjektBool') {
      $obj = $row['Objekt'] ?? null;
      $data['ObjektBool'] = ($obj && $obj !== '0') ? 'Ja' : 'Nei';
      continue;
    }
    if ($key === 'Aarmnd') {
      $y = (int)($row['YearSpes'] ?? 0);
      $m = (int)($row['MndSpes']  ?? 0);
      $data['Aarmnd'] = ($y > 0) ? sprintf('%04d-%02d', $y, max(0, min(12, $m))) : '';
      continue;
    }

    // Fri-tekstfelt i CR
    if ($key === 'MotorDetalj') { $data['MotorDetalj'] = $row['MotorDetalj_free'] ?? ''; continue; }

    // Standard: direkte fra $row
    $data[$key] = $row[$key] ?? '';
  }
}
?>
<?php $page_class = 'page-fart-spes'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<!-- Bilde øverst, objekt-tilpasset, uten cropping -->
<div class="container" style="display:flex; justify-content:center;">
  <div class="image-box">
    <?php $altText = ($topLine !== '') ? $topLine : 'Fartøybilde'; ?>
    <img src="<?= h($imgRel) ?>" alt="<?= h($altText) ?>">
  </div>
</div>

<div class="spec-wrap">
  <div class="spec-head">
    <h1>Fartøysspesifikasjoner</h1>
    <h2 class="sub"><?= h($topLine) ?></h2>
  </div>

  <div class="spec-id">Spes ID: <?= (int)$data['FartSpes_ID'] ?></div>

  <div class="spec-grid">
    <?php foreach ($GROUPS as $groupTitle => $fields): ?>
      <div class="spec-group"><?= h($groupTitle) ?></div>
      <?php foreach ($fields as $key => $cfg): ?>
        <?php $val = $data[$key] ?? ''; if (!nonempty($val)) continue; ?>
        <div class="spec-row">
          <div class="label"><?= h($cfg['label']) ?></div>
          <div class="value"><?= h($val) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>

<!-- Tilbake-knapp nederst -->
<div class="actions" style="margin:1rem 0 2rem; text-align:center;">
  <a class="mdc-button mdc-button--raised btn" href="#" onclick="if(history.length>1){history.back();return false;}" title="Tilbake"><span class="mdc-button__ripple"></span><span class="mdc-button__label">← Tilbake</span></a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
