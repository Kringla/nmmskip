<?php
// /includes/bootstrap.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php'; // BASE_URL m.m. (tom i prod -> ok)
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mysqli: kast exceptions ved feil (bedre enn "stille" feil)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Les konfig, støtt både nytt (DB_RO_*/DB_RW_*) og gammelt (DB_USER/DB_PASSWORD)
$DB_HOST = defined('DB_HOST') ? DB_HOST : 'localhost';
$DB_NAME = defined('DB_NAME') ? DB_NAME : '';
$DB_RO_USER = defined('DB_RO_USER') ? DB_RO_USER : (defined('DB_USER') ? DB_USER : null);
$DB_RO_PASS = defined('DB_RO_PASS') ? DB_RO_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : null);
$DB_RW_USER = defined('DB_RW_USER') ? DB_RW_USER : null;
$DB_RW_PASS = defined('DB_RW_PASS') ? DB_RW_PASS : null;

/**
 * Delt Read-Only-tilkobling (cachet)
 */
function db(): mysqli {
    static $ro = null;
    if ($ro instanceof mysqli) return $ro;

    // Bruk konstanter (fallback til gamle DB_USER/DB_PASSWORD)
    $h = defined('DB_HOST') ? DB_HOST : 'localhost';
    $d = defined('DB_NAME') ? DB_NAME : '';
    $u = defined('DB_RO_USER') ? DB_RO_USER : (defined('DB_USER') ? DB_USER : null);
    $p = defined('DB_RO_PASS') ? DB_RO_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : null);

    $ro = new mysqli($h, (string)($u ?? ''), (string)($p ?? ''), $d);
    $ro->set_charset('utf8mb4');
    return $ro;
}

/**
 * Ny Read-Write-tilkobling (bruk KUN ved skriving)
 */
function db_rw(): mysqli {
    // Fall-back: hvis DB_RW_* ikke finnes, bruk DB_RO_* eller DB_USER/DB_PASSWORD
    $h = defined('DB_HOST') ? DB_HOST : 'localhost';
    $d = defined('DB_NAME') ? DB_NAME : '';
    $u = defined('DB_RW_USER') ? DB_RW_USER : (defined('DB_RO_USER') ? DB_RO_USER : (defined('DB_USER') ? DB_USER : null));
    $p = defined('DB_RW_PASS') ? DB_RW_PASS : (defined('DB_RO_PASS') ? DB_RO_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : null));

    $rw = new mysqli($h, (string)($u ?? ''), (string)($p ?? ''), $d);
    $rw->set_charset('utf8mb4');
    return $rw;
}

/**
 * Transaksjonell hjelper for skriveoperasjoner
 */
function with_rw(callable $fn) {
    $rw = db_rw();
    $rw->begin_transaction();
    try {
        $result = $fn($rw);
        $rw->commit();
        $rw->close();
        return $result;
    } catch (Throwable $e) {
        $rw->rollback();
        $rw->close();
        throw $e;
    }
}

// Bakoverkompatible aliaser for eksisterende kode
$mysqli = db();
$conn   = $mysqli;
$db = db();
