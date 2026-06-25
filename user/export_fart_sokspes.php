<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/export_utils.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$driftId = isset($_GET['fartdrift_id']) ? (int)$_GET['fartdrift_id'] : 0;
$skrogId = isset($_GET['fartskrog_id']) ? (int)$_GET['fartskrog_id'] : 0;
$funkId  = isset($_GET['fartfunk_id'])  ? (int)$_GET['fartfunk_id']  : 0;

/** @var mysqli $mysqli */
$db = $mysqli ?? ($conn ?? null);
if (!$db) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler'; exit; }

$where = [];$types='';$vals=[];
if ($driftId > 0) { $where[]='s.FartDrift_ID = ?'; $types.='i'; $vals[]=$driftId; }
if ($skrogId > 0) { $where[]='s.FartSkrog_ID = ?'; $types.='i'; $vals[]=$skrogId; }
if ($funkId  > 0) { $where[]='s.FartFunk_ID  = ?'; $types.='i'; $vals[]=$funkId; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = [];
$sql = "
  SELECT t.FartNavn, COALESCE(s.YearSpes, t.YearTid) AS YearShow, ft.FartType,
         s.Materiale, s.Lengde, s.Bredde, s.Dypg, s.Tonnasje, te.TonnFork, s.Drektigh, de.DrektFork
  FROM tblfartspes s
  JOIN tblfarttid t ON t.FartSpes_ID = s.FartSpes_ID
  LEFT JOIN tblzfarttype ft ON t.FartType_ID = ft.FartType_ID
  LEFT JOIN tblztonnenh  te ON s.TonnEnh_ID  = te.TonnEnh_ID
  LEFT JOIN tblzdrektenh de ON s.DrektEnh_ID = de.DrektEnh_ID
  $whereSql
  GROUP BY t.FartNavn, YearShow, ft.FartType, s.Materiale, s.Lengde, s.Bredde, s.Dypg, s.Tonnasje, te.TonnFork, s.Drektigh, de.DrektFork
  ORDER BY t.FartNavn, YearShow
  LIMIT 2000";
$stmt = $db->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$vals); }
$stmt->execute(); $res = $stmt->get_result();
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r; $stmt->close();

$headers = ['Navn','År','Type','Materiale','Dimensjoner (LxBxD)','Tonnasje','Drektighet'];
$data = [];
foreach ($rows as $r) {
  $dims = [];
  if (!empty($r['Lengde'])) $dims[] = $r['Lengde'] . ' m';
  if (!empty($r['Bredde'])) $dims[] = $r['Bredde'] . ' m';
  if (!empty($r['Dypg']))   $dims[] = $r['Dypg']   . ' m';
  $tonn = trim((string)($r['Tonnasje'] ?? ''));
  $tf   = trim((string)($r['TonnFork'] ?? ''));
  $dr   = trim((string)($r['Drektigh'] ?? ''));
  $df   = trim((string)($r['DrektFork'] ?? ''));
  $data[] = [
    (string)($r['FartNavn'] ?? ''),
    (string)($r['YearShow'] ?? ''),
    (string)($r['FartType'] ?? ''),
    (string)($r['Materiale'] ?? ''),
    ($dims ? implode(' x ', $dims) : ''),
    trim($tonn . ($tf ? (' ' . $tf) : '')),
    trim($dr . ($df ? (' ' . $df) : '')),
  ];
}

sw_send_csv('fart_sokspes.csv', $headers, $data);

