<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/export_utils.php';
require_once __DIR__ . '/../includes/auth.php';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$includeSted = isset($_GET['include_sted']) && (string)$_GET['include_sted'] !== '';
$verftId = isset($_GET['verft_id']) ? (int)$_GET['verft_id'] : 0;
$section = isset($_GET['section']) ? strtolower((string)$_GET['section']) : 'leveranse';

/** @var mysqli $conn */
$db = $conn ?? ($mysqli ?? null);
if (!$db) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler'; exit; }
$db->set_charset('utf8mb4');

if ($section === 'verft') {
  $rows=[];
  if ($q !== '' && mb_strlen($q) >= 2) {
    if ($includeSted) {
      $sql = "SELECT v.VerftNavn, v.Sted, n.Nasjon FROM tblverft v LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID WHERE v.VerftNavn LIKE CONCAT('%', ?, '%') OR v.Sted LIKE CONCAT('%', ?, '%') ORDER BY v.VerftNavn LIMIT 1000";
      $stmt=$db->prepare($sql); $stmt->bind_param('ss',$q,$q);
    } else {
      $sql = "SELECT v.VerftNavn, v.Sted, n.Nasjon FROM tblverft v LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID WHERE v.VerftNavn LIKE CONCAT('%', ?, '%') ORDER BY v.VerftNavn LIMIT 1000";
      $stmt=$db->prepare($sql); $stmt->bind_param('s',$q);
    }
    $stmt->execute(); $res=$stmt->get_result(); while($res && ($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
  }
  $headers=['Verft','Sted','Nasjon']; $data=[]; foreach($rows as $r){ $data[]=[(string)($r['VerftNavn']??''),(string)($r['Sted']??''),(string)($r['Nasjon']??'')]; }
  sw_send_csv('verft_liste.csv',$headers,$data);
}

if ($section === 'leveranse') {
  $rows=[]; $sql = "SELECT fs.Byggenr, fs.YearSpes, fs.MndSpes, tid.FartNavn, tid.RegHavn, tid.Rederi, zt.TypeFork FROM tblfartspes fs LEFT JOIN ( SELECT t1.FartSpes_ID, t1.FartTid_ID, t1.FartNavn, t1.RegHavn, t1.Rederi, t1.FartType_ID FROM ( SELECT FartSpes_ID, MAX(FartTid_ID) AS FartTid_ID FROM tblfarttid GROUP BY FartSpes_ID ) tmx JOIN tblfarttid t1 ON t1.FartTid_ID = tmx.FartTid_ID ) tid ON tid.FartSpes_ID = fs.FartSpes_ID LEFT JOIN tblzfarttype zt ON zt.FartType_ID = tid.FartType_ID WHERE fs.Verft_ID = ? ORDER BY fs.YearSpes, fs.MndSpes, tid.FartNavn LIMIT 1000";
  $stmt=$db->prepare($sql); $stmt->bind_param('i',$verftId); $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
  $headers=['Byggenr','År/mnd','Navn','Reg.havn','Rederi','Type']; $data=[]; foreach($rows as $r){ $ym=($r['YearSpes']?(int)$r['YearSpes']:0); $ym .= $r['MndSpes']? '/'.str_pad((string)(int)$r['MndSpes'],2,'0',STR_PAD_LEFT):''; $navn=trim((string)($r['TypeFork']??'')); $navn=($navn!==''?$navn.' ':'') . (string)($r['FartNavn']??''); $data[]=[(string)($r['Byggenr']??''),(string)$ym,$navn,(string)($r['RegHavn']??''),(string)($r['Rederi']??''),(string)($r['TypeFork']??'')]; }
  sw_send_csv('verft_leveranser.csv',$headers,$data);
}

if ($section === 'skrog') {
  $rows=[]; $sql = "SELECT fs.Byggenr, fs.YearSpes, fs.MndSpes, tid.FartNavn, tid.RegHavn, tid.Rederi, zt.TypeFork, CASE WHEN fo.FartObj_ID IS NOT NULL THEN 1 ELSE 0 END AS Objekt FROM tblfartspes fs LEFT JOIN tblfartobj fo ON fo.FartObj_ID = fs.FartObj_ID LEFT JOIN ( SELECT t1.FartSpes_ID, t1.FartTid_ID, t1.FartNavn, t1.RegHavn, t1.Rederi, t1.FartType_ID FROM ( SELECT FartSpes_ID, MAX(FartTid_ID) AS FartTid_ID FROM tblfarttid GROUP BY FartSpes_ID ) t2 JOIN tblfarttid t1 ON t1.FartTid_ID = t2.FartTid_ID ) tid ON tid.FartSpes_ID = fs.FartSpes_ID LEFT JOIN tblzfarttype zt ON zt.FartType_ID = tid.FartType_ID WHERE fo.SkrogID = ? AND fo.SkrogID IS NOT NULL AND (fs.Verft_ID IS NULL OR fo.SkrogID <> fs.Verft_ID) ORDER BY fs.YearSpes, fs.MndSpes, tid.FartNavn LIMIT 1000";
  $stmt=$db->prepare($sql); $stmt->bind_param('i',$verftId); $stmt->execute(); $res=$stmt->get_result(); while($res&&($r=$res->fetch_assoc())) $rows[]=$r; $stmt->close();
  $headers=['Byggenr','År/mnd','Navn','Reg.havn','Rederi','Objekt']; $data=[]; foreach($rows as $r){ $ym=($r['YearSpes']?(int)$r['YearSpes']:0); $ym .= $r['MndSpes']? '/'.str_pad((string)(int)$r['MndSpes'],2,'0',STR_PAD_LEFT):''; $navn=trim((string)($r['TypeFork']??'')); $navn=($navn!==''?$navn.' ':'') . (string)($r['FartNavn']??''); $data[]=[(string)($r['Byggenr']??''),(string)$ym,$navn,(string)($r['RegHavn']??''),(string)($r['Rederi']??''),(int)($r['Objekt']??0)]; }
  sw_send_csv('verft_skrog.csv',$headers,$data);
}

