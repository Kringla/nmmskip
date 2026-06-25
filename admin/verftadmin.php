<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
// Ensure a mysqli connection instance is available as $db
// bootstrap.php exposes db() which returns a shared RO mysqli connection
// Many pages use $db->..., so bind it here for consistency
// $db = db();
require_once __DIR__ . '/../includes/auth.php';

require_admin(); // Krever admin-rolle

// uses global h() from includes/functions.php

// Hent nasjoner til nedtrekk
$nasjoner = [];
$stmt = $db->prepare("SELECT Nasjon_ID, Nasjon FROM tblznasjon ORDER BY Nasjon ASC");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $nasjoner[] = $row; }
$stmt->close();

// Sokefelter
$q_navn   = isset($_GET['navn'])   ? trim((string)$_GET['navn'])   : '';
$q_sted   = isset($_GET['sted'])   ? trim((string)$_GET['sted'])   : '';
$q_nasjon = isset($_GET['nasjon']) ? trim((string)$_GET['nasjon']) : '';

$results = [];
if (isset($_GET['do']) && $_GET['do'] === 'search') {
    $sql = "SELECT v.Verft_ID, v.VerftNavn, v.Sted, v.Nasjon_ID, n.Nasjon, v.Etablert, v.Nedlagt
            FROM tblverft v
            LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID
            WHERE 1=1";
    $params = [];
    $types  = '';

    if ($q_navn !== '') { $sql .= " AND v.VerftNavn LIKE ?"; $params[] = "%{$q_navn}%"; $types .= 's'; }
    if ($q_sted !== '') { $sql .= " AND v.Sted LIKE ?";      $params[] = "%{$q_sted}%"; $types .= 's'; }
    if ($q_nasjon !== '') { $sql .= " AND v.Nasjon_ID = ?";  $params[] = (int)$q_nasjon; $types .= 'i'; }

    $sql .= " ORDER BY v.VerftNavn ASC LIMIT 250";

    $stmt = $db->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $results[] = $row; }
    $stmt->close();
}

$pageTitle = 'Verft - administrasjon';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>
<main class="container">
  <h1 class="center" style="margin-top:1rem;">Verft - administrasjon</h1>

  <div class="card centered-card" style="padding:1rem;max-width:1100px;margin-inline:auto;">
    <form method="get">
      <input type="hidden" name="do" value="search">

      <div class="search-form">
        <label for="navn">Verftnavn</label>
        <input type="text" id="navn" name="navn" value="<?= h($q_navn) ?>" placeholder="f.eks. Aker, Nyland ...">

        <label for="sted">Sted</label>
        <input type="text" id="sted" name="sted" value="<?= h($q_sted) ?>" placeholder="f.eks. Oslo, Bergen ...">

        <label for="nasjon">Nasjon</label>
        <select id="nasjon" name="nasjon">
          <option value="">(alle)</option>
          <?php foreach ($nasjoner as $n): ?>
            <option value="<?= (int)$n['Nasjon_ID'] ?>" <?= ($q_nasjon !== '' && (int)$q_nasjon === (int)$n['Nasjon_ID']) ? 'selected' : '' ?>>
              <?= h($n['Nasjon']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="mdc-button mdc-button--raised btn"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Sok</span></button>
      </div>

      <div class="row" style="justify-content:center; margin-top:.5rem;">
        <a class="mdc-button mdc-button--raised btn" href="<?= BASE_URL ?>/admin/verft.php?new=1"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Nytt verft</span></a>
      </div>
      <div class="row" style="justify-content:center; margin-top:.5rem;">
        <a class="mdc-button mdc-button--raised btn btn-secondary" href="<?= BASE_URL ?>/admin/sw_admin.php"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Tilbake</span></a>
      </div>
    </form>
  </div>

  <?php if (isset($_GET['do']) && $_GET['do'] === 'search'): ?>
    <div class="card centered-card" style="overflow-x:auto;max-width:1100px;margin-inline:auto;margin-top:1rem;">
      <div class="mdc-data-table table-container">
        <?php if (count($results) === 0): ?>
          <p style="padding:1rem;">Ingen verft funnet.</p>
        <?php else: ?>
          <div class="mdc-data-table__table-container">
          <table class="mdc-data-table__table table tight fit">
            <thead>
              <tr class="mdc-data-table__header-row">
                <th class="mdc-data-table__header-cell">Verftnavn</th>
                <th class="mdc-data-table__header-cell">Sted</th>
                <th class="mdc-data-table__header-cell">Nasjon</th>
                <th class="mdc-data-table__header-cell">Etablert</th>
                <th class="mdc-data-table__header-cell">Nedlagt</th>
                <th class="mdc-data-table__header-cell">Handling</th>
              </tr>
            </thead>
            <tbody class="mdc-data-table__content">
            <?php foreach ($results as $r): ?>
              <tr class="mdc-data-table__row">
                <td class="mdc-data-table__cell"><?= h((string)$r['VerftNavn']) ?></td>
                <td class="mdc-data-table__cell"><?= h((string)$r['Sted']) ?></td>
                <td class="mdc-data-table__cell"><?= h((string)($r['Nasjon'] ?? '')) ?></td>
                <td class="mdc-data-table__cell"><?= h((string)$r['Etablert']) ?></td>
                <td class="mdc-data-table__cell"><?= h((string)$r['Nedlagt']) ?></td>
                <td class="mdc-data-table__cell">
                  <a class="btn-small" href="<?= BASE_URL ?>/admin/verft.php?verft_id=<?= (int)$r['Verft_ID'] ?>">Velg</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
