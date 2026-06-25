<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin(); // Krever admin-rolle

$conn = db_rw();

if (!function_exists('stmt_infer_types')) {
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

// uses global h() from includes/functions.php

$errors = [];
$success = null;

// Hent nasjoner og linktyper til nedtrekk
$nasjoner = [];
$stmt = $conn->prepare("SELECT Nasjon_ID, Nasjon FROM tblznasjon ORDER BY Nasjon ASC");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $nasjoner[] = $row; }
$stmt->close();

$linktyper = [];
$stmt = $conn->prepare("SELECT LinkType_ID, LinkType FROM tblzlinktype ORDER BY LinkType ASC");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $linktyper[] = $row; }
$stmt->close();
$linkTypeMap = [];
foreach ($linktyper as $lt) {
    $linkTypeMap[(int)($lt['LinkType_ID'] ?? 0)] = $lt['LinkType'] ?? '';
}

// Finn modus
$isNew = (isset($_GET['new']) && $_GET['new'] === '1');

// Prosesser POST: lagre/oppdater verft og CRUD for lenker
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_verft') {
        $VerftNavn = trim((string)($_POST['VerftNavn'] ?? ''));
        if ($VerftNavn === '') $errors[] = 'Verftnavn må fylles ut.';

        $Sted = trim((string)($_POST['Sted'] ?? ''));
        $Nasjon_ID = isset($_POST['Nasjon_ID']) && $_POST['Nasjon_ID'] !== '' ? (int)$_POST['Nasjon_ID'] : null;
        $TidlID = $_POST['TidlID'] !== '' ? (int)$_POST['TidlID'] : null;
        $Etablert = $_POST['Etablert'] !== '' ? (int)$_POST['Etablert'] : null;
        $Nedlagt = $_POST['Nedlagt'] !== '' ? (int)$_POST['Nedlagt'] : null;
        $EtterID = $_POST['EtterID'] !== '' ? (int)$_POST['EtterID'] : null;
        $Merknad = trim((string)($_POST['Merknad'] ?? ''));

        if (!$errors) {
            $stmt = $conn->prepare("INSERT INTO tblverft (VerftNavn, Sted, Nasjon_ID, TidlID, Etablert, Nedlagt, EtterID, Merknad)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertValues = [
                $VerftNavn,
                $Sted !== '' ? $Sted : null,
                $Nasjon_ID,
                $TidlID,
                $Etablert,
                $Nedlagt,
                $EtterID,
                $Merknad !== '' ? $Merknad : null
            ];
            stmt_bind_params($stmt, $insertValues);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                header("Location: " . BASE_URL . "/admin/verft.php?verft_id=" . $newId . "&msg=opprettet");
                exit;
            } else {
                $errors[] = 'Klarte ikke å opprette verft.';
            }
            $stmt->close();
        }
    }
    elseif ($action === 'update_verft') {
        $verft_id = (int)($_POST['Verft_ID'] ?? 0);
        if ($verft_id <= 0) { $errors[] = 'Mangler Verft_ID.'; }
        $VerftNavn = trim((string)($_POST['VerftNavn'] ?? ''));
        if ($VerftNavn === '') $errors[] = 'Verftnavn må fylles ut.';

        $Sted = trim((string)($_POST['Sted'] ?? ''));
        $Nasjon_ID = isset($_POST['Nasjon_ID']) && $_POST['Nasjon_ID'] !== '' ? (int)$_POST['Nasjon_ID'] : null;
        $TidlID = $_POST['TidlID'] !== '' ? (int)$_POST['TidlID'] : null;
        $Etablert = $_POST['Etablert'] !== '' ? (int)$_POST['Etablert'] : null;
        $Nedlagt = $_POST['Nedlagt'] !== '' ? (int)$_POST['Nedlagt'] : null;
        $EtterID = $_POST['EtterID'] !== '' ? (int)$_POST['EtterID'] : null;
        $Merknad = trim((string)($_POST['Merknad'] ?? ''));

        if (!$errors) {
            $stmt = $conn->prepare("UPDATE tblverft
                                  SET VerftNavn=?, Sted=?, Nasjon_ID=?, TidlID=?, Etablert=?, Nedlagt=?, EtterID=?, Merknad=?
                                  WHERE Verft_ID=?");
            $updateValues = [
                $VerftNavn,
                $Sted !== '' ? $Sted : null,
                $Nasjon_ID,
                $TidlID,
                $Etablert,
                $Nedlagt,
                $EtterID,
                $Merknad !== '' ? $Merknad : null,
                $verft_id
            ];
            stmt_bind_params($stmt, $updateValues);
            if ($stmt->execute()) {
                header("Location: " . BASE_URL . "/admin/verft.php?verft_id=" . $verft_id . "&msg=lagret");
                exit;
            } else {
                $errors[] = 'Klarte ikke å lagre endringer.';
            }
            $stmt->close();
        }
    }
    elseif ($action === 'add_link') {
        $verft_id = (int)($_POST['Verft_ID'] ?? 0);
        $LinkType_ID = $_POST['LinkType_ID'] !== '' ? (int)$_POST['LinkType_ID'] : null;
        $LinkType = trim((string)($_POST['LinkType'] ?? '')); // valgfri fritekst
        $LinkInnh = trim((string)($_POST['LinkInnh'] ?? ''));
        $Link = trim((string)($_POST['Link'] ?? ''));

        if ($verft_id <= 0) $errors[] = 'Mangler Verft_ID for lenke.';
        if ($Link === '') $errors[] = 'Lenke-URL må fylles ut.';

        if (!$errors) {
            if ($LinkType === '' && $LinkType_ID !== null) {
                $LinkType = $linkTypeMap[$LinkType_ID] ?? '';
            }
            $stmt = $conn->prepare("INSERT INTO tblxverftlink (Verft_ID, LinkType_ID, LinkType, LinkInnh, Link) VALUES (?, ?, ?, ?, ?)");
            $linkValues = [
                $verft_id,
                $LinkType_ID,
                $LinkType !== '' ? $LinkType : null,
                $LinkInnh !== '' ? $LinkInnh : null,
                $Link
            ];
            stmt_bind_params($stmt, $linkValues);
            if ($stmt->execute()) {
                header("Location: " . BASE_URL . "/admin/verft.php?verft_id=" . $verft_id . "&msg=lenke_lagt_til");
                exit;
            } else {
                $errors[] = 'Klarte ikke å legge til lenke.';
            }
            $stmt->close();
        }
    }
    elseif ($action === 'update_link') {
        $verft_id = (int)($_POST['Verft_ID'] ?? 0);
        $VerftLk_ID = (int)($_POST['VerftLk_ID'] ?? 0);
        $LinkType_ID = $_POST['LinkType_ID'] !== '' ? (int)$_POST['LinkType_ID'] : null;
        $LinkType = trim((string)($_POST['LinkType'] ?? ''));
        $LinkInnh = trim((string)($_POST['LinkInnh'] ?? ''));
        $Link = trim((string)($_POST['Link'] ?? ''));
        if ($verft_id <= 0 || $VerftLk_ID <= 0) $errors[] = 'Mangler id for oppdatering.';

        if (!$errors) {
            if ($LinkType === '' && $LinkType_ID !== null) {
                $LinkType = $linkTypeMap[$LinkType_ID] ?? '';
            }
            $stmt = $conn->prepare("UPDATE tblxverftlink SET LinkType_ID=?, LinkType=?, LinkInnh=?, Link=? WHERE VerftLk_ID=? AND Verft_ID=?");
            $updateLinkValues = [
                $LinkType_ID,
                $LinkType !== '' ? $LinkType : null,
                $LinkInnh !== '' ? $LinkInnh : null,
                $Link,
                $VerftLk_ID,
                $verft_id
            ];
            stmt_bind_params($stmt, $updateLinkValues);
            if ($stmt->execute()) {
                header("Location: " . BASE_URL . "/admin/verft.php?verft_id=" . $verft_id . "&msg=lenke_endret");
                exit;
            } else {
                $errors[] = 'Klarte ikke å oppdatere lenke.';
            }
            $stmt->close();
        }
    }
    elseif ($action === 'delete_link') {
        $verft_id = (int)($_POST['Verft_ID'] ?? 0);
        $VerftLk_ID = (int)($_POST['VerftLk_ID'] ?? 0);

        if ($verft_id <= 0 || $VerftLk_ID <= 0) $errors[] = 'Mangler id for sletting.';

        if (!$errors) {
            $stmt = $conn->prepare("DELETE FROM tblxverftlink WHERE VerftLk_ID=? AND Verft_ID=?");
            $deleteValues = [$VerftLk_ID, $verft_id];
            stmt_bind_params($stmt, $deleteValues);
            if ($stmt->execute()) {
                header("Location: " . BASE_URL . "/admin/verft.php?verft_id=" . $verft_id . "&msg=lenke_slettet");
                exit;
            } else {
                $errors[] = 'Klarte ikke å slette lenke.';
            }
            $stmt->close();
        }
    }
}

// Hent verft-data hvis ikke "new"
$verft = null;
$verft_id = 0;
if (!$isNew) {
    $verft_id = isset($_GET['verft_id']) ? (int)$_GET['verft_id'] : 0;
    if ($verft_id > 0) {
        $stmt = $conn->prepare("SELECT v.*, n.Nasjon
                              FROM tblverft v
                              LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID
                              WHERE v.Verft_ID = ?");
        stmt_bind_params($stmt, [$verft_id]);
        $stmt->execute();
        $res = $stmt->get_result();
        $verft = $res->fetch_assoc();
        $stmt->close();
        if (!$verft) { $errors[] = 'Verft ikke funnet.'; }
    } else {
        $errors[] = 'Mangler verft_id.';
    }
}

// Hent verft-lenker
$verftlenker = [];
if (!$isNew && $verft_id > 0) {
    $stmt = $conn->prepare("SELECT vl.* FROM tblxverftlink vl WHERE vl.Verft_ID = ? ORDER BY vl.VerftLk_ID ASC");
    stmt_bind_params($stmt, [$verft_id]);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $verftlenker[] = $row; }
    $stmt->close();
}

$pageTitle = $isNew ? 'Nytt verft' : 'Verft';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<main class="container">
  <h2 class="center" style="margin-top:1rem;"><?= h($pageTitle) ?></h2>

  <div class="card centered-card" style="padding:1rem;max-width:1100px;margin-inline:auto;">
    <?php if ($errors): ?>
      <div class="alert alert-error">
        <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
      <div class="alert alert-success"><?= h((string)$_GET['msg']) ?></div>
    <?php endif; ?>

    <?php if ($isNew): ?>
      <form method="post">
        <input type="hidden" name="action" value="create_verft">
        <div class="form-grid">
          <div class="row">
            <div class="col">
              <label for="VerftNavn">Verftnavn</label>
              <input type="text" class="form-control" id="VerftNavn" name="VerftNavn" maxlength="255" required>
            </div>
            <div class="col">
              <label for="Sted">Sted</label>
              <input type="text" class="form-control" id="Sted" name="Sted" maxlength="48">
            </div>
            <div class="col">
              <label for="Nasjon_ID">Nasjon</label>
              <select class="form-select" id="Nasjon_ID" name="Nasjon_ID">
                <option value="">(ingen)</option>
                <?php foreach ($nasjoner as $n): ?>
                  <option value="<?= (int)$n['Nasjon_ID'] ?>"><?= h($n['Nasjon']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col">
              <label for="Etablert">Etablert (år)</label>
              <input type="number" class="form-control" id="Etablert" name="Etablert" min="0" max="9999">
            </div>
            <div class="col">
              <label for="Nedlagt">Nedlagt (år)</label>
              <input type="number" class="form-control" id="Nedlagt" name="Nedlagt" min="0" max="9999">
            </div>
            <div class="col">
              <label for="TidlID">Tidligere ID</label>
              <input type="number" class="form-control" id="TidlID" name="TidlID" min="0" max="999999">
            </div>
          </div>

          <div class="row">
            <div class="col">
              <label for="EtterID">Etterfølger ID</label>
              <input type="number" class="form-control" id="EtterID" name="EtterID" min="0" max="999999">
            </div>
            <div class="col" style="grid-column:span 2;">
              <label for="Merknad">Merknad</label>
              <input type="text" class="form-control" id="Merknad" name="Merknad" maxlength="255">
            </div>
          </div>

          <div class="row" style="justify-content:center;margin-top:1rem;gap:.5rem;">
            <button type="submit" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre</span></button>
            <a class="mdc-button mdc-button--raised btn btn-secondary" href="<?= BASE_URL ?>/admin/verftadmin.php"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Avbryt</span></a>
          </div>
        </div>
      </form>
    <?php else: ?>
      <?php if ($verft): ?>
        <form method="post">
          <input type="hidden" name="action" value="update_verft">
          <input type="hidden" name="Verft_ID" value="<?= (int)$verft['Verft_ID'] ?>">
          <div class="form-grid">
            <div class="row">
              <div class="col">
                <label for="VerftNavn">Verftnavn</label>
               <input type="text" class="form-control" id="VerftNavn" name="VerftNavn" <?= sw_len('VerftNavn') ?> required>
             </div>
              <div class="col">
                <label for="Sted">Sted</label>
                <input type="text" class="form-control" id="Sted" name="Sted" <?= sw_len('Sted') ?>>
             </div>
              <div class="col">
                <label for="Nasjon_ID">Nasjon</label>
                <select class="form-select" id="Nasjon_ID" name="Nasjon_ID">
                  <option value="">(ingen)</option>
                  <?php foreach ($nasjoner as $n): ?>
                    <option value="<?= (int)$n['Nasjon_ID'] ?>" <?= ((int)$verft['Nasjon_ID'] === (int)$n['Nasjon_ID']) ? 'selected' : '' ?>>
                      <?= h($n['Nasjon']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col">
                <label for="Etablert">Etablert (år)</label>
                 <input type="number" class="form-control" id="Etablert" name="Etablert" <?= sw_len('Etablert') ?> min="0" max="9999">
             </div>
              <div class="col">
                <label for="Nedlagt">Nedlagt (år)</label>
                 <input type="number" class="form-control" id="Nedlagt" name="Nedlagt" <?= sw_len('Nedlagt') ?> min="0" max="9999">
             </div>
              <div class="col">
                <label for="TidlID">Tidligere ID</label>
                 <input type="number" class="form-control" id="TidlID" name="TidlID" <?= sw_len('TidlID') ?> min="0" max="999999">
             </div>
            </div>

            <div class="row">
              <div class="col">
                <label for="EtterID">Etterfølger ID</label>
                 <input type="number" class="form-control" id="EtterID" name="EtterID" <?= sw_len('EtterID') ?> min="0" max="999999">
             </div>
              <div class="col" style="grid-column:span 2;">
                <label for="Merknad">Merknad</label>
                <input type="text" class="form-control" id="Merknad" name="Merknad" <?= sw_len('Merknad') ?>>
             </div>
            </div>

            <div class="row" style="justify-content:center;margin-top:1rem;gap:.5rem;">
              <button type="submit" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre</span></button>
              <a class="mdc-button mdc-button--raised btn btn-secondary" href="<?= BASE_URL ?>/admin/verftadmin.php"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Tilbake</span></a>
            </div>
          </div>
        </form>

        <!-- Lenketabell -->
        <h2 class="center" style="margin-top:2rem;">Lenker for dette verftet</h2>
        <div class="card centered-card" style="padding:1rem;">
          <div class="mdc-data-table table-wrap center outline-brand">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit tight" id="links-table">
              <thead>
                <tr class="mdc-data-table__header-row">
                  <th class="mdc-data-table__header-cell">LinkType (ID)</th>
                  <th class="mdc-data-table__header-cell">LinkType (tekst)</th>
                  <th class="mdc-data-table__header-cell">Innhold</th>
                  <th class="mdc-data-table__header-cell">URL</th>
                  <th class="mdc-data-table__header-cell">Handling</th>
                </tr>
              </thead>
              <tbody class="mdc-data-table__content">
                <?php if (!$verftlenker): ?>
                  <tr class="mdc-data-table__row"><td class="mdc-data-table__cell" colspan="6" style="text-align:center;">Ingen lenker registrert.</td></tr>
                <?php else: ?>
                  <?php foreach ($verftlenker as $lk): ?>
                    <tr class="mdc-data-table__row">
                      <form method="post">
                        <input type="hidden" name="action" value="update_link">
                        <input type="hidden" name="Verft_ID" value="<?= (int)$verft['Verft_ID'] ?>">
                        <input type="hidden" name="VerftLk_ID" value="<?= (int)$lk['VerftLk_ID'] ?>">
                        <td class="mdc-data-table__cell"><?= (int)$lk['VerftLk_ID'] ?></td>
                        <td class="mdc-data-table__cell" style="min-width:160px;">
                          <select name="LinkType_ID">
                            <option value="">(ingen)</option>
                            <?php foreach ($linktyper as $lt): ?>
                              <option value="<?= (int)$lt['LinkType_ID'] ?>" <?= ((int)$lk['LinkType_ID'] === (int)$lt['LinkType_ID']) ? 'selected' : '' ?>>
                                <?= h($lt['LinkType']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td class="mdc-data-table__cell"><input type="text" name="LinkType" value="<?= h((string)$lk['LinkType']) ?>" placeholder="Valgfri fritekst"></td>
                        <td class="mdc-data-table__cell"><input type="text" name="LinkInnh" value="<?= h((string)$lk['LinkInnh']) ?>"></td>
                        <td class="mdc-data-table__cell" style="min-width:280px;"><input type="text" name="Link" value="<?= h((string)$lk['Link']) ?>"></td>
                        <td class="mdc-data-table__cell" style="white-space:nowrap;">
                          <button type="submit" class="btn btn-small">Lagre</button>
                      </form>
                      <form method="post" onsubmit="return confirm('Slette lenken?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_link">
                        <input type="hidden" name="Verft_ID" value="<?= (int)$verft['Verft_ID'] ?>">
                        <input type="hidden" name="VerftLk_ID" value="<?= (int)$lk['VerftLk_ID'] ?>">
                        <button type="submit" class="btn btn-small btn-danger">Slett</button>
                      </form>
                        </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>

                <!-- Ny lenke -->
                <tr class="mdc-data-table__row">
                  <form method="post">
                    <input type="hidden" name="action" value="add_link">
                    <input type="hidden" name="Verft_ID" value="<?= (int)$verft['Verft_ID'] ?>">
                    <td class="mdc-data-table__cell">(ny)</td>
                    <td class="mdc-data-table__cell">
                      <select name="LinkType_ID">
                        <option value="">(ingen)</option>
                        <?php foreach ($linktyper as $lt): ?>
                          <option value="<?= (int)$lt['LinkType_ID'] ?>"><?= h($lt['LinkType']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td class="mdc-data-table__cell"><input type="text" name="LinkType" placeholder="Valgfri fritekst"></td>
                    <td class="mdc-data-table__cell"><input type="text" name="LinkInnh" placeholder="F.eks. Offisiell side"></td>
                    <td class="mdc-data-table__cell"><input type="text" name="Link" placeholder="https://…"></td>
                    <td class="mdc-data-table__cell"><button type="submit" class="btn btn-small">Legg til</button></td>
                  </form>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
