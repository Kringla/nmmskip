<?php
// /user/fart_sokspes.php
// Søk på spesifikasjoner (uten navnesøk) – med paginering
// Filtrerer KUN på: FartDrift_ID, FartSkrog_ID, FartFunk_ID (0 = Alle)
// Kolonner: Dimensjoner ~20ch, Materiale ~10ch

// uses global h() from includes/functions.php

/* ---------------------------------------------------------
   HEADER / BOOTSTRAP
   --------------------------------------------------------- */
require_once __DIR__ . '/../includes/header.php';   // $mysqli, BASE_URL, CSS osv.

/* ---------------------------------------------------------
   INPUTS
   --------------------------------------------------------- */
$driftId   = isset($_GET['fartdrift_id']) ? (int)$_GET['fartdrift_id'] : 0;
$skrogId   = isset($_GET['fartskrog_id']) ? (int)$_GET['fartskrog_id'] : 0;
$funkId    = isset($_GET['fartfunk_id'])  ? (int)$_GET['fartfunk_id']  : 0;
$didSubmit = isset($_GET['sok']) && (int)$_GET['sok'] === 1;

// Paginering
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 250;
$offset = ($page - 1) * $limit;

/* ---------------------------------------------------------
   Parametertabeller (schema v13 / Fields v1)
   --------------------------------------------------------- */
function getOptions(mysqli $db, string $table, string $idField, string $labelField): array {
  $sql = "SELECT $idField AS id, $labelField AS txt FROM $table ORDER BY $labelField";
  $res = $db->query($sql);
  if (!$res) return [];
  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $res->free();
  return $rows;
}
$optsDrift = getOptions($mysqli, 'tblzfartdrift', 'FartDrift_ID', 'DriftMiddel');
$optsSkrog = getOptions($mysqli, 'tblzfartskrog', 'FartSkrog_ID', 'TypeSkrog');
$optsFunk  = getOptions($mysqli, 'tblzfartfunk',  'FartFunk_ID',  'TypeFunksjon');

/* ---------------------------------------------------------
   SØK – kun når Søk trykkes
   --------------------------------------------------------- */
$total_count = 0;
$rows        = [];

if ($didSubmit) {
  $where = [];
  $types = '';
  $vals  = [];

  if ($driftId > 0) { $where[] = 's.FartDrift_ID = ?'; $types .= 'i'; $vals[] = $driftId; }
  if ($skrogId > 0) { $where[] = 's.FartSkrog_ID = ?'; $types .= 'i'; $vals[] = $skrogId; }
  if ($funkId  > 0) { $where[] = 's.FartFunk_ID  = ?'; $types .= 'i'; $vals[] = $funkId; }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  // 1) COUNT — eksakt antall distinkte navnlinjer
  $sqlCount = "
    SELECT COUNT(*) AS c
    FROM (
      SELECT t.FartTid_ID
      FROM tblfartspes s
      JOIN tblfarttid t ON t.FartSpes_ID = s.FartSpes_ID
      $whereSql
      GROUP BY t.FartTid_ID
    ) x
  ";
  $stmt = $mysqli->prepare($sqlCount);
  if ($types !== '') { $stmt->bind_param($types, ...$vals); }
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) { $row = $res->fetch_assoc(); $total_count = (int)($row['c'] ?? 0); }
  $stmt->close();

  // 2) DATA – visningsfelter, paginert med LIMIT/OFFSET
  // (LIMIT/OFFSET settes som rene heltall i SQL-strengen for kompatibilitet)
  $sqlData = "
    SELECT
      t.FartTid_ID,
      t.FartObj_ID AS ObjId,
      t.FartNavn,
      COALESCE(s.YearSpes, t.YearTid) AS YearShow,
      ft.FartType,
      s.Materiale,
      s.Lengde, s.Bredde, s.Dypg,
      s.Tonnasje, te.TonnFork,
      s.Drektigh, de.DrektFork
    FROM tblfartspes s
    JOIN tblfarttid t     ON t.FartSpes_ID = s.FartSpes_ID
    LEFT JOIN tblzfarttype ft ON t.FartType_ID = ft.FartType_ID
    LEFT JOIN tblztonnenh  te ON s.TonnEnh_ID  = te.TonnEnh_ID
    LEFT JOIN tblzdrektenh de ON s.DrektEnh_ID = de.DrektEnh_ID
    $whereSql
    GROUP BY t.FartTid_ID, t.FartObj_ID, t.FartNavn, YearShow, ft.FartType,
             s.Materiale, s.Lengde, s.Bredde, s.Dypg, s.Tonnasje, te.TonnFork, s.Drektigh, de.DrektFork
    ORDER BY t.FartNavn, YearShow, t.FartTid_ID
    LIMIT $limit OFFSET $offset
  ";
  $stmt = $mysqli->prepare($sqlData);
  if ($types !== '') { $stmt->bind_param($types, ...$vals); }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
  $stmt->close();
}

/* ---------------------------------------------------------
   URL til detaljside (obj_id + tid_id)
   --------------------------------------------------------- */
if (!defined('BASE_URL')) define('BASE_URL', '');
function detaljUrl(int $objId, int $tidId): string {
  return rtrim(BASE_URL, '/') . '/user/fart_detalj.php?obj_id=' . $objId . '&tid_id=' . $tidId;
}

/* ---------------------------------------------------------
   Hjelper for å bygge paginerings-URLer med gjeldende filtre
   --------------------------------------------------------- */
function pageUrl(int $page, int $driftId, int $skrogId, int $funkId): string {
  $base = $_SERVER['PHP_SELF'];
  $qs = http_build_query([
    'sok'          => 1,
    'fartdrift_id' => $driftId,
    'fartskrog_id' => $skrogId,
    'fartfunk_id'  => $funkId,
    'page'         => $page,
  ]);
  return $base . '?' . $qs;
}
?>

<h1>Fartøy – søk på spesifikasjoner</h1>

<!-- FILTRE – smalt kort (maks bredde som i navn-søk) -->
<div class="card centered-card" style="max-width: 960px; margin: 1rem auto 0;">
  <div class="card-content">
    <form id="spes-sok-form" method="get" class="search-form" onsubmit="return startSpinner();">
      <input type="hidden" name="sok" value="1">
      <div class="search-grid" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:flex-end; justify-content:flex-start;">
        <div class="form-field" style="min-width:220px;">
          <label for="fartdrift_id">Drift</label>
          <select id="fartdrift_id" name="fartdrift_id">
            <option value="0">Alle</option>
            <?php foreach($optsDrift as $o): ?>
              <option value="<?= (int)$o['id'] ?>"<?= $driftId===(int)$o['id']?' selected':'' ?>><?= h($o['txt']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field" style="min-width:220px;">
          <label for="fartskrog_id">Skrog</label>
          <select id="fartskrog_id" name="fartskrog_id">
            <option value="0">Alle</option>
            <?php foreach($optsSkrog as $o): ?>
              <option value="<?= (int)$o['id'] ?>"<?= $skrogId===(int)$o['id']?' selected':'' ?>><?= h($o['txt']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field" style="min-width:220px;">
          <label for="fartfunk_id">Funksjon</label>
          <select id="fartfunk_id" name="fartfunk_id">
            <option value="0">Alle</option>
            <?php foreach($optsFunk as $o): ?>
              <option value="<?= (int)$o['id'] ?>"<?= $funkId===(int)$o['id']?' selected':'' ?>><?= h($o['txt']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="actions-compact" style="margin-top:.5rem;">
        <button type="submit" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Søk</span></button>
      </div>

      <!-- Spinner -->
      <div id="spinner" style="display:none; margin-top:6px; text-align:left;">
        <img src="<?= h(rtrim(BASE_URL,'/')) ?>/assets/img/spinner.gif" alt="Søker …" width="24" height="24" style="vertical-align:middle;">
        <span style="margin-left:6px;">Søker …</span>
      </div>
    </form>
  </div>
</div>

<!-- RESULTATER – smalt kort (samme bredde som over) -->
<?php if ($didSubmit): ?>
  <div class="card centered-card" style="max-width: 960px; margin: 1rem auto 0;">
    <div class="card-content">
      <?php
        $shown        = count($rows);
        $total_pages  = ($total_count > 0) ? (int)ceil($total_count / $limit) : 1;
        $title = ($total_count > $limit)
          ? "Resultater ($shown av $total_count) – side $page av $total_pages"
          : "Resultater ($total_count)";
      ?>
      <h2 style="margin-top:0; position:sticky; top:0; background:var(--bg); z-index:3; padding:.25rem 0;"><?= h($title) ?></h2>

      <?php
        $qs = http_build_query([
          'fartdrift_id' => $driftId,
          'fartskrog_id' => $skrogId,
          'fartfunk_id'  => $funkId,
        ]);
        $printBase = rtrim(BASE_URL,'/') . '/user/print_fart_sokspes.php?' . $qs;
        $csvUrl    = rtrim(BASE_URL,'/') . '/user/export_fart_sokspes.php?' . $qs;
      ?>
      <div class="export-actions">
        <a class="mdc-button mdc-button--raised btn" href="<?= h($printBase . '&orient=P') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 portrett)</span></a>
        <a class="mdc-button mdc-button--raised btn" href="<?= h($printBase . '&orient=L') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 landskap)</span></a>
        <a class="mdc-button mdc-button--raised btn" href="<?= h($csvUrl) ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Last ned CSV</span></a>
      </div>

      <div class="table-wrap center">
        <div class="mdc-data-table table-wrap outline-brand" style="max-height: 440px; overflow: auto;">
          <div class="mdc-data-table__table-container">
          <table class="mdc-data-table__table table tight fit">
            <thead style="position: sticky; top: 0; z-index: 2;">
              <tr class="mdc-data-table__header-row">
                <th class="mdc-data-table__header-cell" style="min-width:22ch;">Fartøysnavn</th>
                <th class="mdc-data-table__header-cell">År</th>
                <th class="mdc-data-table__header-cell">Type</th>
                <!-- Materiale ~10ch, Dimensjoner ~20ch -->
                <th class="mdc-data-table__header-cell" style="width:10ch; min-width:10ch;">Materiale</th>
                <th class="mdc-data-table__header-cell" style="width:20ch; min-width:20ch;">Dimensjoner (L×B×D)</th>
                <th class="mdc-data-table__header-cell">Tonnasje</th>
                <th class="mdc-data-table__header-cell">Drektighet</th>
              </tr>
            </thead>
            <tbody class="mdc-data-table__content">
              <?php if (!$rows): ?>
                <tr class="mdc-data-table__row"><td class="mdc-data-table__cell" colspan="7">Ingen treff for valgte kriterier.</td></tr>
              <?php else: foreach ($rows as $r): ?>
                <?php
                  $tid = (int)($r['FartTid_ID'] ?? 0);
                  $obj = (int)($r['ObjId'] ?? 0);
                  $rowUrl = ($tid > 0 && $obj > 0) ? h(detaljUrl($obj, $tid)) : '';
                ?>
                <tr class="mdc-data-table__row" <?= $rowUrl ? 'data-href="' . $rowUrl . '" style="cursor:pointer;"' : '' ?>>
                  <td class="mdc-data-table__cell"><?= h($r['FartNavn'] ?? '') ?></td>
                  <td class="mdc-data-table__cell"><?= h($r['YearShow'] ?? '') ?></td>
                  <td class="mdc-data-table__cell"><?= h($r['FartType'] ?? '') ?></td>
                  <td class="mdc-data-table__cell"><?= h($r['Materiale'] ?? '') ?></td>
                  <td class="mdc-data-table__cell">
                    <?php
                      $dims = [];
                      if (!empty($r['Lengde'])) $dims[] = h($r['Lengde']).' m';
                      if (!empty($r['Bredde'])) $dims[] = h($r['Bredde']).' m';
                      if (!empty($r['Dypg']))   $dims[] = h($r['Dypg']).' m';
                      echo $dims ? implode(' × ', $dims) : '';
                    ?>
                  </td>
                  <td class="mdc-data-table__cell">
                    <?php
                      $tonn = trim((string)($r['Tonnasje'] ?? ''));
                      $tf   = trim((string)($r['TonnFork'] ?? ''));
                      echo h(trim($tonn . ($tf ? (' ' . $tf) : '')));
                    ?>
                  </td>
                  <td class="mdc-data-table__cell">
                    <?php
                      $dr  = trim((string)($r['Drektigh'] ?? ''));
                      $df  = trim((string)($r['DrektFork'] ?? ''));
                      echo h(trim($dr . ($df ? (' ' . $df) : '')));
                    ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <?php if ($total_count > $limit): ?>
        <div class="actions-compact" style="margin-top:0.75rem; display:flex; gap:0.5rem; align-items:center; justify-content:center;">
          <?php if ($page > 1): ?>
            <a class="btn-small" href="<?= h(pageUrl($page-1, $driftId, $skrogId, $funkId)) ?>">Forrige</a>
          <?php endif; ?>
          <span>Side <?= (int)$page ?> av <?= (int)$total_pages ?></span>
          <?php if ($page < $total_pages): ?>
            <a class="btn-small" href="<?= h(pageUrl($page+1, $driftId, $skrogId, $funkId)) ?>">Neste</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
<?php else: ?>
  <div class="card centered-card" style="max-width: 960px; margin: 1rem auto 0;">
    <div class="card-content">
      <p>Velg ett eller flere filtre og trykk <strong>Søk</strong>.</p>
    </div>
  </div>
<?php endif; ?>

<script>
function startSpinner(){
  var sp = document.getElementById('spinner');
  if (sp) sp.style.display = 'block';
  return true;
}
window.addEventListener('load', function(){
  var sp = document.getElementById('spinner');
  if (sp) sp.style.display = 'none';
});
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('tr[data-href]').forEach(function(tr){
    tr.addEventListener('dblclick', function(){
      var url = tr.getAttribute('data-href');
      if (url) window.location.href = url;
    });
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
