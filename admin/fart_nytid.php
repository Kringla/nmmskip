<?php
// admin/fart_nytid.php
// Prosess 3: Endring i navn/eier (ny rad i tblfarttid + valgfritt nye lenker)
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

// Hent referanselister
$types = [];
if ($res = $conn->query("SELECT FartType_ID AS id, TypeFork AS name FROM tblzfarttype ORDER BY name")) {
    while ($row = $res->fetch_assoc()) $types[] = $row;
    $res->free();
}
$nasjoner = [];
if ($res = $conn->query("SELECT Nasjon_ID AS id, Nasjon AS name FROM tblznasjon WHERE Nasjon <> '' ORDER BY name")) {
    while ($row = $res->fetch_assoc()) $nasjoner[] = $row;
    $res->free();
}
$linkTyper = [];
if ($res = $conn->query("SELECT LinkType_ID AS id, LinkType AS name FROM tblzlinktype ORDER BY name")) {
    while ($row = $res->fetch_assoc()) $linkTyper[] = $row;
    $res->free();
}
$linkTypeMap = [];
foreach ($linkTyper as $ltRow) {
    $linkTypeMap[(int)$ltRow['id']] = $ltRow['name'];
}

$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

// Request params
$selType = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
$qNavn   = isset($_GET['navn']) ? trim((string)$_GET['navn']) : '';
$qYear   = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$qReg    = isset($_GET['reghavn']) ? trim((string)$_GET['reghavn']) : '';
$qNat    = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0;
$tidId   = isset($_GET['tid_id']) ? (int)$_GET['tid_id'] : 0;

// POST: lagre ny tidsrad (+ ev. lenker)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_tid_change') {
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
            $spesId = (int)($tidRow['FartSpes_ID'] ?? 0);

            $YearTid = isset($_POST['YearTid']) && $_POST['YearTid'] !== '' ? (int)$_POST['YearTid'] : null;
            $MndTid  = isset($_POST['MndTid']) && $_POST['MndTid'] !== '' ? (int)$_POST['MndTid'] : null;
            $FartNavn = trim((string)($_POST['FartNavn'] ?? $tidRow['FartNavn'] ?? ''));
            $Rederi   = trim((string)($_POST['Rederi'] ?? $tidRow['Rederi'] ?? ''));
            $Nasjon_ID = isset($_POST['Nasjon_ID']) && $_POST['Nasjon_ID'] !== '' ? (int)$_POST['Nasjon_ID'] : (int)($tidRow['Nasjon_ID'] ?? 0);
            $RegHavn  = trim((string)($_POST['RegHavn'] ?? $tidRow['RegHavn'] ?? ''));
            $MMSI     = trim((string)($_POST['MMSI'] ?? $tidRow['MMSI'] ?? ''));
            $Kallesignal = trim((string)($_POST['Kallesignal'] ?? $tidRow['Kallesignal'] ?? ''));
            $Fiskerinr  = trim((string)($_POST['Fiskerinr'] ?? $tidRow['Fiskerinr'] ?? ''));
            $Navning    = isset($_POST['Navning']) ? 1 : 0;
            $Eierskifte = isset($_POST['Eierskifte']) ? 1 : 0;
            $Annet      = isset($_POST['Annet']) ? 1 : 0;

            $conn->begin_transaction();
            try {
                // Sjekk UNIQUE constraint (FartObj_ID, YearTid, MndTid) før INSERT
                $stmtChk = $conn->prepare('SELECT FartTid_ID FROM tblfarttid WHERE FartObj_ID = ? AND YearTid = ? AND MndTid = ?');
                $chkYear = $YearTid; $chkMnd = $MndTid;
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

                $sqlIns = "INSERT INTO tblfarttid (
                    YearTid, MndTid, FartObj_ID, FartSpes_ID, FartNavn, FartType_ID,
                    PennantTiln, Objekt, Rederi, Nasjon_ID, RegHavn, MMSI, Kallesignal,
                    Fiskerinr, Navning, Eierskifte, Annet
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmtIns = $conn->prepare($sqlIns);
                if (!$stmtIns) throw new Exception('Kunne ikke forberede innsetting av ny tidsrad.');
                $tidValues = [
                    $YearTid,
                    $MndTid,
                    $objId,
                    $spesId,
                    $FartNavn,
                    $tidRow['FartType_ID'],
                    $tidRow['PennantTiln'],
                    0,  // Objekt=0 (ikke nybygg)
                    $Rederi,
                    $Nasjon_ID,
                    $RegHavn,
                    $MMSI,
                    $Kallesignal,
                    $Fiskerinr,
                    $Navning,
                    $Eierskifte,
                    $Annet
                ];
                stmt_bind_params($stmtIns, $tidValues);
                $stmtIns->execute();
                $stmtIns->close();
                $newTidId = (int)$conn->insert_id;

                // Lenker (valgfritt)
                if (!empty($_POST['links']) && is_array($_POST['links'])) {
                    $stmtMax = $conn->prepare('SELECT COALESCE(MAX(SerNo), 0) FROM tblxfartlink WHERE FartTid_ID = ?');
                    $stmtMax->bind_param('i', $newTidId);
                    $stmtMax->execute(); $stmtMax->bind_result($maxSerNo); $stmtMax->fetch(); $stmtMax->close();
                    $next = (int)$maxSerNo;
                    $stmtL = $conn->prepare('INSERT INTO tblxfartlink (FartTid_ID, LinkType_ID, LinkType, LinkInnh, Link, SerNo) VALUES (?,?,?,?,?,?)');
                    if (!$stmtL) throw new Exception('Kunne ikke forberede lenkeinnsetting.');
                    foreach ($_POST['links'] as $row) {
                        if (!is_array($row)) continue;
                        $lt = isset($row['LinkType_ID']) && $row['LinkType_ID'] !== '' ? (int)$row['LinkType_ID'] : 1;
                        $li = trim((string)($row['LinkInnh'] ?? ''));
                        $lu = trim((string)($row['Link'] ?? ''));
                        if ($li === '' && $lu === '') continue;
                        $next++;
                        $ltText = $linkTypeMap[$lt] ?? null;
                        $linkValues = [
                            $newTidId,
                            $lt,
                            $ltText,
                            $li !== '' ? $li : null,
                            $lu !== '' ? $lu : null,
                            $next
                        ];
                        stmt_bind_params($stmtL, $linkValues);
                        $stmtL->execute();
                    }
                    $stmtL->close();
                }

                $conn->commit();
                header('Location: ' . $base . '/admin/sw_admin.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $err = 'Feil ved opprettelse av ny tidsrad: ' . h($e->getMessage());
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-3">
  <h1>Endring i navn/eier</h1>
  <?php if (!empty($err)): ?>
    <div class="alert alert-danger"><?= h($err) ?></div>
  <?php endif; ?>
  <?php if ($tidId <= 0): ?>
    <form method="get" class="search-form sw-search">
      <div class="filters">
        <div class="row row-1">
          <div>
            <label for="type_id">Type</label>
            <select name="type_id" id="type_id">
              <option value="0"<?= $selType===0?' selected':''; ?>>Alle</option>
              <?php foreach ($types as $t): ?>
                <option value="<?= (int)$t['id'] ?>"<?= $selType===(int)$t['id']?' selected':''; ?>><?= h($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="navn">Navn</label>
            <input type="text" name="navn" id="navn" value="<?= h($qNavn) ?>">
          </div>
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
      <div class="table-responsive centered-card">
        <div class="mdc-data-table"><div class="mdc-data-table__table-container">
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
        </div></div>
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
      $stmt->execute(); $res = $stmt->get_result(); $spesRow = $res ? $res->fetch_assoc() : null; if ($res) $res->free(); $stmt->close();
    ?>
    <div class="mdc-card card mb-3 centered-card">
      <h2 class="h5">Objektinformasjon (ikke redigerbar)</h2>
      <table class="table table-sm table-borderless compact">
        <tbody>
          <tr><th style="width:30%" class="text-end">Navn ved bygging</th><td><?= h($objRow['NavnObj'] ?? '') ?></td></tr>
          <tr><th class="text-end">Fartoystype-ID</th><td><?= h($objRow['FartType_ID'] ?? '') ?></td></tr>
          <tr><th class="text-end">Bygget (år)</th><td><?= h($objRow['Bygget'] ?? '') ?></td></tr>
          <tr><th class="text-end">Leverende verft</th><td><?= h($objRow['LeverID'] ?? '') ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="mdc-card card mb-3 centered-card">
      <h2 class="h5">Siste spesifikasjon (ikke redigerbar)</h2>
      <table class="table table-sm table-borderless compact">
        <tbody>
          <tr><th style="width:30%" class="text-end">År/Mnd spes.</th><td><?= h(($spesRow['YearSpes'] ?? '') . '/' . ($spesRow['MndSpes'] ?? '')) ?></td></tr>
          <tr><th class="text-end">Verft</th><td><?= h($spesRow['Verft_ID'] ?? '') ?></td></tr>
          <tr><th class="text-end">Byggenr</th><td><?= h($spesRow['Byggenr'] ?? '') ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="mdc-card card mb-4 centered-card">
      <h2 class="h5">Ny navne-/eierendring (tidsrad)</h2>
      <form method="post">
        <input type="hidden" name="action" value="save_tid_change">
        <input type="hidden" name="tid_id" value="<?= (int)$tidId ?>">
        <div class="row g-3">
          <div class="col-4">
            <label for="YearTid" class="form-label">År</label>
            <input type="number" class="form-control" id="YearTid" name="YearTid" <?= function_exists('sw_len')? sw_len('YearTid'):'' ?> value="<?= h($tidRow['YearTid'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label for="MndTid" class="form-label">Måned</label>
            <input type="number" class="form-control" id="MndTid" name="MndTid" min="0" max="12" <?= function_exists('sw_len')? sw_len('MndTid'):'' ?> value="<?= h($tidRow['MndTid'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label for="FartNavn" class="form-label">Navn</label>
            <input type="text" class="form-control" id="FartNavn" name="FartNavn" <?= function_exists('sw_len')? sw_len('FartNavn'):'' ?> value="<?= h($tidRow['FartNavn'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label for="Rederi" class="form-label">Rederi</label>
            <input type="text" class="form-control" id="Rederi" name="Rederi" <?= function_exists('sw_len')? sw_len('Rederi'):'' ?> value="<?= h($tidRow['Rederi'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label for="Nasjon_ID" class="form-label">Nasjon</label>
            <select class="form-select" id="Nasjon_ID" name="Nasjon_ID">
              <?php foreach ($nasjoner as $n): ?>
                <option value="<?= (int)$n['id'] ?>" <?= ((int)($tidRow['Nasjon_ID'] ?? 0) === (int)$n['id']) ? 'selected' : '' ?>><?= h($n['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label for="RegHavn" class="form-label">Reg.havn</label>
            <input type="text" class="form-control" id="RegHavn" name="RegHavn" <?= function_exists('sw_len')? sw_len('RegHavn'):'' ?> value="<?= h($tidRow['RegHavn'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label for="MMSI" class="form-label">MMSI</label>
            <input type="text" class="form-control" id="MMSI" name="MMSI" <?= function_exists('sw_len')? sw_len('MMSI'):'' ?> value="<?= h($tidRow['MMSI'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label for="Kallesignal" class="form-label">Kallesignal</label>
            <input type="text" class="form-control" id="Kallesignal" name="Kallesignal" <?= function_exists('sw_len')? sw_len('Kallesignal'):'' ?> value="<?= h($tidRow['Kallesignal'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label for="Fiskerinr" class="form-label">Fiskerinr</label>
            <input type="text" class="form-control" id="Fiskerinr" name="Fiskerinr" <?= function_exists('sw_len')? sw_len('Fiskerinr'):'' ?> value="<?= h($tidRow['Fiskerinr'] ?? '') ?>">
          </div>
          <?php
            // Forhåndskryss av flagg fra pop-up i sw_admin.php (via URL-parametre)
            $preNavning    = isset($_GET['navning']) ? 1 : 0;
            $preEierskifte = isset($_GET['eierskifte']) ? 1 : 0;
            $preAnnet      = isset($_GET['annet']) ? 1 : 0;
          ?>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="Navning" name="Navning" value="1" <?= $preNavning ? 'checked' : '' ?>>
              <label class="form-check-label" for="Navning">Navning</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="Eierskifte" name="Eierskifte" value="1" <?= $preEierskifte ? 'checked' : '' ?>>
              <label class="form-check-label" for="Eierskifte">Eierskifte</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="Annet" name="Annet" value="1" <?= $preAnnet ? 'checked' : '' ?>>
              <label class="form-check-label" for="Annet">Annet</label>
            </div>
          </div>
        </div>
        <hr>
        <h3 class="h6">Lenker (valgfritt)</h3>
        <div id="links">
          <?php for ($i=0;$i<2;$i++): ?>
            <div class="row g-2 mb-2">
              <div class="col-3">
                <select class="form-select" name="links[<?= $i ?>][LinkType_ID]">
                  <?php foreach ($linkTyper as $lt): ?>
                    <option value="<?= (int)$lt['id'] ?>"><?= h($lt['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-4">
                <input type="text" class="form-control" name="links[<?= $i ?>][LinkInnh]" placeholder="Tittel">
              </div>
              <div class="col-5">
                <input type="text" class="form-control" name="links[<?= $i ?>][Link]" placeholder="URL">
              </div>
            </div>
          <?php endfor; ?>
        </div>
        <button type="submit" class="mdc-button mdc-button--raised btn btn-primary mt-3"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre</span></button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
