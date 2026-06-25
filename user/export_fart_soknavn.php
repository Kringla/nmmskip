<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/export_utils.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$regHavn  = isset($_GET['reghavn']) ? trim((string)$_GET['reghavn']) : '';
$nasjonIdParam = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0;

$dbh = $db ?? ($mysqli ?? ($conn ?? null));
if (!$dbh) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler.'; exit; }

$rows = [];
$sql = "
  SELECT ft.FartNavn, ft.YearTid, ty.FartType, ft.RegHavn, ns.Nasjon, ft.Kallesignal, ft.MMSI, ft.PennantTiln
  FROM tblfarttid ft
  LEFT JOIN tblzfarttype ty ON ty.FartType_ID = ft.FartType_ID
  LEFT JOIN tblznasjon   ns ON ns.Nasjon_ID   = ft.Nasjon_ID
  WHERE 1
    AND (? = '' OR ft.FartNavn LIKE ?)
    AND (? = '' OR ft.RegHavn  LIKE ?)
    AND (? = 0  OR ft.Nasjon_ID = ?)
  ORDER BY ft.FartNavn ASC, ft.YearTid ASC
  LIMIT 200";
$stmt = $dbh->prepare($sql);
$likeQ = '%' . $q . '%'; $likeR = '%' . $regHavn . '%';
$stmt->bind_param('ssssii', $q, $likeQ, $regHavn, $likeR, $nasjonIdParam, $nasjonIdParam);
$stmt->execute(); $res = $stmt->get_result();
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r; $stmt->close();

$headers = ['Navn','År','Type','Reg.havn','Nasjon','Kallesignal','MMSI','Pennant/Tilnavn'];
$data = [];
foreach ($rows as $r) {
  $data[] = [
    (string)($r['FartNavn'] ?? ''),
    (string)($r['YearTid'] ?? ''),
    (string)($r['FartType'] ?? ''),
    (string)($r['RegHavn'] ?? ''),
    (string)($r['Nasjon'] ?? ''),
    (string)($r['Kallesignal'] ?? ''),
    (string)($r['MMSI'] ?? ''),
    (string)($r['PennantTiln'] ?? ''),
  ];
}

sw_send_csv('fart_soknavn.csv', $headers, $data);

