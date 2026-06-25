<?php
// admin/fart_nyspes.php
// Prosess 2: Endring i spesifikasjoner (ny rad i tblfartspes + ny rad i tblfarttid)
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$conn = db_rw();

// uses global h() from includes/functions.php
function val($arr,$k,$d=''){ return isset($arr[$k]) ? $arr[$k] : $d; }

if (!function_exists('stmt_infer_types')) {
    /**
     * Bygger en type-streng for mysqli::bind_param basert på PHP-verdier.
     */
    function stmt_infer_types(array $params): string {
        $types = '';
        foreach ($params as $value) {
            if (is_int($value) || is_bool($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_null($value)) {
                $types .= 's';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
}

if (!function_exists('stmt_bind_params')) {
    /**
     * Binder parametre til en mysqli_stmt og håndterer referanser automatisk.
     *
     * @param mysqli_stmt $stmt
     * @param array       $params Verdier som skal bindes (sendes som referanser)
     * @param string|null $types  Valgfri type-streng (auto-infer hvis null)
     */
    function stmt_bind_params(mysqli_stmt $stmt, array &$params, ?string $types = null): void {
        $types = $types ?? stmt_infer_types($params);
        $refs = [$types];
        foreach ($params as $key => &$value) {
            $refs[] = &$value;
        }
        unset($value);
        $stmt->bind_param(...$refs);
    }
}

// Helpers for robust lookups (handle column name differences across environments)
function sw_lookup_cols(mysqli $conn, string $table): array {
    $cols = [];
    $safe = preg_replace('/[^a-z0-9_]/i','',$table);
    if ($res = $conn->query("SHOW COLUMNS FROM `{$safe}`")) {
        while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }
        $res->free();
    }
    return $cols;
}
function sw_lookup(mysqli $conn, string $table, string $idField, array $nameCandidates): array {
    $cols = sw_lookup_cols($conn, $table);
    $nameCol = null;
    foreach ($nameCandidates as $cand) { if (in_array($cand, $cols, true)) { $nameCol = $cand; break; } }
    if ($nameCol === null) { // fallback: first non-PK text-ish candidate
        $nameCol = $cols[1] ?? $idField;
    }
    $safeTable = preg_replace('/[^a-z0-9_]/i','',$table);
    $safeId    = preg_replace('/[^a-z0-9_]/i','',$idField);
    $safeName  = preg_replace('/[^a-z0-9_]/i','',$nameCol);
    $sql = "SELECT `{$safeId}` AS id, `{$safeName}` AS name FROM `{$safeTable}` ORDER BY name";
    $rows = [];
    if ($res = $conn->query($sql)) { while ($row = $res->fetch_assoc()) { $rows[] = $row; } $res->free(); }
    return $rows;
}

// Hent referanselister
$types = sw_lookup($conn, 'tblzfarttype', 'FartType_ID', ['FartType','TypeFork']);
$nasjoner = [];
if ($res = $conn->query("SELECT Nasjon_ID AS id, Nasjon AS name FROM tblznasjon WHERE Nasjon <> '' ORDER BY name")) {
    while ($row = $res->fetch_assoc()) $nasjoner[] = $row;
    $res->free();
}

// Verft for edit-steg
$verft = [];
if ($res = $conn->query("SELECT Verft_ID AS id, CONCAT_WS(', ', VerftNavn, Sted) AS name FROM tblverft ORDER BY name")) {
    while ($row = $res->fetch_assoc()) $verft[] = $row;
    $res->free();
}

// Lookups for spesifikasjoner
$fartTyper    = sw_lookup($conn, 'tblzfarttype',  'FartType_ID',  ['FartType','TypeFork']);
$fartMat      = sw_lookup($conn, 'tblzfartmat',   'FartMat_ID',   ["CONCAT_WS(' - ', MatFork, Materiale)", 'MatFork']);
$fartFunk     = sw_lookup($conn, 'tblzfartfunk',  'FartFunk_ID',  ['TypeFunksjon']);
$fartSkrog    = sw_lookup($conn, 'tblzfartskrog', 'FartSkrog_ID', ['TypeSkrog']);
$fartDrift    = sw_lookup($conn, 'tblzfartdrift', 'FartDrift_ID', ['DriftMiddel']);
$fartKlasse   = sw_lookup($conn, 'tblzfartklasse','FartKlasse_ID',['KlasseNavn','TypeKlasse']);
$fartRigg     = sw_lookup($conn, 'tblzfartrigg',  'FartRigg_ID',  ['RiggDetalj','RiggFork']);
$fartMotor    = sw_lookup($conn, 'tblzfartmotor', 'FartMotor_ID', ['MotorDetalj','MotorFork']);
$tonnEnheter  = sw_lookup($conn, 'tblztonnenh',   'TonnEnh_ID',   ['TonnFork','TonnDetalj']);
$drektEnheter = sw_lookup($conn, 'tblzdrektenh',  'DrektEnh_ID',  ['DrektFork','DrektDetalj']);

$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

// Request params
$selType = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
$qNavn   = isset($_GET['navn']) ? trim((string)$_GET['navn']) : '';
$qYear   = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$qReg    = isset($_GET['reghavn']) ? trim((string)$_GET['reghavn']) : '';
$qNat    = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0;
$tidId   = isset($_GET['tid_id']) ? (int)$_GET['tid_id'] : 0;

// POST: lagre ny spes + ny tidsrad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_spec_change') {
    $tidId = (int)($_POST['tid_id'] ?? 0);
    if ($tidId <= 0) {
        $err = 'Mangler valgt fartøy.';
    } else {
        // Hent nåværende tidsrad og objekt
        $stmt = $conn->prepare('SELECT * FROM tblfarttid WHERE FartTid_ID = ?');
        $stmt->bind_param('i', $tidId);
        $stmt->execute();
        $res = $stmt->get_result();
        $tidRow = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $stmt->close();

        if (!$tidRow) {
            $err = 'Fant ikke valgt tidsrad.';
        } else {
            $objId = (int)$tidRow['FartObj_ID'];

            // Finn siste spesifikasjon for arv
            $lastSp = null;
            $stmtSp = $conn->prepare('SELECT * FROM tblfartspes WHERE FartObj_ID = ? ORDER BY COALESCE(YearSpes,0) DESC, COALESCE(MndSpes,0) DESC, FartSpes_ID DESC LIMIT 1');
            $stmtSp->bind_param('i', $objId);
            $stmtSp->execute();
            $resSp = $stmtSp->get_result();
            if ($resSp) { $lastSp = $resSp->fetch_assoc(); $resSp->free(); }
            $stmtSp->close();

            $yearSpes = isset($_POST['YearSpes']) && $_POST['YearSpes'] !== '' ? (int)$_POST['YearSpes'] : (isset($lastSp['YearSpes']) ? (int)$lastSp['YearSpes'] : null);
            $mndSpes  = isset($_POST['MndSpes']) && $_POST['MndSpes'] !== '' ? (int)$_POST['MndSpes'] : (isset($lastSp['MndSpes']) ? (int)$lastSp['MndSpes'] : null);
            $verftId  = isset($_POST['Verft_ID']) && $_POST['Verft_ID'] !== '' ? (int)$_POST['Verft_ID'] : (isset($lastSp['Verft_ID']) ? (int)$lastSp['Verft_ID'] : null);
            $objektF  = 0; // Ombygging: alltid Objekt=0 per SkipsWebLiv.md

            $intFields = ['FartMat_ID','FartType_ID','FartFunk_ID','FartSkrog_ID','FartDrift_ID','FartKlasse_ID','FartRigg_ID','FartMotor_ID','TonnEnh_ID','DrektEnh_ID'];
            $numericFields = ['MaxFart','Lengde','Bredde','Dypg'];
            $specDefaults = [
                'Byggenr'     => '',
                'FartMat_ID'  => null,
                'FartType_ID' => null,
                'FartFunk_ID' => null,
                'FartSkrog_ID'=> null,
                'FartDrift_ID'=> null,
                'FunkDetalj'  => '',
                'TeknDetalj'  => '',
                'FartKlasse_ID' => null,
                'Kapasitet'   => '',
                'FartRigg_ID' => null,
                'FartMotor_ID'=> null,
                'MotorDetalj' => '',
                'MotorEff'    => '',
                'MaxFart'     => null,
                'Lengde'      => null,
                'Bredde'      => null,
                'Dypg'        => null,
                'Tonnasje'    => '',
                'TonnEnh_ID'  => null,
                'Drektigh'    => '',
                'DrektEnh_ID' => null,
            ];

            $vals = [];
            foreach ($specDefaults as $field => $default) {
                if (in_array($field, $intFields, true)) {
                    if (isset($_POST[$field]) && $_POST[$field] !== '') {
                        $vals[$field] = (int)$_POST[$field];
                    } elseif (isset($lastSp[$field]) && $lastSp[$field] !== '') {
                        $vals[$field] = (int)$lastSp[$field];
                    } else {
                        $vals[$field] = null;
                    }
                } elseif (in_array($field, $numericFields, true)) {
                    if (isset($_POST[$field]) && $_POST[$field] !== '') {
                        $vals[$field] = (int)$_POST[$field];
                    } elseif (isset($lastSp[$field]) && $lastSp[$field] !== '') {
                        $vals[$field] = (int)$lastSp[$field];
                    } else {
                        $vals[$field] = null;
                    }
                } else {
                    $vals[$field] = trim((string)($_POST[$field] ?? ($lastSp[$field] ?? $default)));
                }
            }

            $conn->begin_transaction();
            try {
                // Ny spesifikasjon
                $sqlSp = "INSERT INTO tblfartspes (
                    FartObj_ID, YearSpes, MndSpes, Verft_ID, Byggenr, FartMat_ID, FartType_ID, FartFunk_ID,
                    FartSkrog_ID, FartDrift_ID, FunkDetalj, TeknDetalj, FartKlasse_ID, Kapasitet,
                    FartRigg_ID, FartMotor_ID, MotorDetalj, MotorEff, MaxFart, Lengde, Bredde, Dypg, Tonnasje,
                    TonnEnh_ID, Drektigh, DrektEnh_ID, Objekt
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmtIns = $conn->prepare($sqlSp);
                if (!$stmtIns) throw new Exception('Kunne ikke forberede ny spesifikasjon.');
                $insertValues = [
                    $objId,
                    $yearSpes, $mndSpes, $verftId,
                    $vals['Byggenr'], $vals['FartMat_ID'], $vals['FartType_ID'], $vals['FartFunk_ID'],
                    $vals['FartSkrog_ID'], $vals['FartDrift_ID'], $vals['FunkDetalj'], $vals['TeknDetalj'], $vals['FartKlasse_ID'],
                    $vals['Kapasitet'], $vals['FartRigg_ID'], $vals['FartMotor_ID'],
                    $vals['MotorDetalj'], $vals['MotorEff'], $vals['MaxFart'], $vals['Lengde'], $vals['Bredde'], $vals['Dypg'],
                    $vals['Tonnasje'], $vals['TonnEnh_ID'], $vals['Drektigh'], $vals['DrektEnh_ID'], $objektF
                ];
                stmt_bind_params($stmtIns, $insertValues);
                $stmtIns->execute();
                $stmtIns->close();
                $newSpesId = (int)$conn->insert_id;

                // Sjekk UNIQUE constraint (FartObj_ID, YearTid, MndTid) før INSERT
                $stmtChk = $conn->prepare('SELECT FartTid_ID FROM tblfarttid WHERE FartObj_ID = ? AND YearTid = ? AND MndTid = ?');
                $chkYear = $yearSpes; $chkMnd = $mndSpes;
                $stmtChk->bind_param('iii', $objId, $chkYear, $chkMnd);
                $stmtChk->execute();
                $chkRes = $stmtChk->get_result();
                if ($chkRes && $chkRes->num_rows > 0) {
                    $chkRes->free();
                    $stmtChk->close();
                    throw new Exception('Det finnes allerede en tidsrad for dette fartøyet med år=' . ($chkYear ?? '0') . ' og måned=' . ($chkMnd ?? '0') . '. Velg en annen kombinasjon.');
                }
                if ($chkRes) $chkRes->free();
                $stmtChk->close();

                // Ny tidsrad som arver fra valgt tidsrad
                $sqlTid = "INSERT INTO tblfarttid (
                    YearTid, MndTid, FartObj_ID, FartSpes_ID, FartNavn, FartType_ID, PennantTiln, Objekt,
                    Rederi, Nasjon_ID, RegHavn, MMSI, Kallesignal, Fiskerinr, Navning, Eierskifte, Annet
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmtTid = $conn->prepare($sqlTid);
                if (!$stmtTid) throw new Exception('Kunne ikke forberede ny tidsrad.');
                $yearTid = $yearSpes; $mndTid = $mndSpes;
                $tidValues = [
                    $yearTid,
                    $mndTid,
                    $objId,
                    $newSpesId,
                    $tidRow['FartNavn'] ?? '',
                    $tidRow['FartType_ID'],
                    $tidRow['PennantTiln'],
                    0,  // Objekt=0 (ikke nybygg, ombygging)
                    $tidRow['Rederi'],
                    $tidRow['Nasjon_ID'],
                    $tidRow['RegHavn'],
                    $tidRow['MMSI'],
                    $tidRow['Kallesignal'],
                    $tidRow['Fiskerinr'],
                    isset($_POST['Navning']) ? 1 : 0,     // Navning fra pop-up
                    isset($_POST['Eierskifte']) ? 1 : 0,  // Eierskifte fra pop-up
                    isset($_POST['Annet']) ? 1 : 0         // Annet fra pop-up
                ];
                stmt_bind_params($stmtTid, $tidValues);
                $stmtTid->execute();
                $stmtTid->close();

                $conn->commit();
                header('Location: ' . $base . '/admin/sw_admin.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $err = 'Feil ved spesifikasjonsendring: ' . h($e->getMessage());
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-3">
  <h1>Endring i spesifikasjoner</h1>
  <?php if (!empty($err)): ?>
    <div class="alert alert-danger"><?= h($err) ?></div>
  <?php endif; ?>
  <?php if ($tidId <= 0): ?>
    <form method="get" class="search-form sw-search">
      <div class="filters">
        <div class="row row-1">
      <label for="type_id">Type</label>
      <select name="type_id" id="type_id">
        <option value="0"<?= $selType===0?' selected':''; ?>>Alle</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int)$t['id'] ?>"<?= $selType===(int)$t['id']?' selected':''; ?>><?= h($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="navn">Navn</label>
      <input type="text" name="navn" id="navn" value="<?= h($qNavn) ?>">
        </div>
        <div class="row row-2">
      <label for="year">År</label>
      <input type="number" name="year" id="year" value="<?= $qYear ? (int)$qYear : '' ?>">
      <label for="reghavn">Reg.havn</label>
      <input type="text" name="reghavn" id="reghavn" value="<?= h($qReg) ?>">
      <label for="nasjon_id">Nasjon</label>
      <select name="nasjon_id" id="nasjon_id">
        <option value="0"<?= $qNat===0?' selected':''; ?>>Alle</option>
        <?php foreach ($nasjoner as $n): ?>
          <option value="<?= (int)$n['id'] ?>"<?= $qNat===(int)$n['id']?' selected':''; ?>><?= h($n['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Søk</span></button>
        </div>
      </div>
    </form>
    <?php
      $rows = [];
      if ($selType || $qNavn !== '' || $qYear || $qReg !== '' || $qNat) {
        $sql = "
          SELECT curr.FartTid_ID, curr.FartObj_ID, curr.FartNavn, curr.RegHavn, curr.Nasjon_ID,
                 curr.YearTid, curr.MndTid, n.Nasjon, o.Bygget, zft.TypeFork
            FROM tblfarttid curr
            LEFT JOIN tblfartobj o   ON o.FartObj_ID = curr.FartObj_ID
            LEFT JOIN (
              SELECT s1.FartObj_ID, s1.FartSpes_ID, s1.FartType_ID
                FROM tblfartspes s1
               WHERE s1.FartSpes_ID IN (
                 SELECT s2.FartSpes_ID FROM tblfartspes s2 WHERE s2.FartObj_ID = s1.FartObj_ID
                 ORDER BY COALESCE(s2.YearSpes,0) DESC, COALESCE(s2.MndSpes,0) DESC, s2.FartSpes_ID DESC LIMIT 1
               )
            ) ls ON ls.FartObj_ID = curr.FartObj_ID
            LEFT JOIN tblzfarttype zft ON zft.FartType_ID = ls.FartType_ID
            LEFT JOIN tblznasjon n ON n.Nasjon_ID = curr.Nasjon_ID
           WHERE curr.FartTid_ID = (
             SELECT t2.FartTid_ID FROM tblfarttid t2
              WHERE t2.FartObj_ID = curr.FartObj_ID
              ORDER BY COALESCE(t2.YearTid,0) DESC, COALESCE(t2.MndTid,0) DESC, t2.FartTid_ID DESC
              LIMIT 1
           )
             AND (? = 0 OR ls.FartType_ID = ?)
             AND (? = '' OR curr.FartNavn LIKE CONCAT('%', ?, '%'))
             AND (? = 0 OR curr.YearTid = ?)
             AND (? = '' OR curr.RegHavn LIKE CONCAT('%', ?, '%'))
             AND (? = 0 OR curr.Nasjon_ID = ?)
           ORDER BY curr.FartNavn ASC
           LIMIT 300";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isisisisii', $selType, $selType, $qNavn, $qNavn, $qYear, $qYear, $qReg, $qReg, $qNat, $qNat);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
        $stmt->close();
      }
    ?>
    <?php if (!empty($rows)): ?>
      <div class="mdc-data-table table-responsive centered-card">
        <div class="mdc-data-table__table-container">
        <table class="mdc-data-table__table table table-striped table-sm">
          <thead><tr class="mdc-data-table__header-row">
            <th class="mdc-data-table__header-cell">Type</th><th class="mdc-data-table__header-cell">Navn</th><th class="mdc-data-table__header-cell">År</th><th class="mdc-data-table__header-cell">Mnd</th><th class="mdc-data-table__header-cell">Reg.havn</th><th class="mdc-data-table__header-cell">Nasjon</th><th class="mdc-data-table__header-cell"></th>
          </tr></thead>
          <tbody class="mdc-data-table__content">
            <?php foreach ($rows as $r): ?>
              <tr class="mdc-data-table__row">
                <td class="mdc-data-table__cell"><?= h($r['TypeFork'] ?? '') ?></td>
                <td class="mdc-data-table__cell"><?= h($r['FartNavn'] ?? '') ?></td>
                <td class="mdc-data-table__cell"><?= h($r['YearTid'] ?? '') ?></td>
                <td class="mdc-data-table__cell"><?= h($r['MndTid'] ?? '') ?></td>
                <td class="mdc-data-table__cell"><?= h($r['RegHavn'] ?? '') ?></td>
                <td class="mdc-data-table__cell"><?= h($r['Nasjon'] ?? '') ?></td>
                <td class="mdc-data-table__cell"><a class="btn-small" href="?tid_id=<?= (int)$r['FartTid_ID'] ?>">Velg</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    <?php elseif ($selType || $qNavn !== '' || $qYear || $qReg !== '' || $qNat): ?>
      <p>Ingen treff.</p>
    <?php else: ?>
      <p class="muted">Søk etter fartøy for å starte.</p>
    <?php endif; ?>
  <?php else: // edit steg ?>
    <?php
      // Hent valgt tidsrad + objekt + siste spes
      $stmt = $conn->prepare('SELECT * FROM tblfarttid WHERE FartTid_ID = ?');
      $stmt->bind_param('i', $tidId);
      $stmt->execute();
      $res = $stmt->get_result();
      $tidRow = $res ? $res->fetch_assoc() : null; if ($res) $res->free(); $stmt->close();
      if (!$tidRow) { echo '<p>Fant ikke tidsrad.</p></div>'; include __DIR__ . '/../includes/footer.php'; exit; }
      $objId = (int)$tidRow['FartObj_ID'];
      $stmt = $conn->prepare('SELECT * FROM tblfartobj WHERE FartObj_ID = ?');
      $stmt->bind_param('i', $objId);
      $stmt->execute(); $res = $stmt->get_result(); $objRow = $res ? $res->fetch_assoc() : null; if ($res) $res->free(); $stmt->close();
      $stmt = $conn->prepare('SELECT * FROM tblfartspes WHERE FartObj_ID = ? ORDER BY COALESCE(YearSpes,0) DESC, COALESCE(MndSpes,0) DESC, FartSpes_ID DESC LIMIT 1');
      $stmt->bind_param('i', $objId);
      $stmt->execute(); $res = $stmt->get_result(); $lastSp = $res ? $res->fetch_assoc() : null; if ($res) $res->free(); $stmt->close();
    ?>
    <div class="mdc-card card mb-3 centered-card">
      <h2 class="h5">Objektinformasjon (ikke redigerbar)</h2>
      <div class="mdc-data-table"><div class="mdc-data-table__table-container">
      <table class="mdc-data-table__table table table-sm table-borderless compact">
        <tbody class="mdc-data-table__content">
          <tr class="mdc-data-table__row"><th style="width:30%" class="text-end">Navn ved bygging</th><td class="mdc-data-table__cell"><?= h($objRow['NavnObj'] ?? '') ?></td></tr>
          <tr class="mdc-data-table__row"><th class="text-end">Fartoystype-ID</th><td class="mdc-data-table__cell"><?= h($objRow['FartType_ID'] ?? '') ?></td></tr>
          <tr class="mdc-data-table__row"><th class="text-end">Bygget (år)</th><td class="mdc-data-table__cell"><?= h($objRow['Bygget'] ?? '') ?></td></tr>
          <tr class="mdc-data-table__row"><th class="text-end">Leverende verft</th><td class="mdc-data-table__cell"><?= h($objRow['LeverID'] ?? '') ?></td></tr>
          <tr class="mdc-data-table__row"><th class="text-end">Byggenummer</th><td class="mdc-data-table__cell"><?= h($objRow['ByggeNr'] ?? '') ?></td></tr>
        </tbody>
      </table>
      </div></div>
    </div>
    <div class="mdc-card card mb-4 centered-card">
      <h2 class="h5">Ny spesifikasjon (arver forrige verdier)</h2>
      <form method="post">
        <input type="hidden" name="action" value="save_spec_change">
        <input type="hidden" name="tid_id" value="<?= (int)$tidId ?>">
        <div class="row g-3">
          <div class="col-6">
            <label for="YearSpes" class="form-label">År spes.</label>
            <input type="number" class="form-control" name="YearSpes" id="YearSpes" <?= function_exists('sw_len')? sw_len('YearSpes'):'' ?> value="<?= h($lastSp['YearSpes'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label for="MndSpes" class="form-label">Måned spes.</label>
            <input type="number" class="form-control" name="MndSpes" id="MndSpes" min="0" max="12" <?= function_exists('sw_len')? sw_len('MndSpes'):'' ?> value="<?= h($lastSp['MndSpes'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label for="Verft_ID" class="form-label">Verft</label>
            <select class="form-select" name="Verft_ID" id="Verft_ID">
              <option value="">-- Velg --</option>
              <?php foreach ($verft as $v): ?>
                <option value="<?= (int)$v['id'] ?>" <?= ((int)($lastSp['Verft_ID'] ?? 0) === (int)$v['id']) ? 'selected' : '' ?>><?= h($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <div class="mdc-data-table"><div class="mdc-data-table__table-container">
          <table class="mdc-data-table__table table compact table-sm table-borderless align-middle">
            <tbody class="mdc-data-table__content">
              <tr class="mdc-data-table__row">
                <th class="text-end" style="width:30%">Byggenr</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Byggenr" <?= function_exists('sw_len')? sw_len('Byggenr'):'' ?> value="<?= h($lastSp['Byggenr'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Materiale</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartMat_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartMat as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['FartMat_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Fartoystype</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartType_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartTyper as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['FartType_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Funksjon</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartFunk_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartFunk as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['FartFunk_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Skrogtype</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartSkrog_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartSkrog as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['FartSkrog_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Driftsmiddel</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartDrift_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartDrift as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['FartDrift_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">FunkDetalj</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="FunkDetalj" <?= function_exists('sw_len')? sw_len('FunkDetalj'):'' ?> value="<?= h($lastSp['FunkDetalj'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">TeknDetalj</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="TeknDetalj" <?= function_exists('sw_len')? sw_len('TeknDetalj'):'' ?> value="<?= h($lastSp['TeknDetalj'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Klasse</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartKlasse_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartKlasse as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['FartKlasse_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Kapasitet</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Kapasitet" <?= function_exists('sw_len')? sw_len('Kapasitet'):'' ?> value="<?= h($lastSp['Kapasitet'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Rigg (kategori)</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartRigg_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartRigg as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['FartRigg_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Motor (kategori)</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartMotor_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartMotor as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['FartMotor_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">MotorDetalj</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="MotorDetalj" <?= function_exists('sw_len')? sw_len('MotorDetalj'):'' ?> value="<?= h($lastSp['MotorDetalj'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">MotorEff</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="MotorEff" <?= function_exists('sw_len')? sw_len('MotorEff'):'' ?> value="<?= h($lastSp['MotorEff'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">MaxFart</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="MaxFart" <?= function_exists('sw_len')? sw_len('MaxFart'):'' ?> value="<?= h($lastSp['MaxFart'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Lengde</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="Lengde" <?= function_exists('sw_len')? sw_len('Lengde'):'' ?> value="<?= h($lastSp['Lengde'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Bredde</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="Bredde" <?= function_exists('sw_len')? sw_len('Bredde'):'' ?> value="<?= h($lastSp['Bredde'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Dypg</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="Dypg" <?= function_exists('sw_len')? sw_len('Dypg'):'' ?> value="<?= h($lastSp['Dypg'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Tonnasje</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Tonnasje" <?= function_exists('sw_len')? sw_len('Tonnasje'):'' ?> value="<?= h($lastSp['Tonnasje'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Tonn-enhet</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="TonnEnh_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($tonnEnheter as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['TonnEnh_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Drektigh</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Drektigh" <?= function_exists('sw_len')? sw_len('Drektigh'):'' ?> value="<?= h($lastSp['Drektigh'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Drekt-enhet</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="DrektEnh_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($drektEnheter as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>" <?= ((int)($lastSp['DrektEnh_ID'] ?? 0) === (int)$opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
          </div></div>
        </div>
        <?php
          // Hent eventuelle flagg fra URL (satt av pop-up i sw_admin.php)
          $popNavning    = isset($_GET['navning']) ? 1 : 0;
          $popEierskifte = isset($_GET['eierskifte']) ? 1 : 0;
          $popAnnet      = isset($_GET['annet']) ? 1 : 0;
        ?>
        <div class="mdc-card card mt-3" style="padding:0.5rem;">
          <h3 class="h6">Samtidige endringer (fra pop-up)</h3>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="Navning" name="Navning" value="1" <?= $popNavning ? 'checked' : '' ?>>
            <label class="form-check-label" for="Navning">Navning (navneendring)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="Eierskifte" name="Eierskifte" value="1" <?= $popEierskifte ? 'checked' : '' ?>>
            <label class="form-check-label" for="Eierskifte">Eierskifte</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="Annet" name="Annet" value="1" <?= $popAnnet ? 'checked' : '' ?>>
            <label class="form-check-label" for="Annet">Andre endringer</label>
          </div>
        </div>
        <button type="submit" class="mdc-button mdc-button--raised btn btn-primary mt-1"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre ny spesifikasjon</span></button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
