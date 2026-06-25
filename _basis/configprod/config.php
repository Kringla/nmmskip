<?php
/**
 * Standard konfigurasjon for sterkestav
 *
 * VIKTIG: Ikke commit sensitiv informasjon her!
 * Bruk config.local.php for lokale/production overrides
 */

// Standard konfigurasjon (XAMPP lokalt)
$config = [
    'db_host' => '127.0.0.1',
    'db_name' => 'sterkestav',
    'db_user' => 'root',
    'db_pass' => '',
    'base_url' => '/mgmt/'
];

// Last inn lokale overrides hvis filen eksisterer
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    $local = require $localConfig;
    $config = array_merge($config, $local);
}

return $config;
