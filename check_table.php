<?php
require 'includes/bootstrap.php';
$res = db()->query("SHOW TABLES LIKE 'tblxdigmuseum'");
$row = $res ? $res->fetch_row() : null;
var_export($row);

$res = db()->query("SHOW TABLES LIKE 'tblxdigmuseumx'");
$row = $res ? $res->fetch_row() : null;
var_export($row);
