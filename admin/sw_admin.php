<?php
// admin/sw_admin.php
// Administrasjon: søk, list og håndter fartøyer. Kun for admin-brukere.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
// Sørg for BASE_URL alltid finnes lokalt
$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tilgangskontroll
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/');
    exit;
}

// Hjelpere
// uses global h() from includes/functions.php
function val($arr, $key, $def = '') { return isset($arr[$key]) ? $arr[$key] : $def; }

// Parametre
$nasjonId = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0;
$q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$doSearch = ($q !== '' || $nasjonId !== 0);

// Hent nasjoner for filter
$nasjoner = [];
$sqlN = "SELECT Nasjon_ID, Nasjon FROM tblznasjon WHERE Nasjon IS NOT NULL AND Nasjon <> '' ORDER BY Nasjon";
if ($resN = $conn->query($sqlN)) {
    while ($row = $resN->fetch_assoc()) { $nasjoner[] = $row; }
    $resN->free();
}

// Søk
$rows = [];
if ($doSearch) {
    $sql = "
        SELECT
            curr.FartTid_ID,
            curr.FartObj_ID,
            curr.FartNavn,
            curr.RegHavn,
            curr.Kallesignal,
            curr.Nasjon_ID   AS TNat,
            n.Nasjon,
            curr.Objekt      AS IsOriginalNow,
            o.Bygget,
            zft.TypeFork
        FROM tblfarttid AS curr
        LEFT JOIN tblfartobj   AS o   ON o.FartObj_ID  = curr.FartObj_ID
        LEFT JOIN tblfartspes  AS fs  ON fs.FartObj_ID = curr.FartObj_ID
        LEFT JOIN tblzfarttype AS zft ON zft.FartType_ID = fs.FartType_ID
        LEFT JOIN tblznasjon   AS n   ON n.Nasjon_ID   = curr.Nasjon_ID
        WHERE curr.FartTid_ID = (
            SELECT t2.FartTid_ID
            FROM tblfarttid t2
            WHERE t2.FartObj_ID = curr.FartObj_ID
            ORDER BY COALESCE(t2.YearTid,0) DESC, COALESCE(t2.MndTid,0) DESC, t2.FartTid_ID DESC
            LIMIT 1
        )
          AND (? = 0 OR curr.Nasjon_ID = ?)
          AND (? = '' OR curr.FartNavn LIKE CONCAT('%', ?, '%'))
        ORDER BY curr.FartNavn ASC
        LIMIT 200
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiss', $nasjonId, $nasjonId, $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } $res->free(); }
    $stmt->close();
}

include __DIR__ . '/../includes/header.php';
// Skjul "Administrer fartøyer" i menyen når vi er på akkurat denne siden
$__HIDE_ADMIN_FANE__ = true;
include __DIR__ . '/../includes/menu.php';
?>
<!-- Responsive image box (contain, no crop) -->
    <div class="container" style="display:flex; justify-content:center;">
      <div class="image-box">
        <?php
          // Fallback-bilde
          $imgCandidate = '/assets/img/placeholder2.jpg';

          // 3) Relativ URL hvis siden ligger i /user/
          $imgRel = (substr($imgCandidate, 0, 1) === '/') ? ('..' . $imgCandidate) : $imgCandidate;

          // 4) Alt‑tekst (prøv å bruke type  navn hvis det finnes)
          $altText = trim(
            (string)($main['TypeFork'] ?? ($main['FartType'] ?? '')) . ' ' .
            (string)($main['FartNavn'] ?? 'Fartøy')
          );
          if ($altText === '') { $altText = 'Fartøybilde'; }
        ?>
        <img src="<?= h($imgRel) ?>" alt="<?= h($altText) ?>">
      </div>
    </div>
    
    <h1>Administrasjon av fartøyer, verft og parametre</h1>
<div class="container mt-3">
  <div class="card centered-card">
    <h2 class="h5" style="margin-top:0; text-align:center;">Velg prosess for fartøyer</h2>
    <div class="d-flex flex-wrap gap-2" style="justify-content:center;">
      <a href="<?= $base ?>/admin/fart_nytt.php" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Opprett nytt fartøy</span></a>
      <a href="<?= $base ?>/admin/fart_nyspes.php" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Nye spesifikasjoner</span></a>
      <a href="<?= $base ?>/admin/fart_nytid.php" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Nytt navn/eierforhold</span></a>
      <h4>Benyttes for nybygg, ombygninger av eksisterende fartøyer, eller endringer i eksisterende fartøys navn, eierforhold o.l.</h4>
 
    </div>
  </div>
  <form method="get" class="search-form" style="margin-bottom:1rem; text-align:center;">
    <label for="q">Del av navn:&nbsp;</label>
    <input type="text" id="q" name="q" value="<?= h($q) ?>" />
    <label for="nasjon_id">fra nasjon</label>
    <select name="nasjon_id" id="nasjon_id">
      <option value="0"<?= $nasjonId === 0 ? ' selected' : '' ?>>Alle nasjoner</option>
      <?php foreach ($nasjoner as $r): ?>
        <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= $nasjonId === (int)$r['Nasjon_ID'] ? ' selected' : '' ?>><?= h($r['Nasjon']) ?></option>
      <?php endforeach; ?>
    </select>
  
    <button type="submit" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Søk fartøy å korrigere</span></button>
    <?php $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : ''; ?> 
  </form>
  <h4>Skriv inn del av navn  og/eller velg nasjon for å søke etter fartøy å korrigere.</h4>
  <p style="text-align:center; margin-top:1rem;">
        
  </p>
  <p style="text-align:center; margin-top:1rem;">
        <a href="<?= $base ?>/admin/verftadmin.php" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Verft</span></a>
  </p>
  

    <p style="text-align:center; margin-top:1rem;">
      <a class="mdc-button mdc-button--raised btn" href="<?= $base ?>/admin/param_admin.php"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Parametertabeller</span></a>
    </p>
  <?php if ($doSearch && $rows): ?>
    <div class="mdc-data-table table-responsive">
      <div class="mdc-data-table__table-container">
      <table class="mdc-data-table__table table table-striped table-sm">
        <thead>
          <tr class="mdc-data-table__header-row">
            <th class="mdc-data-table__header-cell">Type</th>
            <th class="mdc-data-table__header-cell">Navn</th>
            <th class="mdc-data-table__header-cell">Reg.havn</th>
            <th class="mdc-data-table__header-cell">Flaggstat</th>
            <th class="mdc-data-table__header-cell">Bygget</th>
            <th class="mdc-data-table__header-cell">Kallesignal</th>
            <th class="mdc-data-table__header-cell">Handlinger</th>
          </tr>
        </thead>
        <tbody class="mdc-data-table__content">
        <?php foreach ($rows as $r): ?>
          <tr class="mdc-data-table__row">
            <td class="mdc-data-table__cell"><?= h(val($r, 'TypeFork')) ?></td>
            <td class="mdc-data-table__cell">
              <?= h(val($r, 'FartNavn')) ?>
              <?php if ((int)val($r,'IsOriginalNow',0) === 1): ?>
                <span title="Navnet tilhører opprinnelig fartøy">•</span>
              <?php endif; ?>
            </td>
            <td class="mdc-data-table__cell"><?= h(val($r,'RegHavn')) ?></td>
            <td class="mdc-data-table__cell"><?= h(val($r,'Nasjon')) ?></td>
            <td class="mdc-data-table__cell"><?= h(val($r,'Bygget')) ?></td>
            <td class="mdc-data-table__cell"><?= h(val($r,'Kallesignal')) ?></td>
            <td class="mdc-data-table__cell">
              <?php $objId = (int)val($r,'FartObj_ID',0); $tidId = (int)val($r,'FartTid_ID',0); ?>
              <?php if ($objId > 0 && $tidId > 0): ?>
                <a class="btn-small" href="fart_edit.php?obj_id=<?= $objId ?>&tid_id=<?= $tidId ?>">Korriger</a>
                <button type="button" class="btn-small" onclick="openEndrePopup(<?= $objId ?>, <?= $tidId ?>, '<?= h(val($r,'FartNavn')) ?>')">Endre</button>
                <a class="btn-small" href="fart_delete.php?obj_id=<?= $objId ?>&tid_id=<?= $tidId ?>" onclick="return confirm('Er du sikker på at du vil slette dette fartøyet?');">Slett</a>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php elseif ($doSearch): ?>
    <p>Ingen treff.</p>
  <?php else: ?>
  <?php endif; ?>
  
</div>
<!-- Pop-up for Type B endringer -->
<div class="modal-overlay" id="endre-modal" style="display:none;">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="endre-modal-title" style="max-width:500px;">
    <h3 id="endre-modal-title">Velg endringstype</h3>
    <p id="endre-modal-navn" class="text-muted"></p>
    <input type="hidden" id="endre-obj-id" value="">
    <input type="hidden" id="endre-tid-id" value="">
    <div style="margin:1rem 0;">
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="chk-ombygging" value="1">
        <label class="form-check-label" for="chk-ombygging"><strong>Ombygging</strong> — ny spesifikasjon + ny tidsrad</label>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="chk-navning" value="1">
        <label class="form-check-label" for="chk-navning"><strong>Endre navn</strong> — navneendring (FartNavn/PennantTiln)</label>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="chk-eierskifte" value="1">
        <label class="form-check-label" for="chk-eierskifte"><strong>Endre eierskap</strong> — eierskifte (Rederi m.m.)</label>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="chk-annet" value="1">
        <label class="form-check-label" for="chk-annet"><strong>Andre endringer</strong> — nasjon, reghavn, kallesignal, MMSI, fiskerinr</label>
      </div>
    </div>
    <div style="display:flex; gap:10px; justify-content:center;">
      <button type="button" class="mdc-button mdc-button--raised btn btn-primary" id="endre-go" disabled><span class="mdc-button__ripple"></span><span class="mdc-button__label">Gå videre</span></button>
      <button type="button" class="mdc-button mdc-button--raised btn" id="endre-cancel"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Avbryt</span></button>
    </div>
  </div>
</div>
<script>
(function(){
  var modal = document.getElementById('endre-modal');
  var objField = document.getElementById('endre-obj-id');
  var tidField = document.getElementById('endre-tid-id');
  var navnField = document.getElementById('endre-modal-navn');
  var goBtn = document.getElementById('endre-go');
  var cancelBtn = document.getElementById('endre-cancel');
  var chkOmbygg = document.getElementById('chk-ombygging');
  var chkNavn = document.getElementById('chk-navning');
  var chkEier = document.getElementById('chk-eierskifte');
  var chkAnnet = document.getElementById('chk-annet');
  var allChk = [chkOmbygg, chkNavn, chkEier, chkAnnet];

  window.openEndrePopup = function(objId, tidId, navn) {
    objField.value = objId;
    tidField.value = tidId;
    navnField.textContent = 'Fartøy: ' + navn;
    allChk.forEach(function(c){ c.checked = false; });
    goBtn.disabled = true;
    modal.style.display = 'flex';
  };

  allChk.forEach(function(c){
    c.addEventListener('change', function(){
      goBtn.disabled = !allChk.some(function(cb){ return cb.checked; });
    });
  });

  cancelBtn.addEventListener('click', function(){ modal.style.display = 'none'; });
  modal.addEventListener('click', function(e){ if(e.target === modal) modal.style.display = 'none'; });

  goBtn.addEventListener('click', function(){
    var tidId = tidField.value;
    var params = [];
    if (chkOmbygg.checked) {
      // Ombygging: gå til fart_nyspes.php med ev. flagg
      params.push('tid_id=' + tidId);
      if (chkNavn.checked) params.push('navning=1');
      if (chkEier.checked) params.push('eierskifte=1');
      if (chkAnnet.checked) params.push('annet=1');
      window.location.href = 'fart_nyspes.php?' + params.join('&');
    } else {
      // Kun tidsrad-endringer: gå til fart_nytid.php med flagg
      params.push('tid_id=' + tidId);
      if (chkNavn.checked) params.push('navning=1');
      if (chkEier.checked) params.push('eierskifte=1');
      if (chkAnnet.checked) params.push('annet=1');
      window.location.href = 'fart_nytid.php?' + params.join('&');
    }
  });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
