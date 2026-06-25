<?php
/**
 * dimu_hent_dimukode.php
 * 
 * Henter DIMUKODE fra DigitaltMuseum API for hvert skip i nmm_skip,
 * basert på UUID-kolonnen. Lagrer alle DIMUKODER kommaseparert i
 * DIMUKODE-kolonnen.
 * 
 * Kjøres fra kommandolinje: php dimu_hent_dimukode.php
 * eller i browser via XAMPP.
 */

// ---------------------------------------------------------------------------
// KONFIGURASJON – tilpass disse
// ---------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'skipsweb_skipsdb');   // <-- bytt
define('DB_USER', 'root');
define('DB_PASS', '');
define('DIMU_API_KEY', 'demo');      // <-- bytt til din nøkkel hvis du har en
define('BARE_TOMME', true);          // true = hopp over rader som allerede har DIMUKODE
define('PAUSE_MS', 250);             // millisekunder mellom API-kall (vær snill mot API)
// ---------------------------------------------------------------------------

set_time_limit(0);

// --- Utskriftsformat (fungerer i både CLI og browser) ---
$cli = (php_sapi_name() === 'cli');
function ut(string $tekst, bool $linjeskift = true): void {
    global $cli;
    echo $tekst . ($linjeskift ? ($cli ? "\n" : "<br>\n") : '');
}

if (!$cli) {
    echo '<pre>';
}

// --- DB-tilkobling ---
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ut("DB-FEIL: " . $e->getMessage());
    exit(1);
}

// --- Hent UUIDs fra databasen ---
$sql = "SELECT UUID FROM tblxdigmuseum WHERE UUID IS NOT NULL AND UUID != ''";
if (BARE_TOMME) {
    $sql .= " AND (DIMUKODE IS NULL OR DIMUKODE = '')";
}
$uuids = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

$totalt = count($uuids);
ut("Fant $totalt UUIDs å behandle.");
ut(str_repeat('-', 60));

$stm_oppdater = $pdo->prepare(
    "UPDATE tblxdigmuseum SET DIMUKODE = :dimukode WHERE UUID = :uuid"
);

$ok = 0;
$ingenTreff = 0;
$feil = 0;

foreach ($uuids as $nr => $uuid) {
    $posisjon = $nr + 1;
    ut("[$posisjon/$totalt] $uuid ... ", false);

    $dimukoder = [];
    $start     = 0;
    $antallFunnet = null;

    // --- Paginert API-kall ---
    do {
        $params = [
            'q'       => '*',
            'fq'      => "artifact.uuid:$uuid",
            'wt'      => 'json',
            'fl'      => 'artifact.uniqueId',
            'rows'    => 100,
            'start'   => $start,
            'api.key' => DIMU_API_KEY,
        ];

        $url  = 'https://api.dimu.org/api/solr/select?' . http_build_query($params);
        $json = @file_get_contents($url);

        if ($json === false) {
            ut("FEIL (ingen respons fra API)");
            $feil++;
            break 2; // hopp til neste UUID
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            ut("FEIL (ugyldig JSON)");
            $feil++;
            break 2;
        }

        $antallFunnet = (int)($data['response']['numFound'] ?? 0);
        $docs         = $data['response']['docs']     ?? [];

        foreach ($docs as $doc) {
            if (!empty($doc['artifact.uniqueId'])) {
                $dimukoder[] = $doc['artifact.uniqueId'];
            }
        }

        $start += 100;

    } while ($start < $antallFunnet);

    // --- Lagre i DB ---
    if (empty($dimukoder)) {
        ut("ingen treff i DiMu");
        $ingenTreff++;
        continue;
    }

    $dimukodeStr = implode(',', $dimukoder);
    $stm_oppdater->execute([
        ':dimukode' => $dimukodeStr,
        ':uuid'     => $uuid,
    ]);

    $antall = count($dimukoder);
    ut("$antall DIMUKODE" . ($antall > 1 ? "r" : "") . ": $dimukodeStr");
    $ok++;

    usleep(PAUSE_MS * 1000);
}

// --- Oppsummering ---
ut(str_repeat('-', 60));
ut("Ferdig.");
ut("  Oppdatert:    $ok");
ut("  Ingen treff:  $ingenTreff");
ut("  Feil:         $feil");

if (!$cli) {
    echo '</pre>';
}
