<?php
// /user/verft_sok.php — åpen søkeside (ingen auth‑krav)

/*
 * Søkeside for verft:
 *  1) Liste over verft som matcher ?q
 *  2) Leveranser fra valgt verft
 *  3) Skrog bygget ved valgt verft
 *
 * Denne versjonen legger kun til paginering i verftlisten + retter SQL-feil.
 * Øvrig layout/meny (faner) kommer fra includes/header.php og berøres ikke.
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

// uses global h() from includes/functions.php

// Input
$q           = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$verftId     = isset($_GET['verft_id']) ? (int)$_GET['verft_id'] : 0;
// Avkrysningsboks for å inkludere sted i søket (default: av)
$includeSted = isset($_GET['include_sted']) && (string)$_GET['include_sted'] !== '';

// Resultatlister
$verftList     = [];
$leveranseList = [];
$skrogList     = [];
$error         = null;

// Paginering for VERFT-listen
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
// Egen paginering for leveranser og skrog
$page_lev   = isset($_GET['page_lev']) ? max(1, (int)$_GET['page_lev']) : 1;
$page_skrog = isset($_GET['page_skrog']) ? max(1, (int)$_GET['page_skrog']) : 1;
$perPage_vessel = 25;

// 1) Søk etter verft (min. 2 tegn). Basert på tblverft.
if ($q !== '' && mb_strlen($q) >= 2) {
    if ($includeSted) {
        $sql = "
            SELECT v.Verft_ID, v.VerftNavn, v.Sted, n.Nasjon
            FROM tblverft v
            LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID
            WHERE v.VerftNavn LIKE CONCAT('%', ?, '%')
               OR v.Sted      LIKE CONCAT('%', ?, '%')
            ORDER BY v.VerftNavn
            LIMIT 1000
        ";
    } else {
        $sql = "
            SELECT v.Verft_ID, v.VerftNavn, v.Sted, n.Nasjon
            FROM tblverft v
            LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID
            WHERE v.VerftNavn LIKE CONCAT('%', ?, '%')
            ORDER BY v.VerftNavn
            LIMIT 1000
        ";
    }
    if ($stmt = $conn->prepare($sql)) {
        if ($includeSted) {
            $stmt->bind_param('ss', $q, $q);
        } else {
            $stmt->bind_param('s', $q);
        }
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $verftList[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error = 'Kunne ikke forberede SQL for verft‑søk.';
    }

    // Hvis vi fant verft og har valgt verft_id, behold det valgt, ellers bruk første i lista
    if (!$error && count($verftList) > 0) {
        $ids = array_map('intval', array_column($verftList, 'Verft_ID'));
        if ($verftId === 0 || !in_array($verftId, $ids, true)) {
            $verftId = (int)$verftList[0]['Verft_ID'];
        }

        // 2) Fartøyer levert av verftet
        // NB: LEFT JOIN tblfartobj MÅ stå før WHERE (rettelse av SQL-feil)
        $sqlLev = "
          SELECT
            fs.FartSpes_ID,
            fs.FartObj_ID,
            fs.Verft_ID,
            fs.Byggenr,
            fs.YearSpes,
            fs.MndSpes,
            tid.FartTid_ID,
            tid.FartNavn,
            tid.RegHavn,
            tid.Rederi,
            zt.TypeFork,
            CASE WHEN fo.FartObj_ID IS NOT NULL THEN 1 ELSE 0 END AS Objekt
          FROM tblfartspes fs

          /* Bind 'nyeste navn' til spesifikasjonen */
          LEFT JOIN (
              SELECT t1.FartSpes_ID, t1.FartTid_ID, t1.FartNavn, t1.RegHavn, t1.Rederi, t1.FartType_ID
              FROM (
                  SELECT FartSpes_ID, MAX(FartTid_ID) AS FartTid_ID
                  FROM tblfarttid
                  GROUP BY FartSpes_ID
              ) tmx
              JOIN tblfarttid t1 ON t1.FartTid_ID = tmx.FartTid_ID
          ) tid ON tid.FartSpes_ID = fs.FartSpes_ID

          LEFT JOIN tblzfarttype zt ON zt.FartType_ID = tid.FartType_ID
          LEFT JOIN tblfartobj fo   ON fo.FartObj_ID   = fs.FartObj_ID

          WHERE fs.Verft_ID = ?
            AND (fo.SkrogID IS NULL OR fo.SkrogID = fs.Verft_ID)

          ORDER BY fs.YearSpes, fs.MndSpes, tid.FartNavn
          LIMIT 500
        ";
        if ($stmt = $conn->prepare($sqlLev)) {
            $stmt->bind_param('i', $verftId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $leveranseList[] = $row;
                }
            }
            $stmt->close();
        }

        // 3) Fartøyer skrogbygd ved verftet (ikke levert av samme verft)
        $sqlSkrog = "
          SELECT
            fs.FartSpes_ID,
            fs.FartObj_ID,
            fs.Verft_ID,
            fs.Byggenr,
            fs.YearSpes,
            fs.MndSpes,
            tid.FartTid_ID,
            tid.FartNavn,
            tid.RegHavn,
            tid.Rederi,
            zt.TypeFork,
            CASE WHEN fo.FartObj_ID IS NOT NULL THEN 1 ELSE 0 END AS Objekt
          FROM tblfartspes fs
          LEFT JOIN tblfartobj fo ON fo.FartObj_ID = fs.FartObj_ID

          LEFT JOIN (
              SELECT t1.FartSpes_ID, t1.FartTid_ID, t1.FartNavn, t1.RegHavn, t1.Rederi, t1.FartType_ID
              FROM (
                  SELECT FartSpes_ID, MAX(FartTid_ID) AS FartTid_ID
                  FROM tblfarttid
                  GROUP BY FartSpes_ID
              ) t2
              JOIN tblfarttid t1 ON t1.FartTid_ID = t2.FartTid_ID
          ) tid ON tid.FartSpes_ID = fs.FartSpes_ID

          LEFT JOIN tblzfarttype zt ON zt.FartType_ID = tid.FartType_ID

          WHERE fo.SkrogID = ?
            AND fo.SkrogID IS NOT NULL
            AND (fs.Verft_ID IS NULL OR fo.SkrogID <> fs.Verft_ID)

          ORDER BY fs.YearSpes, fs.MndSpes, tid.FartNavn
          LIMIT 500
        ";
        if ($stmt = $conn->prepare($sqlSkrog)) {
            $stmt->bind_param('i', $verftId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $skrogList[] = $row;
                }
            }
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<!-- Hero image for verft-sok page -->
<div class="container">
    <section class="hero hero--small" style="background-image:url('../assets/img/verft_sok_1.jpg'); background-size:cover; background-position:center;">
        <div class="hero-overlay"></div>
    </section>
</div>
<!--
<div class="hero hero--thin">Ny
    <picture class="hero-bg">
        <img src="<?= BASE_URL ?>/assets/img/hero/hero2.jpg" alt="SkipsWeb hero">
    </picture>
    <div class="hero-content">
        <h1>Verftsøk</h1>
        <p>Finn verft og se leveranser/skrogbygg.</p>
    </div>
    <div class="hero-overlay"></div>
</div> -->

<section class="container">
  <h1>Søk verft</h1>

  <form method="get" class="search-form" style="margin-bottom:1rem;">
    <label for="q">Verft/sted (min. 2 tegn)</label>
    <input type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="f.eks. Aker, Ulstein, Tønsberg ...">
    <button type="submit" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Søk</span></button>
    <label for="include_sted" style="display:inline-flex; align-items:center; gap:.35rem; margin:.25rem 0;">
      <input type="checkbox" id="include_sted" name="include_sted" value="1" <?php if ($includeSted): ?>checked<?php endif; ?>>
      Inkluder sted i søk
    </label>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <p>Angi minst 2 tegn.</p>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) >= 2): ?>
<?php
    $total_verftList   = is_array($verftList) ? count($verftList) : 0;
    $maxPage_verftList = $perPage > 0 ? (int)ceil($total_verftList / $perPage) : 1;
    if ($page > $maxPage_verftList) $page = $maxPage_verftList ?: 1;
    $offset_verftList  = ($page - 1) * $perPage;
    $verftList_paged   = array_slice($verftList, $offset_verftList, $perPage);

    if (!function_exists('build_page_url')) {
        function build_page_url($pageNum) {
            $params = $_GET;
            $params['page'] = $pageNum;
            $qs = http_build_query($params);
            return basename($_SERVER['PHP_SELF']) . ($qs ? ('?' . $qs) : '');
        }
    }
?>
    <h2>Verft funnet (<?= $total_verftList ?>)</h2>
    <?php if (!count($verftList)): ?>
      <p>Ingen treff.</p>
    <?php else: ?>
      <div id="verftliste" class="card centered-card">
          <div class="mdc-data-table table-wrap table-wrap--scroll center" style="display:flex;justify-content:center;">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit table--compact table--zebra">
            <thead>
              <tr class="mdc-data-table__header-row">
                <th class="mdc-data-table__header-cell">Verft</th>
                <th class="mdc-data-table__header-cell">Sted</th>
                <th class="mdc-data-table__header-cell">Nasjon</th>
              </tr>
            </thead>
            <tbody class="mdc-data-table__content">
              <?php foreach ($verftList_paged as $v): ?>
                <?php
                  $rowUrl = basename($_SERVER['PHP_SELF']) . '?q=' . urlencode($q) . ($includeSted ? '&include_sted=1' : '') . '&verft_id=' . (int)($v['Verft_ID'] ?? 0) . '#verftliste';
                  $rowStyle = 'cursor:pointer;';
                  $isSelected = ((int)($v['Verft_ID'] ?? 0) === (int)$verftId);
                  if ($isSelected) { $rowStyle = 'background:var(--accent);cursor:pointer;'; }
                ?>
                <tr class="mdc-data-table__row row-click" data-href="<?= h($rowUrl) ?>" tabindex="0" role="button" aria-label="Velg verft <?= h($v['VerftNavn'] ?? '') ?>" style="<?= h($rowStyle) ?>">
                  <td class="mdc-data-table__cell"><?= h($v['VerftNavn'] ?? '') ?></td>
                  <td class="mdc-data-table__cell"><?= h($v['Sted'] ?? '') ?></td>
                  <td class="mdc-data-table__cell"><?= h($v['Nasjon'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
        <script>
          (function(){
            var table = document.querySelector('#verftliste table');
            if(!table) return;
            table.addEventListener('click', function(ev){
              var a = ev.target.closest('a');
              if (a) return; // let native links work
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
          <span>Viser <?= $total_verftList ? ($offset_verftList+1) : 0 ?>–<?= min($offset_verftList+$perPage, $total_verftList) ?> av <?= $total_verftList ?></span>
          <?php if ($maxPage_verftList > 1): ?>
            <a class="btn-small" href="<?= h(build_page_url(1)) ?>#verftliste" <?php if($page<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>« Første</a>
            <a class="btn-small" href="<?= h(build_page_url(max(1,$page-1))) ?>#verftliste" <?php if($page<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>‹ Forrige</a>
            <span>Side <?= $page ?> / <?= $maxPage_verftList ?></span>
            <a class="btn-small" href="<?= h(build_page_url(min($maxPage_verftList,$page+1))) ?>#verftliste" <?php if($page>=$maxPage_verftList): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Neste ›</a>
            <a class="btn-small" href="<?= h(build_page_url($maxPage_verftList)) ?>#verftliste" <?php if($page>=$maxPage_verftList): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Siste »</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (count($verftList)): ?>
      <h3>Levert av valgt verft (<?= count($leveranseList) ?>)</h3>
      <?php if (!count($leveranseList)): ?>
        <p>Ingen leveranser funnet.</p>
      <?php else: ?>
        <?php
          $total_lev   = is_array($leveranseList) ? count($leveranseList) : 0;
          $maxPage_lev = $perPage_vessel > 0 ? (int)ceil($total_lev / $perPage_vessel) : 1;
          if ($page_lev > $maxPage_lev) { $page_lev = $maxPage_lev ?: 1; }
          $offset_lev  = ($page_lev - 1) * $perPage_vessel;
          $leveranseList_paged = array_slice($leveranseList, $offset_lev, $perPage_vessel);
          if (!function_exists('build_page_url_lev')) {
            function build_page_url_lev($pageNum) {
              $params = $_GET;
              $params['page_lev'] = $pageNum;
              $qs = http_build_query($params);
              return basename($_SERVER['PHP_SELF']) . ($qs ? ('?' . $qs) : '');
            }
          }
        ?>
        <?php $qsVID = http_build_query(['verft_id' => $verftId]); $printLev = rtrim(BASE_URL,'/') . '/user/print_verft_sok.php?section=leveranse&' . $qsVID; $csvLev = rtrim(BASE_URL,'/') . '/user/export_verft_sok.php?section=leveranse&' . $qsVID; ?>
        <div class="export-actions">
          <a class="mdc-button mdc-button--raised btn" href="<?= h($printLev . '&orient=P') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 portrett)</span></a>
          <a class="mdc-button mdc-button--raised btn" href="<?= h($printLev . '&orient=L') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 landskap)</span></a>
          <a class="mdc-button mdc-button--raised btn" href="<?= h($csvLev) ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Last ned CSV</span></a>
        </div>
        <div id="leveranseliste" class="card centered-card">
          <div class="mdc-data-table table-wrap table-wrap--scroll center">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit table--compact table--zebra">
              <thead>
                <tr class="mdc-data-table__header-row">
                  <th class="mdc-data-table__header-cell">Byggenr</th>
                  <th class="mdc-data-table__header-cell">År/mnd</th>
                  <th class="mdc-data-table__header-cell">Navn</th>
                  <th class="mdc-data-table__header-cell">Reg.havn</th>
                  <th class="mdc-data-table__header-cell">Rederi</th>
                  <th class="mdc-data-table__header-cell">Nybygg</th>
                </tr>
              </thead>
              <tbody class="mdc-data-table__content">
                <?php foreach ($leveranseList_paged as $r): ?>
                  <?php
                    $year  = isset($r['YearSpes']) ? (int)$r['YearSpes'] : 0;
                    $month = isset($r['MndSpes']) ? (int)$r['MndSpes'] : 0;
                    $ym    = $year ? ($year . '/' . ($month ? str_pad((string)$month, 2, '0', STR_PAD_LEFT) : '')) : '';
                    $navn  = trim((string)($r['TypeFork'] ?? ''));
                    $navn  = $navn !== '' ? ($navn . ' ') : '';
                    $navn .= (string)($r['FartNavn'] ?? '');
                    $objId  = isset($r['FartObj_ID']) ? (int)$r['FartObj_ID'] : 0;
                    $navnId = isset($r['FartTid_ID'])  ? (int)$r['FartTid_ID']  : 0;
                    $levRowUrl = ($objId > 0 && $navnId > 0) ? h('fart_detalj.php?obj_id=' . $objId . '&tid_id=' . $navnId . '#fartoyliste') : '';
                  ?>
                  <tr class="mdc-data-table__row" <?= $levRowUrl ? 'data-href="' . $levRowUrl . '" style="cursor:pointer;"' : '' ?>>
                    <td class="mdc-data-table__cell"><?= h($r['Byggenr'] ?? '') ?></td>
                    <td class="mdc-data-table__cell"><?= h($ym) ?></td>
                    <td class="mdc-data-table__cell"><?= h($navn) ?></td>
                    <td class="mdc-data-table__cell"><?= h($r['RegHavn'] ?? '') ?></td>
                    <td class="mdc-data-table__cell"><?= h($r['Rederi'] ?? '') ?></td>
                    <td class="mdc-data-table__cell">
                      <?php if (isset($r['Objekt']) && (int)$r['Objekt'] === 1): ?>
                        <span title="Navnet peker på et eget objekt (nybygg)">●</span>
                      <?php else: ?>
                        <span class="muted" aria-hidden="true">•</span>
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
          <span>Viser <?= $total_lev ? ($offset_lev+1) : 0 ?>-<?= min($offset_lev+$perPage_vessel, $total_lev) ?> av <?= $total_lev ?></span>
          <?php if (($maxPage_lev ?? 1) > 1): ?>
            <a class="btn-small" href="<?= h(build_page_url_lev(1)) ?>#leveranseliste" <?php if(($page_lev ?? 1)<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>« Første</a>
            <a class="btn-small" href="<?= h(build_page_url_lev(max(1,($page_lev ?? 1)-1))) ?>#leveranseliste" <?php if(($page_lev ?? 1)<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>‹ Forrige</a>
            <span>Side <?= $page_lev ?? 1 ?> / <?= $maxPage_lev ?></span>
            <a class="btn-small" href="<?= h(build_page_url_lev(min($maxPage_lev,($page_lev ?? 1)+1))) ?>#leveranseliste" <?php if(($page_lev ?? 1)>=$maxPage_lev): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Neste ›</a>
            <a class="btn-small" href="<?= h(build_page_url_lev($maxPage_lev)) ?>#leveranseliste" <?php if(($page_lev ?? 1)>=$maxPage_lev): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Siste »</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <h3>Skrog bygget ved valgt verft (<?= count($skrogList) ?>)</h3>
      <?php if (!count($skrogList)): ?>
        <p>Ingen skrogbygg funnet.</p>
      <?php else: ?>
        <?php
          $total_skrog   = is_array($skrogList) ? count($skrogList) : 0;
          $maxPage_skrog = $perPage_vessel > 0 ? (int)ceil($total_skrog / $perPage_vessel) : 1;
          if ($page_skrog > $maxPage_skrog) { $page_skrog = $maxPage_skrog ?: 1; }
          $offset_skrog  = ($page_skrog - 1) * $perPage_vessel;
          $skrogList_paged = array_slice($skrogList, $offset_skrog, $perPage_vessel);
          if (!function_exists('build_page_url_skrog')) {
            function build_page_url_skrog($pageNum) {
              $params = $_GET;
              $params['page_skrog'] = $pageNum;
              $qs = http_build_query($params);
              return basename($_SERVER['PHP_SELF']) . ($qs ? ('?' . $qs) : '');
            }
          }
        ?>
        <?php $qsVID = http_build_query(['verft_id' => $verftId]); $printSkrog = rtrim(BASE_URL,'/') . '/user/print_verft_sok.php?section=skrog&' . $qsVID; $csvSkrog = rtrim(BASE_URL,'/') . '/user/export_verft_sok.php?section=skrog&' . $qsVID; ?>
        <div class="export-actions">
          <a class="mdc-button mdc-button--raised btn" href="<?= h($printSkrog . '&orient=P') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 portrett)</span></a>
          <a class="mdc-button mdc-button--raised btn" href="<?= h($printSkrog . '&orient=L') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 landskap)</span></a>
          <a class="mdc-button mdc-button--raised btn" href="<?= h($csvSkrog) ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Last ned CSV</span></a>
        </div>
        <div id="skrogliste" class="card centered-card">
          <div class="mdc-data-table table-wrap table-wrap--scroll center">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit table--compact table--zebra">
              <thead>
                <tr class="mdc-data-table__header-row">
                  <th class="mdc-data-table__header-cell">Byggenr</th>
                  <th class="mdc-data-table__header-cell">År/mnd</th>
                  <th class="mdc-data-table__header-cell">Navn</th>
                  <th class="mdc-data-table__header-cell">Reg.havn</th>
                  <th class="mdc-data-table__header-cell">Rederi</th>
                  <th class="mdc-data-table__header-cell">Nybygg</th>
                </tr>
              </thead>
              <tbody class="mdc-data-table__content">
                <?php foreach ($skrogList_paged as $r): ?>
                  <?php
                    $year  = isset($r['YearSpes']) ? (int)$r['YearSpes'] : 0;
                    $month = isset($r['MndSpes']) ? (int)$r['MndSpes'] : 0;
                    $ym    = $year ? ($year . '/' . ($month ? str_pad((string)$month, 2, '0', STR_PAD_LEFT) : '')) : '';
                    $navn  = trim((string)($r['TypeFork'] ?? ''));
                    $navn  = $navn !== '' ? ($navn . ' ') : '';
                    $navn .= (string)($r['FartNavn'] ?? '');
                    $objId  = isset($r['FartObj_ID']) ? (int)$r['FartObj_ID'] : 0;
                    $navnId = isset($r['FartTid_ID'])  ? (int)$r['FartTid_ID']  : 0;
                    $skrRowUrl = ($objId > 0 && $navnId > 0) ? h('fart_detalj.php?obj_id=' . $objId . '&tid_id=' . $navnId . '#fartoyliste') : '';
                  ?>
                  <tr class="mdc-data-table__row" <?= $skrRowUrl ? 'data-href="' . $skrRowUrl . '" style="cursor:pointer;"' : '' ?>>
                    <td class="mdc-data-table__cell"><?= h($r['Byggenr'] ?? '') ?></td>
                    <td class="mdc-data-table__cell"><?= h($ym) ?></td>
                    <td class="mdc-data-table__cell"><?= h($navn) ?></td>
                    <td class="mdc-data-table__cell"><?= h($r['RegHavn'] ?? '') ?></td>
                    <td class="mdc-data-table__cell"><?= h($r['Rederi'] ?? '') ?></td>
                    <td class="mdc-data-table__cell">
                      <?php if (isset($r['Objekt']) && (int)$r['Objekt'] === 1): ?>
                        <span title="Navnet peker på et eget objekt (nybygg)">●</span>
                      <?php else: ?>
                        <span class="muted" aria-hidden="true">•</span>
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
          <span>Viser <?= $total_skrog ? ($offset_skrog+1) : 0 ?>-<?= min($offset_skrog+$perPage_vessel, $total_skrog) ?> av <?= $total_skrog ?></span>
          <?php if (($maxPage_skrog ?? 1) > 1): ?>
            <a class="btn-small" href="<?= h(build_page_url_skrog(1)) ?>#skrogliste" <?php if(($page_skrog ?? 1)<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>« Første</a>
            <a class="btn-small" href="<?= h(build_page_url_skrog(max(1,($page_skrog ?? 1)-1))) ?>#skrogliste" <?php if(($page_skrog ?? 1)<=1): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>‹ Forrige</a>
            <span>Side <?= $page_skrog ?? 1 ?> / <?= $maxPage_skrog ?></span>
            <a class="btn-small" href="<?= h(build_page_url_skrog(min($maxPage_skrog,($page_skrog ?? 1)+1))) ?>#skrogliste" <?php if(($page_skrog ?? 1)>=$maxPage_skrog): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Neste ›</a>
            <a class="btn-small" href="<?= h(build_page_url_skrog($maxPage_skrog)) ?>#skrogliste" <?php if(($page_skrog ?? 1)>=$maxPage_skrog): ?>style="pointer-events:none;opacity:.5"<?php endif; ?>>Siste »</a>
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
