<?php
/**
 * session_check.php
 * Legg denne filen i roten av hvert repo og inkluder den
 * øverst i repoets index.php:
 *
 *   require_once 'session_check.php';
 *
 * Hvis brukeren kom fra portalen er session allerede satt
 * og de slipper rett inn. Ellers videresendes de til
 * repoets egen loginside.
 */

session_set_cookie_params(['domain' => '.skipsweb.no', 'path' => '/', 'secure' => true, 'httponly' => true]);
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // Ikke innlogget via portal — send til repoets egen login
    header('Location: login.php');
    exit;
}
