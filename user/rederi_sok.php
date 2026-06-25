<?php
// /user/rederi_sok.php — åpen søkeside (ingen auth‑krav)

/*
 * Denne siden lar brukeren søke etter rederier (skipseiere) og viser en
 * rederiliste med alle rederi som matcher søkekriteriet. Når et rederi
 * velges, vises en liste over fartøyer som eies/har vært eid av det valgte
 * rederiet. Søkeordet hentes fra ?q og det valgte rederinavnet fra ?rederi.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Dersom bootstrap ikke laget $conn, forsøk å koble via config
if (!isset($conn) || !($conn instanceof mysqli)) {
    $cfgFile = __DIR__ . '/../config/config.php';
    if (is_file($cfgFile)) {
        require_once $cfgFile;
    }
    if (!isset($conn) || !($conn instanceof mysqli)) {
        die('DB‑tilkobling mangler.');
    }
}
$conn->set_charset('utf8mb4');

// Uses global h() from includes/functions.php

// Input
$q           = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$selRederi   = isset($_GET['rederi']) ? trim((string)$_GET['rederi']) : '';
$rederiList  = [];
$fartoyListe = [];
$error       = null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
// Egen paginering for fartøyliste
$page_fartoy = isset($_GET['page_fartoy']) ? max(1, (int)$_GET['page_fartoy']) : 1;
$perPage_fartoy = 25;
// SQL uttrykk for 'trimmet rederinavn' iht. CR: behold alt t.o.m. siste ')', eller t.o.m. tegnet før siste ','.
$REDE_TI_TRIM_T = "
RTRIM(
  TRIM(TRAILING CONCAT(' ', t.RegHavn) FROM
    SUBSTRING(
      t.Rederi,
      1,
      GREATEST(
        CASE WHEN LOCATE(', ', t.Rederi) > 0
             THEN CHAR_LENGTH(t.Rederi) - LOCATE(', ', REVERSE(t.Rederi)) - 1
             ELSE 0 END,
        CASE WHEN LOCATE(') ', t.Rederi) > 0
             THEN CHAR_LENGTH(t.Rederi) - LOCATE(') ', REVERSE(t.Rederi)) - 1
             ELSE 0 END,
        CHAR_LENGTH(t.Rederi)
      )
    )
  )
)
";
$REDE_TI_TRIM_FT = "
RTRIM(
  TRIM(TRAILING CONCAT(' ', ft.RegHavn) FROM
    SUBSTRING(
      ft.Rederi,
      1,
      GREATEST(
        CASE WHEN LOCATE(', ', ft.Rederi) > 0
             THEN CHAR_LENGTH(ft.Rederi) - LOCATE(', ', REVERSE(ft.Rederi)) - 1
             ELSE 0 END,
        CASE WHEN LOCATE(') ', ft.Rederi) > 0
             THEN CHAR_LENGTH(ft.Rederi) - LOCATE(') ', REVERSE(ft.Rederi)) - 1
             ELSE 0 END,
        CHAR_LENGTH(ft.Rederi)
      )
    )
  )
)
";


// 1) Søk etter rederier (min. 2 tegn). Basert på tblfarttid.Rederi.
if ($q !== '' && mb_strlen($q) >= 2) {
    $sql = "
        SELECT DISTINCT $REDE_TI_TRIM_T AS Rederi
        FROM tblfarttid t
        WHERE $REDE_TI_TRIM_T LIKE CONCAT('%', ?, '%')
          AND t.Rederi IS NOT NULL AND t.Rederi <> ''
        ORDER BY Rederi
        LIMIT 500
    ";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $q);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rederiList[] = $row;
        }
        $stmt->close();
    } else {
        $error = 'Kunne ikke forberede SQL for rederi‑søk.';
    }

    // 2) Dersom vi har rederiresultater, bestem hvilket rederi som er valgt
    if (!$error && count($rederiList) > 0) {
        // Finn valgt rederi i resultatlisten; ellers bruk første
        $selectedName = null;
        if ($selRederi !== '') {
            foreach ($rederiList as $r) {
                if ((string)$r['Rederi'] === $selRederi) {
                    $selectedName = $r['Rederi'];
                    break;
                }
            }
        }
        if (!$selectedName) {
            $selectedName = $rederiList[0]['Rederi'];
        }

        // 3) Fartøyliste: alle fartøyer eid/har vært eid av valgt rederi
        $sqlFart = "
            SELECT
                ft.FartTid_ID,
                ft.FartObj_ID,
                ft.FartType_ID,
                ft.FartNavn,
                ft.YearTid,
                ft.MndTid,
                ft.RegHavn,
                ft.Rederi,
                ft.Objekt,
                zt.TypeFork
            FROM tblfarttid ft
            LEFT JOIN tblzfarttype zt ON zt.FartType_ID = ft.FartType_ID
            WHERE $REDE_TI_TRIM_FT = ?
            ORDER BY ft.YearTid, ft.MndTid, ft.FartNavn
            LIMIT 500
        ";
        if ($stmt = $conn->prepare($sqlFart)) {
            $stmt->bind_param('s', $selectedName);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $fartoyListe[] = $row;
            }
            $stmt->close();
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<!-- Hero image for rederi‑søk page -->
<div class="container">
    <section class="hero hero--small" style="background-image:url('../assets/img/rederi_sok_1.jpg'); background-size:cover; background-position:center;">
        <div class="hero-overlay"></div>
    </section>
</div>

<section class="container">
  <h1>Søk rederi</h1>

  <form method="get" class="search-form" style="margin-bottom:1rem;">
    <label for="q">Rederinavn (min. 2 tegn)</label>
    <input type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="f.eks. Knutsen, Wilhelmsen ...">
    <button type="submit" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Søk</span></button>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <p>Angi minst 2 tegn.</p>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) >= 2): ?>
    <?php
    $total_rederiList = is_array($rederiList) ? count($rederiList) : 0;
    $maxPage_rederiList = $perPage > 0 ? (int)ceil($total_rederiList / $perPage) : 1;
    if ($page > $maxPage_rederiList) $page = $maxPage_rederiList ?: 1;
    $offset_rederiList = ($page - 1) * $perPage;
    $rederiList_paged = array_slice($rederiList, $offset_rederiList, $perPage);
    if (!function_exists('build_page_url')) {
        function build_page_url($pageNum) {
            $params = $_GET;
            $params['page'] = $pageNum;
            $qs = http_build_query($params);
            return basename($_SERVER['PHP_SELF']) . ($qs ? ('?' . $qs) : '');
        }
    }
?>
<h2>Rederier funnet (<?= count($rederiList) ?>)</h2>
    <?php if (!count($rederiList)): ?>
      <p>Ingen treff.</p>
    <?php else: ?>
      <div id="rederiliste" class="card centered-card">
          <div class="mdc-data-table table-wrap table-wrap--scroll center">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit table--compact table--zebra">
              <thead>
                <tr class="mdc-data-table__header-row">
                  <th class="mdc-data-table__header-cell">Rederi</th>
                </tr>
              </thead>
              <tbody class="mdc-data-table__content">
                <?php foreach ($rederiList_paged as $r): ?>
                  <?php $navn = (string)$r['Rederi']; ?>
                  <?php
                    $rowUrl = '?q=' . urlencode($q) . '&rederi=' . urlencode($navn) . '&page=' . (int)$page . '#rederiliste';
                    $rowStyle = 'cursor:pointer;';
                    $isSelected = (isset($selectedName) && $navn === $selectedName);
                    if ($isSelected) { $rowStyle = 'background:var(--accent);cursor:pointer;'; }
                  ?>
                  <tr class="mdc-data-table__row row-click" data-href="<?= h($rowUrl) ?>" tabindex="0" role="button" aria-label="Velg rederi <?= h($navn) ?>" style="<?= h($rowStyle) ?>">
                    <td class="mdc-data-table__cell"><?= h($navn) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
           </table>
           </div>
          </div>
        </div>
        <script>
          (function(){
            var wrap = document.getElementById('rederiliste');
            if(!wrap) return;
            var table = wrap.querySelector('table');
            if(!table) return;
            table.addEventListener('click', function(ev){
              var a = ev.target.closest('a');
              if (a) return; // allow native link clicks
              var tr = ev.target.closest('tr.row-click');
              if (!tr) return;
              var href = tr.getAttribute('data-href');
              if (href) { window.location.href = href; }
            });
            table.addEventListener('keydown', function(ev){
              if (ev.key !== 'Enter' && ev.key !== ' ') return;
              var tr = ev.target.closest('tr.row-click');
              if (!tr || document.activeElement !== tr) return;
              ev.preventDefault();
              var href = tr.getAttribute('data-href');
              if (href) { window.location.href = href; }
            });
          })();
        </script>

<!-- Pagination -->
<div class="pagination" style="display:flex; gap:.5rem; justify-content:center; align-items:center; padding:.75rem 0;">
  <span>Viser <?= $total_rederiList ? ($offset_rederiList+1) : 0 ?>–<?= min($offset_rederiList+$perPage, $total_rederiList) ?> av <?= $total_rederiList ?></span>
  <?php if ($maxPage_rederiList > 1): ?>
    <a class="btn-small" href="<?= h(build_page_url(1)) ?>#rederiliste" <?php if($page<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>« Første</a>
    <a class="btn-small" href="<?= h(build_page_url(max(1,$page-1))) ?>#rederiliste" <?php if($page<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>‹ Forrige</a>
    <span>Side <?= $page ?> / <?= $maxPage_rederiList ?></span>
    <a class="btn-small" href="<?= h(build_page_url(min($maxPage_rederiList,$page+1))) ?>#rederiliste" <?php if($page>=$maxPage_rederiList): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Neste ›</a>
    <a class="btn-small" href="<?= h(build_page_url($maxPage_rederiList)) ?>#rederiliste" <?php if($page>=$maxPage_rederiList): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Siste »</a>
  <?php endif; ?>
</div>
</div>

      <!-- Fartøyliste for valgt rederi -->
      <h3 style="margin-top:1.5rem;">Fartøyer eid av valgt rederi</h3>
      <?php if (count($fartoyListe) === 0): ?>
        <p>Ingen registrerte fartøyer for dette rederiet.</p>
      <?php else: ?>
        <?php
          $total_fartoy = is_array($fartoyListe) ? count($fartoyListe) : 0;
          $maxPage_fartoy = $perPage_fartoy > 0 ? (int)ceil($total_fartoy / $perPage_fartoy) : 1;
          if ($page_fartoy > $maxPage_fartoy) $page_fartoy = $maxPage_fartoy ?: 1;
          $offset_fartoy = ($page_fartoy - 1) * $perPage_fartoy;
          $fartoyListe_paged = array_slice($fartoyListe, $offset_fartoy, $perPage_fartoy);
          if (!function_exists('build_page_url_fartoy')) {
            function build_page_url_fartoy($pageNum) {
              $params = $_GET;
              $params['page_fartoy'] = $pageNum;
              $qs = http_build_query($params);
              return basename($_SERVER['PHP_SELF']) . ($qs ? ('?' . $qs) : '');
            }
          }
        ?>
        <?php $qsR = http_build_query(['rederi' => isset($selectedName)?$selectedName:$selRederi]); $printF = rtrim(BASE_URL,'/') . '/user/print_rederi_sok.php?section=fartoy&' . $qsR; $csvF = rtrim(BASE_URL,'/') . '/user/export_rederi_sok.php?section=fartoy&' . $qsR; ?>
        <div class="export-actions">
          <a class="mdc-button mdc-button--raised btn" href="<?= h($printF . '&orient=P') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 portrett)</span></a>
          <a class="mdc-button mdc-button--raised btn" href="<?= h($printF . '&orient=L') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 landskap)</span></a>
          <a class="mdc-button mdc-button--raised btn" href="<?= h($csvF) ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Last ned CSV</span></a>
        </div>
        <div id="fartoyer" class="card centered-card">
          <div class="mdc-data-table table-wrap table-wrap--scroll center">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit table--compact table--zebra">
              <thead>
                <tr class="mdc-data-table__header-row">
                  <th class="mdc-data-table__header-cell">Navn</th>
                  <th class="mdc-data-table__header-cell">Reg. havn</th>
                  <th class="mdc-data-table__header-cell">Fra År/mnd</th>
                  <th class="mdc-data-table__header-cell">Objekt</th>
                </tr>
              </thead>
              <tbody class="mdc-data-table__content">
                <?php foreach ($fartoyListe_paged as $r): ?>
                  <?php
                    $year  = isset($r['YearTid']) ? (int)$r['YearTid'] : 0;
                    $month = isset($r['MndTid']) ? (int)$r['MndTid'] : 0;
                    $ym    = $year ? ($year . '/' . ($month ? str_pad((string)$month, 2, '0', STR_PAD_LEFT) : '')) : '';
                    $navn  = trim((string)($r['TypeFork'] ?? ''));
                    $navn  = $navn !== '' ? ($navn . ' ') : '';
                    $navn .= (string)($r['FartNavn'] ?? '');
                    $objId  = isset($r['FartObj_ID']) ? (int)$r['FartObj_ID'] : 0;
                    $navnId = isset($r['FartTid_ID'])  ? (int)$r['FartTid_ID']  : 0;
                    $fRowUrl = ($objId > 0 && $navnId > 0) ? h('fart_detalj.php?obj_id=' . $objId . '&tid_id=' . $navnId . '#fartoyliste') : '';
                  ?>
                  <tr class="mdc-data-table__row" <?= $fRowUrl ? 'data-href="' . $fRowUrl . '" style="cursor:pointer;"' : '' ?>>
                    <td class="mdc-data-table__cell"><?= h($navn) ?></td>
                    <td class="mdc-data-table__cell"><?= h($r['RegHavn'] ?? '') ?></td>
                    <td class="mdc-data-table__cell"><?= h($ym) ?></td>
                    <td class="mdc-data-table__cell">
                      <?php if (isset($r['Objekt']) && (int)$r['Objekt'] === 1): ?>
                        <span title="Navnet tilhører opprinnelig fartøy" aria-hidden="true">•</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          </div>
        </div>
        <div class="pagination" style="display:flex; gap:.5rem; justify-content:center; align-items:center; padding:.75rem 0;">
          <span>Viser <?= $total_fartoy ? ($offset_fartoy+1) : 0 ?>-<?= min($offset_fartoy+$perPage_fartoy, $total_fartoy) ?> av <?= $total_fartoy ?></span>
          <?php if ($maxPage_fartoy > 1): ?>
            <a class="btn-small" href="<?= h(build_page_url_fartoy(1)) ?>#fartoyer" <?php if($page_fartoy<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>« Første</a>
            <a class="btn-small" href="<?= h(build_page_url_fartoy(max(1,$page_fartoy-1))) ?>#fartoyer" <?php if($page_fartoy<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>‹ Forrige</a>
            <span>Side <?= $page_fartoy ?> / <?= $maxPage_fartoy ?></span>
            <a class="btn-small" href="<?= h(build_page_url_fartoy(min($maxPage_fartoy,$page_fartoy+1))) ?>#fartoyer" <?php if($page_fartoy>=$maxPage_fartoy): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Neste ›</a>
            <a class="btn-small" href="<?= h(build_page_url_fartoy($maxPage_fartoy)) ?>#fartoyer" <?php if($page_fartoy>=$maxPage_fartoy): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Siste »</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>

<!-- Tilbake-knapp nederst: midtstilt -->
<div class="actions" style="margin:1rem 0 2rem; text-align:center;">
  <a class="mdc-button mdc-button--raised btn" href="#" onclick="if(history.length>1){history.back();return false;}" title="Tilbake"><span class="mdc-button__ripple"></span><span class="mdc-button__label">← Tilbake</span></a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('tr[data-href]').forEach(function(tr){
    tr.addEventListener('dblclick', function(e){
      if (e.target.closest('a')) return;
      var url = tr.getAttribute('data-href');
      if (url) window.location.href = url;
    });
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


