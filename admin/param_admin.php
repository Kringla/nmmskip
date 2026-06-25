<?php
// admin/param_admin.php – overview of parameter tables
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/param_utils.php';

require_admin();

$tables = param_list_tables();

$page_title = 'Parameter-tabeller';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
// uses global h() from includes/functions.php
?>
<div class="container mt-3">
  <h1>Parameter-tabeller</h1>
  <p>Her kan du liste, opprette, endre og slette rader i parametertabeller (<code>tblz*</code>).</p>

  <?php if (!$tables): ?>
    <p>Fant ingen tabeller som starter med <code>tblz</code>.</p>
  <?php else: ?>
    <div class="mdc-data-table table-responsive">
      <div class="mdc-data-table__table-container">
      <table class="mdc-data-table__table table table-striped table-sm">
        <thead>
          <tr class="mdc-data-table__header-row">
            <th class="mdc-data-table__header-cell">Tabell</th>
            <th class="mdc-data-table__header-cell">Handling</th>
          </tr>
        </thead>
        <tbody class="mdc-data-table__content">
          <?php foreach ($tables as $t): ?>
            <tr class="mdc-data-table__row">
              <td class="mdc-data-table__cell"><?= h($t) ?></td>
              <td class="mdc-data-table__cell">
                <a class="btn-small" href="param_table.php?table=<?= urlencode($t) ?>">Administrer</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

