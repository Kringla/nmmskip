<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/export_utils.php';
require_once __DIR__ . '/../includes/auth.php';
// uses global h() from includes/functions.php

$obj_id  = isset($_GET['obj_id']) ? (int)$_GET['obj_id'] : 0;
$tid_id  = isset($_GET['tid_id']) ? (int)$_GET['tid_id'] : 0;

/** @var mysqli $conn */
$db = $conn ?? ($mysqli ?? null);
if (!$db) { http_response_code(500); header('Content-Type: text/plain; charset=UTF-8'); echo 'DB-tilkobling mangler'; exit; }
if ($obj_id <= 0 || $tid_id <= 0) { http_response_code(400); header('Content-Type: text/plain; charset=UTF-8'); echo 'Mangler parametre'; exit; }

$stmt = $db->prepare("SELECT t.*, zft.TypeFork, zn.Nasjon AS NasjonNavn FROM tblfarttid t LEFT JOIN tblzfarttype zft ON zft.FartType_ID = t.FartType_ID LEFT JOIN tblznasjon zn ON zn.Nasjon_ID = t.Nasjon_ID WHERE t.FartTid_ID = ? LIMIT 1");
$stmt->bind_param('i',$tid_id); $stmt->execute(); $res=$stmt->get_result(); $main = $res?$res->fetch_assoc():null; $stmt->close();
if (!$main) { http_response_code(404); header('Content-Type:text/plain; charset=UTF-8'); echo 'Fant ingen detalj.'; exit; }

$typeFork = trim((string)($main['TypeFork'] ?? ''));
if ($typeFork === '') {
  $stmt = $db->prepare("SELECT zft.TypeFork FROM tblfarttid t LEFT JOIN tblfartspes fs ON fs.FartSpes_ID = t.FartSpes_ID LEFT JOIN tblzfarttype zft ON zft.FartType_ID = fs.FartType_ID WHERE t.FartTid_ID = ? LIMIT 1");
  $stmt->bind_param('i',$tid_id); $stmt->execute(); $res2=$stmt->get_result(); if ($res2){ $r=$res2->fetch_assoc(); if ($r) $typeFork = trim((string)$r['TypeFork']); } $stmt->close();
}

$stmt=$db->prepare("SELECT o.*, CONCAT_WS(', ', v1.VerftNavn, v1.Sted) AS LeverandorNavn, CONCAT_WS(', ', v2.VerftNavn, v2.Sted) AS SkrogbyggerNavn, zs.Strok AS StroketNavn FROM tblfartobj o LEFT JOIN tblverft v1 ON v1.Verft_ID=o.LeverID LEFT JOIN tblverft v2 ON v2.Verft_ID=o.SkrogID LEFT JOIN tblzstroket zs ON zs.Stroket_ID=o.StroketID WHERE o.FartObj_ID = ? LIMIT 1");
$stmt->bind_param('i',$obj_id); $stmt->execute(); $res3=$stmt->get_result(); $objRow = $res3?$res3->fetch_assoc():null; $stmt->close();

$mm = (int)($main['MndTid'] ?? 0); $mmS = $mm > 0 ? str_pad((string)$mm,2,'0',STR_PAD_LEFT) : '00';
$tidStr = (string)($main['YearTid'] ?? '') . '/' . $mmS;
$navn    = (string)($main['FartNavn'] ?? '(ukjent navn)');
$visNavn = $typeFork !== '' ? trim($typeFork . ' ' . $navn) : $navn;

$headers=['Felt','Verdi']; $rows=[]; $add=function($k,$v) use (&$rows){ $rows[]=[(string)$k,(string)$v]; };
$add('Navn',$visNavn);
$add('Tidspunkt',$tidStr);
if (!empty($main['NasjonNavn'])) $add('Nasjon',$main['NasjonNavn']);
if (!empty($main['RegHavn'])) $add('Reg.havn',$main['RegHavn']);
if (!empty($main['Rederi'])) $add('Rederi',$main['Rederi']);
if (!empty($main['Kallesignal'])) $add('Kallesignal',$main['Kallesignal']);
if (!empty($main['PennantTiln'])) $add('Tilnavn/Pennant nr',$main['PennantTiln']);
if (!empty($main['MMSI'])) $add('MMSI',$main['MMSI']);
if (!empty($main['Fiskerinr'])) $add('Fiskerinr',$main['Fiskerinr']);
if ($objRow){
  if (!empty($objRow['Bygget'])) $add('Bygget (år)',$objRow['Bygget']);
  if (!empty($objRow['NavnObj'])) $add('Navn gitt ved bygging',$objRow['NavnObj']);
  if (!empty($typeFork)) $add('Fartøytype',$typeFork);
  if (!empty($objRow['IMO'])) $add('IMO',$objRow['IMO']);
  if (!empty($objRow['Kontrahert'])) $add('Kontrahert',$objRow['Kontrahert']);
  if (!empty($objRow['Kjolstrukket'])) $add('Kjolstrukket',$objRow['Kjolstrukket']);
  if (!empty($objRow['Sjosatt'])) $add('Sjosatt',$objRow['Sjosatt']);
  if (!empty($objRow['Levert'])) $add('Levert',$objRow['Levert']);
  $levNavn = trim((string)($objRow['LeverandorNavn'] ?? '')); $levID = trim((string)($objRow['LeverID'] ?? ''));
  if ($levNavn!=='' || $levID!=='') $add('Leverandør', $levNavn!==''? $levNavn : ('ID: '.$levID));
  if (!empty($objRow['ByggeNr'])) $add('Byggenr',$objRow['ByggeNr']);
}

sw_send_csv('fart_detalj.csv',$headers,$rows);
