<?php
// /user/fart_soknavn.php  (v0905A)
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$regHavn  = isset($_GET['reghavn']) ? trim((string)$_GET['reghavn']) : '';
$nasjonId = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0;
$doSearch = ($q !== '' || $regHavn !== '' || $nasjonId > 0);
$limit    = 200;

$dbh = $db ?? ($mysqli ?? null);
$rows = [];
$listNasjon = [];
$error = null;

// Nasjon-liste for nedtrekk
try {
  if ($dbh) {
    $res = $dbh->query("SELECT Nasjon_ID, Nasjon FROM tblznasjon ORDER BY Nasjon");
    if ($res) while ($r = $res->fetch_assoc()) $listNasjon[] = $r;
  } else {
    $error = "DB-tilkobling mangler (dbh=null).";
  }
} catch (Throwable $e) { $error = "Feil ved nasjon-oppslag: " . h($e->getMessage()); }

// Søk
if ($error === null && $doSearch) {
  try {
    $sql = "
      SELECT
        ft.FartTid_ID,
        ft.FartObj_ID,
        ft.FartNavn,
        ft.YearTid,
        ft.RegHavn,
        ft.Nasjon_ID,
        ft.Kallesignal,
        ft.MMSI,
        ft.PennantTiln,
        ty.FartType,
        ns.Nasjon,
        CASE WHEN dm.FartTid_ID IS NULL THEN 0 ELSE 1 END AS HasDimu
      FROM tblfarttid ft
      LEFT JOIN tblzfarttype ty ON ty.FartType_ID = ft.FartType_ID
      LEFT JOIN tblznasjon   ns ON ns.Nasjon_ID   = ft.Nasjon_ID
      LEFT JOIN (
        SELECT DISTINCT FartTid_ID
        FROM tblxdigmuseum
        WHERE COALESCE(DIMUkode, '') <> ''
      ) dm ON dm.FartTid_ID = ft.FartTid_ID
      WHERE 1
        AND (? = '' OR ft.FartNavn LIKE ?)
        AND (? = '' OR ft.RegHavn  LIKE ?)
        AND (? = 0  OR ft.Nasjon_ID = ?)
      ORDER BY ft.FartNavn ASC, ft.YearTid ASC
      LIMIT {$limit}
    ";
    $stmt = $dbh->prepare($sql);
    $likeQ = '%' . $q . '%';
    $likeR = '%' . $regHavn . '%';
    $stmt->bind_param('ssssii', $q, $likeQ, $regHavn, $likeR, $nasjonId, $nasjonId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
  } catch (Throwable $e) { $error = "Søk feilet: " . h($e->getMessage()); }
}
?>
<div class="container">
  <h1 Style="padding: 18px;">Fartøysnavn – søk</h1>

  <!-- Søkeskjema: isolert styling slik at felter ALLTID vises -->
  <form method="get" action=""
        style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:center; margin:12px 0 16px;">
    <div class="search-form__row" style="width:100%; display:flex; flex-wrap:wrap; gap:10px 16px; align-items:center; justify-content:center;">
    <label for="q" style="font-weight:600;">Navn inneholder</label>
    <input id="q" name="q" type="text" value="<?= h($q) ?>"
      placeholder="Skriv del av navn"
      style="display:block!important; visibility:visible!important; opacity:1!important;
             border:1px solid #111; background:#fff; color:#000; min-width:34ch;
             height:34px; padding:6px 10px; border-radius:6px; z-index:10; position:relative;" />

    <label for="reghavn" style="font-weight:600;">Reg.havn</label>
    <input id="reghavn" name="reghavn" type="text" value="<?= h($regHavn) ?>"
      placeholder="f.eks. Bergen"
      style="display:block; border:1px solid #111; background:#fff; color:#000; min-width:26ch;
             height:34px; padding:6px 10px; border-radius:6px; position:relative;" />

    <label for="nasjon_id" style="font-weight:600;">Nasjon</label>
    <select id="nasjon_id" name="nasjon_id"
      style="display:block!important; visibility:visible!important; opacity:1!important;
             border:1px solid #111; background:#fff; color:#000; min-width:18ch;
             height:34px; padding:6px 8px; border-radius:6px; z-index:10; position:relative;">
      <option value="0"<?= $nasjonId===0?' selected':'' ?>>Alle</option>
      <?php foreach ($listNasjon as $n): ?>
        <option value="<?= (int)$n['Nasjon_ID'] ?>"<?= $nasjonId===(int)$n['Nasjon_ID']?' selected':'' ?>>
          <?= h($n['Nasjon']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    </div>
    <div class="search-form__actions" style="width:100%; text-align:center;">
      <button type="submit"
      style="display:inline-block!important; visibility:visible!important; opacity:1!important;
             background:#004080; color:#fff; border:0; height:34px; padding:0 14px;
             border-radius:6px; cursor:pointer; z-index:10; position:relative; font-size:12px;">
      Søk
    </button>
    <?php if ($q !== '' || $regHavn !== '' || $nasjonId>0): ?>
      <a href="?q=&reghavn=&nasjon_id=0"
        style="display:inline-block!important; visibility:visible!important; opacity:1!important;
               background:#e5e7eb; color:#111; text-decoration:none; height:34px; line-height:34px;
               padding:0 12px; border-radius:6px; font-size:12px;">
        Nullstill
      </a>
    <?php endif; ?>
    </div>
  </form>
  <div id="loading" style="display:none; text-align:center; margin:10px;">
    <img src="<?= BASE_URL ?>/assets/img/spinner.gif" alt="Søker..." style="width:24px; height:24px; vertical-align:middle;">
    <span style="margin-left:6px;">Søker, vennligst vent.</span>
  </div>

  <?php if ($error): ?>
    <div style="background:#fde8e8; color:#7a0b0b; border:1px solid #f5c2c2; padding:10px; border-radius:6px; max-width:980px; margin:0 auto;">
      <?= $error ?>
    </div>
  <?php endif; ?>

  <?php if ($doSearch): ?>
    <div style="max-width:1100px; margin:10px auto 0;">
      <h2 style="text-align:center; margin:0 0 8px;">Søkeresultat<?= $rows ? ' ('.count($rows).')' : '' ?></h2>

      <?php
        $params = [ 'q'=>$q, 'reghavn'=>$regHavn, 'nasjon_id'=>$nasjonId ];
        $qs = http_build_query($params);
        $printBase = rtrim(BASE_URL,'/') . '/user/print_fart_soknavn.php?' . $qs;
        $csvUrl    = rtrim(BASE_URL,'/') . '/user/export_fart_soknavn.php?' . $qs;
      ?>
      <div class="export-actions">
        <a class="mdc-button mdc-button--raised btn" href="<?= h($printBase . '&orient=P') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 portrett)</span></a>
        <a class="mdc-button mdc-button--raised btn" href="<?= h($printBase . '&orient=L') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 landskap)</span></a>
        <a class="mdc-button mdc-button--raised btn" href="<?= h($csvUrl) ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Last ned CSV</span></a>
      </div>
      <!-- Scroll i tabellboks + sticky thead uten ekstern CSS -->
      <div style="position:relative; overflow:auto; max-height:72vh; border:1px solid #ddd; border-radius:8px; background:#fff;">
        <table style="width:100%; border-collapse:separate; border-spacing:0; font-size:14px;">
          <thead>
            <tr style="background:#f8f9fb;">
              <?php
              $th = 'style="position:sticky; top:0; z-index:2; background:#fff; padding:.5rem .6rem; border-bottom:1px solid #ddd; text-align:left;"';
              ?>
              <th <?= $th ?>>Navn</th>
              <th <?= $th ?>>År</th>
              <th <?= $th ?>>Type</th>
              <th <?= $th ?>>Reg.havn</th>
              <th <?= $th ?>>Nasjon</th>
              <th <?= $th ?>>Kallesignal</th>
              <th <?= $th ?>>MMSI</th>
              <th <?= $th ?> style="width:90px;">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" style="padding:.6rem;">Ingen treff.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <?php $rowUrl = BASE_URL . '/user/fart_detalj.php?obj_id=' . (int)($r['FartObj_ID'] ?? 0) . '&tid_id=' . (int)($r['FartTid_ID'] ?? 0); ?>
            <tr data-href="<?= h($rowUrl) ?>" style="cursor:pointer;">
              <td style="padding:.45rem .6rem; border-bottom:1px solid #eee;"><?= h($r['FartNavn'] ?? '') ?></td>
              <td style="padding:.45rem .6rem; border-bottom:1px solid #eee;"><?= h($r['YearTid'] ?? '') ?></td>
              <td style="padding:.45rem .6rem; border-bottom:1px solid #eee;"><?= h($r['FartType'] ?? '') ?></td>
              <td style="padding:.45rem .6rem; border-bottom:1px solid #eee;"><?= h($r['RegHavn'] ?? '') ?></td>
              <td style="padding:.45rem .6rem; border-bottom:1px solid #eee;"><?= h($r['Nasjon'] ?? '') ?></td>
              <td style="padding:.45rem .6rem; border-bottom:1px solid #eee;"><?= h($r['Kallesignal'] ?? '') ?></td>
              <td style="padding:.45rem .6rem; border-bottom:1px solid #eee;"><?= h($r['MMSI'] ?? '') ?></td>
              <td style="padding:.35rem .6rem; border-bottom:1px solid #eee; text-align:center;">
                <?php if (!empty($r['HasDimu'])): ?>
                  <span class="dimu-label" title="Har DigitaltMuseum-foto">DiMu</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php else: ?>
    <h4>Skriv del av navn (og ev. nasjon) for å søke!</h4>
  <?php endif; ?>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const form = document.querySelector("form");
  const loader = document.getElementById("loading");
  if (form && loader) {
    form.addEventListener("submit", function() {
      loader.style.display = "block";
    });
  }

  document.querySelectorAll('tr[data-href]').forEach(function(tr) {
    tr.addEventListener('dblclick', function() {
      var url = tr.getAttribute('data-href');
      if (url) window.location.href = url;
    });
  });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<!-- fn_sok v0905A -->

