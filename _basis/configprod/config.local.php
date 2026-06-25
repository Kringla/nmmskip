<?php
return [
    // Database-innstillinger
    'db_host' => 'hostmaster.onnet.no',   // Database server (localhost eller ekstern server)
    'db_name' => 'rlzrgpsx_sterkestav',    // Database-navn
    'db_user' => 'rlzrgpsx_stavbruker',      // Database bruker
    'db_pass' => 'Use!Web?',              // Database passord

    // Applikasjon-innstillinger
    'base_url' => '/',     // Base URL for applikasjonen (med trailing slash)
                                       // Lokalt: '/sterkestav/'
                                       // Produksjon: '/' eller '/path/to/app/'

    'mgmt_url' => 'https://mgmt.sterkestav.no/',  // URL til mgmt-applikasjonen
                                                    // Lokalt: '/mgmt/'
                                                    // Produksjon: 'https://mgmt.sterkestav.no/'

    // Miljø (development/production)
    'environment' => 'production',   // development = vis feilmeldinger
                                       // production = skjul feilmeldinger

    // Session-innstillinger (valgfritt)
    'session_name' => 'STERKESTAV',    // Session cookie navn (må være likt i begge apper)
    'session_domain' => '.sterkestav.no',  // Cookie-domain for delt session på tvers av subdomener
                                            // Lokalt: '' (tom = bare localhost)
                                            // Produksjon: '.sterkestav.no'
    'session_lifetime' => 7200,       // Session lengde i sekunder (2 timer)

    // Sikkerhet (valgfritt)
    'enable_csrf' => true,            // CSRF-beskyttelse (anbefalt: true)

    // Upload-innstillinger (valgfritt)
    'max_upload_size' => 10485760,    // Maks filstørrelse i bytes (10MB)
    'upload_dir' => __DIR__ . '/../uploads/bilag/',  // Upload-mappe
];
