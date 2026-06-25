<?php
// admin/param_table.php – manage a single parameter table
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/param_utils.php';

require_admin();

$table = isset($_GET['table']) ? (string)$_GET['table'] : '';
if (!param_allowed_table($table)) {
    http_response_code(400);
    echo 'Ugyldig tabellnavn';
    exit;
}

$pk = param_get_primary_key($table);
if (!$pk) {
    http_response_code(400);
    echo 'Tabellen har ikke en enkel primærnøkkel som støttes.';
    exit;
}

$allColumns  = param_table_columns($table);
$hiddenCols  = param_get_hidden_columns($table);
$hashedCols  = param_get_hashed_columns($table);
$columns     = array_values(array_filter($allColumns, fn($c) => !in_array($c['COLUMN_NAME'], $hiddenCols, true)));

// Handle actions: create, update, delete
$action  = $_POST['action'] ?? '';
$message = '';
$error   = '';

try {
    if ($action === 'create') {
        $data = [];
        foreach ($columns as $c) {
            $name = $c['COLUMN_NAME'];
            if ($name === $pk) continue;
            $data[$name] = isset($_POST[$name]) ? (string)$_POST[$name] : null;
        }
        $filtered = [];
        foreach ($columns as $c) {
            $n = $c['COLUMN_NAME'];
            if ($n === $pk) continue;
            if (array_key_exists($n, $data)) {
                $val = $data[$n];
                if ($val === '' && $c['IS_NULLABLE'] === 'YES') {
                    // skip to allow default NULL
                } else {
                    $filtered[$n] = $val;
                }
            }
        }
        foreach ($hashedCols as $hCol) {
            if (isset($filtered[$hCol]) && $filtered[$hCol] !== '') {
                $filtered[$hCol] = password_hash($filtered[$hCol], PASSWORD_BCRYPT);
            }
        }
        $newId = param_insert($table, $filtered);
        $message = 'Opprettet rad med ID ' . h((string)$newId);
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $data = [];
        foreach ($columns as $c) {
            $name = $c['COLUMN_NAME'];
            if ($name === $pk) continue;
            if (isset($_POST[$name])) {
                $data[$name] = (string)$_POST[$name];
            }
        }
        // Hash password; skip update if left blank (unchanged)
        foreach ($hashedCols as $hCol) {
            if (array_key_exists($hCol, $data)) {
                if ($data[$hCol] === '') {
                    unset($data[$hCol]);
                } else {
                    $data[$hCol] = password_hash($data[$hCol], PASSWORD_BCRYPT);
                }
            }
        }
        if ($id === '') throw new RuntimeException('Mangler ID for oppdatering.');
        param_update($table, $pk, $id, $data);
        $message = 'Oppdatert rad ' . h((string)$id);
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id === '') throw new RuntimeException('Mangler ID for sletting.');
        param_delete($table, $pk, $id);
        $message = 'Slettet rad ' . h((string)$id);
    }
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}

// Fetch rows after action
$rows = param_fetch_all($table, $pk, 500);

$page_title = "Param: $table";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-3">
  <h1><?= h($table) ?></h1>
  <p>Primærnøkkel: <code><?= h($pk) ?></code></p>

  <?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert danger"><?= h($error) ?></div><?php endif; ?>

  <p>
    <a class="mdc-button mdc-button--raised btn" href="param_admin.php"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Tilbake til oversikt</span></a>
  </p>

  <h2>Legg til ny rad</h2>
  <form method="post" class="form">
    <input type="hidden" name="action" value="create">
    <div class="two-col">
      <?php foreach (param_build_form_fields($columns, $pk) as $f): if ($f['is_pk']) continue; ?>
        <label>
          <span><?= h($f['name']) ?></span>
          <input type="<?= in_array($f['name'], $hashedCols, true) ? 'password' : 'text' ?>"
                 name="<?= h($f['name']) ?>" value=""
                 <?= $f['max'] ? 'maxlength="'.(int)$f['max'].'"' : '' ?>>
        </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="mdc-button mdc-button--raised btn primary" style="margin-top: 1.5rem;"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Opprett</span></button>
  </form>

  <h2 class="mt-3">Eksisterende rader</h2>
  <div class="mdc-data-table table-responsive">
    <div class="mdc-data-table__table-container">
    <table class="mdc-data-table__table table table-striped table-sm">
      <thead>
        <tr class="mdc-data-table__header-row">
          <?php foreach ($columns as $c): ?>
            <th class="mdc-data-table__header-cell"><?= h($c['COLUMN_NAME']) ?></th>
          <?php endforeach; ?>
          <th class="mdc-data-table__header-cell">Handlinger</th>
        </tr>
      </thead>
      <tbody class="mdc-data-table__content">
        <?php foreach ($rows as $r): ?>
          <tr class="mdc-data-table__row">
            <?php foreach ($columns as $c): $n = $c['COLUMN_NAME']; ?>
              <td class="mdc-data-table__cell"><?= h(in_array($n, $hashedCols, true) ? '***' : ($r[$n] ?? '')) ?></td>
            <?php endforeach; ?>
            <td class="mdc-data-table__cell">
              <details>
                <summary>Endre</summary>
                <form method="post" style="margin-top: .5rem;">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= h((string)$r[$pk]) ?>">
                  <div class="param-edit-row">
                    <?php foreach (param_build_form_fields($columns, $pk, $r) as $f): if ($f['is_pk']) continue; ?>
                      <label>
                        <?= h($f['name']) ?><?= in_array($f['name'], $hashedCols, true) ? ' <em>(tom=uendret)</em>' : '' ?>
                        <input type="<?= in_array($f['name'], $hashedCols, true) ? 'password' : 'text' ?>"
                               name="<?= h($f['name']) ?>"
                               value="<?= in_array($f['name'], $hashedCols, true) ? '' : h($f['value']) ?>"
                               <?= $f['max'] ? 'maxlength="'.(int)$f['max'].'"' : '' ?>>
                      </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn-small">Lagre</button>
                    <button type="button" class="btn-small danger"
                            onclick="if(confirm('Slette rad?')){this.closest('.param-edit-row').querySelector('[name=action]').value='delete';this.closest('form').submit();}">Slett</button>
                    <input type="hidden" name="action" value="update">
                  </div>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
