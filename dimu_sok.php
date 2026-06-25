<?php

// =========================================================================================
// KONSTANTER OG FEILMELDINGER
// =========================================================================================
const FEIL_INGEN_ID = "Fant ingen DIMU ID i søkeresultatet for det angitte fartøyet.";
const FEIL_IKKE_BILDE = "Fant ikke den primære bilde-URL-en på objektets side.";
const FEIL_NETTV = "Feil ved nettverksforespørsel. HTTP Status:";
const EIER_KODE = 'NMM'; // Hardkodet til Norsk Maritimt Museum

// =========================================================================================
// VARIABEL INITIALISERING (For å unngå "Undefined Variable" Warnings)
// =========================================================================================
$resultat_url = '';
$fartøy_navn = '';
$årstall = ''; 
$modus = 'første'; // Standardmodus
$melding = '';
$dimu_id = '';
$melding_tillegg = '';
$full_søkeord = '';
$søkeord_encoded = '';


// =========================================================================================
// HJELPEFUNKSJONER
// =========================================================================================

/**
 * Henter innholdet fra en URL ved hjelp av cURL, følger omdirigeringer.
 */
function hent_url_innhold($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Følger omdirigeringer (f.eks. 301)
    curl_setopt($ch, CURLOPT_USERAGENT, 'Norsk Maritimt Museum Scraper/1.0');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return ['error' => FEIL_NETTV . ' ' . $http_code];
    }
    return ['content' => $data];
}

/**
 * Finner den første DIMU ID (10-14 siffer) fra HTML-innhold.
 */
function finn_dimu_id($html_content) {
    // Regex: ser etter mønsteret /<10-14 siffer>/
    if (preg_match('/\/(\d{10,14})\//', $html_content, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Finner *alle* ID-er fra HTML-innholdet for sortering.
 */
function finn_alle_dimu_ider($html_content) {
    $ids = [];
    if (preg_match_all('/\/(\d{10,14})\//', $html_content, $matches)) {
        $ids = array_unique($matches[1]);
    }
    return $ids;
}

/**
 * Henter den faktiske bilde-URL-en fra objektets side ved hjelp av og:image meta-taggen.
 */
function hent_bilde_url($dimu_id) {
    $url_object = "https://digitaltmuseum.no/" . $dimu_id;
    $response = hent_url_innhold($url_object);

    if (isset($response['error'])) {
        return $response['error'];
    }

    $html_content = $response['content'];
    
    // Regex: ser etter <meta property="og:image" content="[URL-en]"> (mest pålitelig)
    if (preg_match('/<meta\s+property="og:image"\s+content="([^"]+)"/i', $html_content, $matches)) {
        return $matches[1];
    }

    return FEIL_IKKE_BILDE;
}


// =========================================================================================
// HOVEDLOGIKK
// =========================================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['fartoy_navn'])) {
    
    // Hent data fra skjemaet
    $fartøy_navn = trim($_POST['fartoy_navn']);
    $årstall = trim($_POST['aarstall'] ?? ''); 
    $modus = trim($_POST['modus'] ?? 'første'); 

    // 1. Bygg det fulle søkeordet: Fartøynavn + Årstall (hvis satt)
    $full_søkeord = $fartøy_navn;
    if (preg_match('/^\d{4}$/', $årstall)) { // Sjekker at det er et 4-sifret tall
        $full_søkeord .= " " . $årstall;
    }
    
    $søkeord_encoded = urlencode($full_søkeord);
    
    // Bygg grunn-URLen
    $url_search = "https://digitaltmuseum.no/search?q=" . $søkeord_encoded . "&type=Photograph";
    $url_search .= "&owner_code=" . urlencode(EIER_KODE); // Hardkodet eierkode
    $url_search .= "&count=1"; 

    $dimu_id = null; // Tilbakestill ID

    // 2. Utfør Søk basert på Modus
    if ($modus === 'laveste' || $modus === 'høyeste') {
        // Øk antall resultater for å få en liste å sortere fra
        $url_search = str_replace("count=1", "count=100", $url_search); 
        $response = hent_url_innhold($url_search);
        
        if (isset($response['error'])) {
            $melding = $response['error'];
        } else {
            $dimu_ids = finn_alle_dimu_ider($response['content']);
            
            if (empty($dimu_ids)) {
                $melding = FEIL_INGEN_ID;
            } else {
                sort($dimu_ids, SORT_NUMERIC); // Sorter numerisk
                $dimu_id = ($modus === 'laveste') ? $dimu_ids[0] : end($dimu_ids); 
                $melding_tillegg = " (Simulert " . $modus . " ID fra " . count($dimu_ids) . " treff)";
            }
        }
        
    } else { // Modus: Første treff
        $response = hent_url_innhold($url_search);
        
        if (isset($response['error'])) {
            $melding = $response['error'];
        } else {
            $dimu_id = finn_dimu_id($response['content']); 
        }
    }


    // 3. Hent Bilde URL og vis resultat
    if ($dimu_id) {
        $resultat_url = hent_bilde_url($dimu_id);

        if (strpos($resultat_url, FEIL_IKKE_BILDE) === 0 || strpos($resultat_url, FEIL_NETTV) === 0) {
            $melding = "Fant DIMU ID **" . $dimu_id . "**, men klarte ikke å hente bilde-URL.";
            $resultat_url = "";
        } else {
            $melding = "Suksess! Funnet bilde-URL for **" . $fartøy_navn . "** (ID: " . $dimu_id . ")" . ($melding_tillegg ?? '');
        }
        
    } elseif ($melding === '') {
        $melding = FEIL_INGEN_ID;
    }
}
?>

<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="UTF-8">
    <title>DIMU Bilde-URL Henter</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-dimu-sok">
    <h1>Digitalt Museum Bilde-URL Henter (NMM)</h1>
    
    <form method="POST">
        <label for="fartoy_navn">Fartøyets navn (f.eks. M/S Bataan):</label>
        <input type="text" id="fartoy_navn" name="fartoy_navn" value="<?php echo htmlspecialchars($fartøy_navn); ?>" required>

        <label for="aarstall">Årstall (Valgfritt, 4 siffer):</label>
        <input type="text" id="aarstall" name="aarstall" maxlength="4" pattern="\d{4}" title="Skriv inn et 4-sifret årstall" value="<?php echo htmlspecialchars($årstall); ?>">
        
        <p>Eier er satt fast til: <strong>Norsk Maritimt Museum (<?php echo EIER_KODE; ?>)</strong></p>
        
        <label for="modus">Velg resultatmodus:</label>
        <select name="modus" id="modus">
            <option value="første" <?php echo $modus === 'første' ? 'selected' : ''; ?>>Første treff (Raskest)</option>
            <option value="laveste" <?php echo $modus === 'laveste' ? 'selected' : ''; ?>>Laveste DIMUkode nr. (Simulert)</option>
            <option value="høyeste" <?php echo $modus === 'høyeste' ? 'selected' : ''; ?>>Høyeste DIMUkode nr. (Simulert)</option>
        </select>
        
        <input type="submit" value="Søk etter bilde-URL">
    </form>

    <?php if ($melding): ?>
        <div class="melding <?php echo strpos($melding, 'Suksess') === 0 ? 'resultat' : 'error'; ?>">
            <?php echo $melding; ?>
            <?php if ($resultat_url): ?>
                <p>Direkte URL:</p>
                <a href="<?php echo htmlspecialchars($resultat_url); ?>" target="_blank" class="url-link"><?php echo htmlspecialchars($resultat_url); ?></a>
                <p><img src="<?php echo htmlspecialchars($resultat_url); ?>" alt="Funnet bilde" style="max-width: 100%; height: auto; margin-top: 10px; border: 1px solid #ddd;"></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>