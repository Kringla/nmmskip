<?php
declare(strict_types=1);
// admin/fart_delete.php
// Bekreft og utfør sletting av fartøy eller enkeltrader. Kun admin-brukere.
// Ref: SkipsWebLiv.md, seksjon "Sletting av rader"

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

$conn = db_rw();

// Parametre: obj_id + tid_id (tidsrad som skal slettes)
$objId = isset($_GET['obj_id']) ? (int)$_GET['obj_id'] : 0;
$tidId = isset($_GET['tid_id']) ? (int)$_GET['tid_id'] : 0;
if ($objId <= 0 || $tidId <= 0) {
    http_response_code(400);
    echo "Ugyldige parametre.";
    exit;
}

// Hent tidsraden
$stmt = $conn->prepare(
    "SELECT t.FartTid_ID, t.FartObj_ID, t.FartSpes_ID, t.Objekt, t.FartNavn,
            zft.TypeFork
     FROM tblfarttid t
     LEFT JOIN tblzfarttype zft ON zft.FartType_ID = t.FartType_ID
     WHERE t.FartTid_ID = ? AND t.FartObj_ID = ?"
);
$stmt->bind_param('ii', $tidId, $objId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo "Fant ingen data å slette.";
    exit;
}

$isObject = (int)$row['Objekt'] === 1;
$fartNavn = $row['FartNavn'] ?? '';
$typeFork = $row['TypeFork'] ?? '';

// Sjekk referanser i tblxfartlink, tblxnmmfoto og tblxdigmuseum for denne tidsraden
function countRefs(mysqli $conn, int $tidId): array
{
    $refs = [];
    // tblxfartlink
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tblxfartlink WHERE FartTid_ID = ?");
    $stmt->bind_param('i', $tidId);
    $stmt->execute();
    $stmt->bind_result($c);
    $stmt->fetch();
    $stmt->close();
    if ($c > 0) { $refs['tblxfartlink'] = $c; }

    // tblxdigmuseum
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tblxdigmuseum WHERE FartTid_ID = ?");
    $stmt->bind_param('i', $tidId);
    $stmt->execute();
    $stmt->bind_result($c);
    $stmt->fetch();
    $stmt->close();
    if ($c > 0) { $refs['tblxdigmuseum'] = $c; }

    return $refs;
}

// Ved sletting av hele objektet: samle opp alle tidsrad-IDer og sjekk referanser
$allTidIds = [];
$allRefs = [];
if ($isObject) {
    $stmt = $conn->prepare("SELECT FartTid_ID FROM tblfarttid WHERE FartObj_ID = ?");
    $stmt->bind_param('i', $objId);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($tr = $r->fetch_assoc()) {
        $allTidIds[] = (int)$tr['FartTid_ID'];
    }
    $stmt->close();
    foreach ($allTidIds as $tid) {
        $tidRefs = countRefs($conn, $tid);
        if ($tidRefs !== []) {
            $allRefs[$tid] = $tidRefs;
        }
    }
} else {
    $allTidIds = [$tidId];
    $tidRefs = countRefs($conn, $tidId);
    if ($tidRefs !== []) {
        $allRefs[$tidId] = $tidRefs;
    }
}

$hasRefs = $allRefs !== [];
$errorMsg = '';
$cascadeConfirmed = isset($_POST['cascade']) && $_POST['cascade'] === '1';

// POST: utfør sletting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, (string)$_POST['csrf_token'])) {
        $errorMsg = 'Ugyldig forespørsel (CSRF).';
    } elseif ($hasRefs && !$cascadeConfirmed) {
        $errorMsg = 'Det finnes referanser i tilknyttede tabeller. Bekreft kaskadesletting.';
    } else {
        $conn->begin_transaction();
        try {
            if ($isObject) {
                // Kaskadesletting: tblx-tabeller → tblfarttid → tblfartspes → tblfartobj
                foreach ($allTidIds as $tid) {
                    $stmt = $conn->prepare("DELETE FROM tblxfartlink WHERE FartTid_ID = ?");
                    $stmt->bind_param('i', $tid);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("DELETE FROM tblxdigmuseum WHERE FartTid_ID = ?");
                    $stmt->bind_param('i', $tid);
                    $stmt->execute();
                    $stmt->close();
                }

                $stmt = $conn->prepare("DELETE FROM tblfarttid WHERE FartObj_ID = ?");
                $stmt->bind_param('i', $objId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM tblfartspes WHERE FartObj_ID = ?");
                $stmt->bind_param('i', $objId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM tblfartobj WHERE FartObj_ID = ?");
                $stmt->bind_param('i', $objId);
                $stmt->execute();
                $stmt->close();
            } else {
                // Slett kun denne tidsraden (+ tilhørende tblx-rader)
                $stmt = $conn->prepare("DELETE FROM tblxfartlink WHERE FartTid_ID = ?");
                $stmt->bind_param('i', $tidId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM tblxdigmuseum WHERE FartTid_ID = ?");
                $stmt->bind_param('i', $tidId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM tblfarttid WHERE FartTid_ID = ?");
                $stmt->bind_param('i', $tidId);
                $stmt->execute();
                $stmt->close();
            }
            $conn->commit();

            $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
            header('Location: ' . $base . '/admin/sw_admin.php?deleted=1');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = 'Sletting feilet: ' . $e->getMessage();
        }
    }
}

$page_title = 'Slett fartøy';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-3">
  <h1>Slett fartøy</h1>

  <?php if ($errorMsg): ?>
    <div class="alert alert-danger"><?= h($errorMsg) ?></div>
  <?php endif; ?>

  <div class="mdc-card card" style="padding:1rem; max-width:700px; margin:0 auto;">
    <p>
      Du er i ferd med å slette
      <strong><?= h($typeFork . ' ' . $fartNavn) ?></strong>
      (Objekt-ID <?= $objId ?>, Tidsrad-ID <?= $tidId ?>).
    </p>

    <?php if ($isObject): ?>
      <p class="text-danger">
        Dette er et <em>objekt</em>. Alle tilhørende tidsrader (<?= count($allTidIds) ?> stk),
        spesifikasjoner, lenker og museumsreferanser vil også bli slettet.
      </p>
    <?php else: ?>
      <p>Kun denne tidsraden vil bli slettet.</p>
    <?php endif; ?>

    <?php if ($hasRefs): ?>
      <div class="alert alert-warning">
        <strong>Referanser funnet:</strong> Følgende tilknyttede rader vil bli slettet sammen med tidsradene:
        <ul>
          <?php foreach ($allRefs as $tid => $tables): ?>
            <?php foreach ($tables as $table => $count): ?>
              <li>Tidsrad #<?= $tid ?>: <?= $count ?> rad(er) i <code><?= h($table) ?></code></li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" style="margin-top:1rem; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
      <input type="hidden" name="confirm" value="yes">
      <?php if ($hasRefs): ?>
        <div class="form-check mb-3" style="width:100%; text-align:center;">
          <input class="form-check-input" type="checkbox" id="cascade" name="cascade" value="1">
          <label class="form-check-label" for="cascade">
            Bekreft kaskadesletting av tilknyttede rader
          </label>
        </div>
      <?php endif; ?>
      <button type="submit" class="mdc-button mdc-button--raised btn btn-danger" <?= $hasRefs ? 'id="btnDelete" disabled' : '' ?>><span class="mdc-button__ripple"></span><span class="mdc-button__label">Bekreft sletting</span></button>
      <a href="<?= h(url('/admin/sw_admin.php')) ?>" class="mdc-button mdc-button--outlined btn btn-outline-secondary"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Avbryt</span></a>
    </form>
  </div>
</div>
<?php if ($hasRefs): ?>
<script>
(function(){
  var cb = document.getElementById('cascade');
  var btn = document.getElementById('btnDelete');
  if (cb && btn) {
    cb.addEventListener('change', function(){ btn.disabled = !cb.checked; });
  }
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
