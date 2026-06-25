<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/export_utils.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$selRederi = isset($_GET['rederi']) ? trim((string)$_GET['rederi']) : '';
$section = isset($_GET['section']) ? strtolower((string)$_GET['section']) : 'rederi';

/** @var mysqli $conn */
$db = $conn ?? ($mysqli ?? null);
if (!$db) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler'; exit; }
$db->set_charset('utf8mb4');

$RE_T = "RTRIM( TRIM(TRAILING CONCAT(' ', t.RegHavn) FROM SUBSTRING( t.Rederi, 1, GREATEST( CASE WHEN LOCATE(', ', t.Rederi) > 0 THEN CHAR_LENGTH(t.Rederi) - LOCATE(', ', REVERSE(t.Rederi)) - 1 ELSE 0 END, CASE WHEN LOCATE(') ', t.Rederi) > 0 THEN CHAR_LENGTH(t.Rederi) - LOCATE(') ', REVERSE(t.Rederi)) - 1 ELSE 0 END, CHAR_LENGTH(t.Rederi)))) )";
$RE_FT = "RTRIM( TRIM(TRAILING CONCAT(' ', ft.RegHavn) FROM SUBSTRING( ft.Rederi, 1, GREATEST( CASE WHEN LOCATE(', ', ft.Rederi) > 0 THEN CHAR_LENGTH(ft.Rederi) - LOCATE(', ', REVERSE(ft.Rederi)) - 1 ELSE 0 END, CASE WHEN LOCATE(') ', ft.Rederi) > 0 THEN CHAR_LENGTH(ft.Rederi) - LOCATE(') ', REVERSE(ft.Rederi)) - 1 ELSE 0 END, CHAR_LENGTH(ft.Rederi)))) )";

if ($section === 'rederi') {
  $rows=[];
  if ($q !== '' && mb_strlen($q) >= 2) {
    $sql = "SELECT DISTINCT $RE_T AS Rederi FROM tblfarttid t WHERE $RE_T LIKE CONCAT('%', ?, '%') AND t.Rederi IS NOT NULL AND t.Rederi <> '' ORDER BY Rederi LIMIT 500";
    $stmt=$db->prepare($sql); $stmt->bind_param('s',$q); $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
  }
  $headers=['Rederi']; $data=[]; foreach($rows as $r){ $data[]=[(string)($r['Rederi']??'')]; } sw_send_csv('rederi_liste.csv',$headers,$data);
}

if ($section === 'fartoy') {
  $rows=[];
  if ($selRederi !== '') {
    $sql = "SELECT ft.FartNavn, ft.RegHavn, ft.YearTid, ft.MndTid, zt.TypeFork, CASE WHEN fo.FartObj_ID IS NOT NULL THEN 1 ELSE 0 END AS Objekt FROM tblfarttid ft LEFT JOIN tblzfarttype zt ON zt.FartType_ID = ft.FartType_ID LEFT JOIN tblfartobj fo ON fo.FartObj_ID = ft.FartObj_ID WHERE $RE_FT = ? ORDER BY ft.YearTid, ft.MndTid, ft.FartNavn LIMIT 1000";
    $stmt=$db->prepare($sql); $stmt->bind_param('s',$selRederi); $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
  }
  $headers=['Navn','Reg.havn','Fra år/mnd','Objekt']; $data=[]; foreach($rows as $r){ $ym=(isset($r['YearTid'])?(int)$r['YearTid']:0); $ym.=isset($r['MndTid'])? '/'.str_pad((string)(int)$r['MndTid'],2,'0',STR_PAD_LEFT):''; $navn=trim((string)($r['TypeFork']??'')); $navn=($navn!==''?$navn.' ':'') . (string)($r['FartNavn']??''); $data[]=[ $navn, (string)($r['RegHavn']??''), (string)$ym, (int)($r['Objekt']??0) ]; } sw_send_csv('rederi_fartoyer.csv',$headers,$data);
}

