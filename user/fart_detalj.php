<?php
// user/fart_detalj.php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php'; // for meny/rolle

// helpers from includes/functions.php
function val($arr, $key, $def=''){ return isset($arr[$key]) ? $arr[$key] : $def; }

if (!function_exists('sw_fetch_remote_html')) {
    /**
     * Hjelpefunksjon for å hente HTML via cURL med file_get_contents-fallback.
     */
    function sw_fetch_remote_html(string $url): ?string {
        if ($url === '') {
            return null;
        }

        $html = null;

        if (function_exists('curl_init')) {
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 4,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_USERAGENT      => 'SkipsWeb/1.0 (+https://digitaltmuseum.no/)',
                ]);
                $body = curl_exec($ch);
                if ($body !== false) {
                    $html = $body;
                }
            } catch (Throwable $e) {
                $html = null;
            }
        }

        if (!is_string($html) || $html === '') {
            $context = stream_context_create([
                'http'  => ['timeout' => 4],
                'https' => ['timeout' => 4],
            ]);
            $body = @file_get_contents($url, false, $context);
            if ($body !== false) {
                $html = $body;
            }
        }

        return is_string($html) && $html !== '' ? $html : null;
    }
}

if (!function_exists('sw_fetch_dimu_image')) {
    /**
     * Hent DigitaltMuseum-forhåndsvisningsbilde (og:image/twitter:image).
     * Returnerer direkte bilde-URL eller null hvis ikke funnet.
     */
    function sw_fetch_dimu_image(string $url): ?string {
        $html = sw_fetch_remote_html($url);

        if (!is_string($html) || $html === '') {
            return null;
        }

        $patterns = [
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
            '/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $src = htmlspecialchars_decode($m[1], ENT_QUOTES);
                if (is_string($src) && filter_var($src, FILTER_VALIDATE_URL)) {
                    return $src;
                }
            }
        }

        return null;
    }
}

if (!function_exists('sw_fetch_dimu_first_object')) {
    /**
     * Hent første objekt-URL fra DigitaltMuseum-søk.
     *
     * @param string $searchUrl Komplett søke-URL.
     * @return string|null Absolutt URL til første objekt, ellers null.
     */
    function sw_fetch_dimu_first_object(string $searchUrl): ?string {
        $html = sw_fetch_remote_html($searchUrl);

        if (!is_string($html) || $html === '') {
            return null;
        }

        if (preg_match_all('#href="(?:https://digitaltmuseum\\.no)?/([0-9]{2}[0-9A-Za-z/_\\-]+)"#i', $html, $matches)) {
            $seen = [];
            foreach ($matches[1] as $path) {
                $candidate = trim($path);
                if ($candidate === '') {
                    continue;
                }
                $candidate = preg_split('/[?#]/', $candidate)[0];
                if ($candidate === '') {
                    continue;
                }
                if (!preg_match('#^(0[0-9]{2})#', $candidate)) {
                    continue;
                }
                $abs = 'https://digitaltmuseum.no/' . ltrim($candidate, '/');
                if (!isset($seen[$abs])) {
                    return $abs;
                }
            }
        }

        return null;
    }
}

if (!function_exists('sw_build_dimu_search_url')) {
    /**
     * Bygg DigitaltMuseum-søk basert på deler og eventuelt år.
     *
     * @return array{url:string,term:string}
     */
    function sw_build_dimu_search_url(array $parts, ?int $year = null, array $opts = []): array {
        $owner = isset($opts['owner_code']) ? trim((string)$opts['owner_code']) : 'NMM';
        if ($owner === '') {
            $owner = 'NMM';
        }

        $count = isset($opts['count']) ? (int)$opts['count'] : 100;
        if ($count < 1) {
            $count = 1;
        }

        $base = 'https://digitaltmuseum.no/search?type=Photograph';
        $base .= '&owner_code=' . rawurlencode($owner);
        $base .= '&count=' . $count;

        $normalized = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '') {
                $normalized[] = $part;
            }
        }

        if ($year !== null) {
            $year = (int)$year;
            if ($year >= 1000 && $year <= 9999) {
                $normalized[] = (string)$year;
            }
        }

        $term = trim(implode(' ', $normalized));
        $url  = $base;

        if ($term !== '') {
            $encoded = rawurlencode($term);
            $encoded = str_replace(['%2F', '%2f'], '/', $encoded);
            $url .= '&q=' . $encoded;
        }

        return ['url' => $url, 'term' => $term];
    }
}

if (!function_exists('sw_fetch_dimu_lowest_id')) {
    /**
     * Returner laveste DIMU-id for et gitt søkeoppsett.
     */
    function sw_fetch_dimu_lowest_id(array $parts, ?int $year = null, array $opts = []): ?string {
        $search = sw_build_dimu_search_url($parts, $year, $opts);
        $html   = sw_fetch_remote_html($search['url']);

        if (!is_string($html) || $html === '') {
            return null;
        }

        if (!preg_match_all('#/(\\d{10,14})/#', $html, $matches)) {
            return null;
        }

        $ids = array_unique($matches[1]);
        if (empty($ids)) {
            return null;
        }

        sort($ids, SORT_NUMERIC);
        return $ids[0];
    }
}

// Parametre
$obj_id  = isset($_GET['obj_id']) ? (int)$_GET['obj_id'] : 0;
// Nytt schema bruker FartTid_ID i lenker/bilder; støtt både tid_id og historisk navn_id
$tid_id = 0;
if (isset($_GET['tid_id'])) {
    $tid_id = (int)$_GET['tid_id'];
} elseif (isset($_GET['navn_id'])) {
    $tid_id = (int)$_GET['navn_id'];
} elseif (isset($_GET['ft'])) {
    // Fallback for eldre lenker: ?ft=FartTid_ID
    $tid_id = (int)$_GET['ft'];
}

// Fallback: Dersom vi mangler obj_id men har tid_id (eller ft), forsøk å hente FartObj_ID
if ($obj_id <= 0 && $tid_id > 0 && isset($conn)) {
    $stmt = $conn->prepare("SELECT FartObj_ID FROM tblfarttid WHERE FartTid_ID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $tid_id);
        $stmt->execute();
        $tmpRes = $stmt->get_result();
        if ($tmpRes) {
            $row = $tmpRes->fetch_assoc();
            if ($row && isset($row['FartObj_ID'])) {
                $obj_id = (int)$row['FartObj_ID'];
            }
            $tmpRes->free();
        }
        $stmt->close();
    }
}

if ($obj_id <= 0 || $tid_id <= 0) {
    http_response_code(400);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Mangler eller ugyldige parametre: obj_id og tid_id må være > 0.";
    exit;
}

// Hent valgt navneoppføring (tblFartTid) + typeforkortelse og nasjonsnavn
$main = null;
$stmt = $conn->prepare(
    "SELECT t.*,
            zft.TypeFork,
            zn.Nasjon AS NasjonNavn
     FROM tblfarttid t
     LEFT JOIN tblzfarttype zft ON zft.FartType_ID = t.FartType_ID
     LEFT JOIN tblznasjon   zn  ON zn.Nasjon_ID    = t.Nasjon_ID
     WHERE t.FartTid_ID = ?
     LIMIT 1"
);
if ($stmt) {
    $stmt->bind_param("i", $tid_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $main = $res->fetch_assoc();
    $stmt->close();
}
if (!$main) {
    echo "Fant ingen detaljer for angitt objekt/tid.";
    exit;
}

// Hent TypeFork (ev. via spesifikasjon om den mangler direkte)
$typeFork = '';
if (isset($main['TypeFork'])) {
    $typeFork = trim((string)$main['TypeFork']);
}
if ($typeFork === '') {
    $stmt = $conn->prepare(
        "SELECT zft.TypeFork
         FROM tblfarttid t
         LEFT JOIN tblfartspes fs ON fs.FartSpes_ID = t.FartSpes_ID
         LEFT JOIN tblzfarttype zft ON zft.FartType_ID = fs.FartType_ID
         WHERE t.FartTid_ID = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('i', $tid_id);
        $stmt->execute();
        $resTF = $stmt->get_result();
        if ($resTF) {
            $tfRow = $resTF->fetch_assoc();
            if ($tfRow && isset($tfRow['TypeFork'])) {
                $typeFork = trim((string)$tfRow['TypeFork']);
            }
            $resTF->free();
        }
        $stmt->close();
    }
}

// Bilde: prøv lokalt i /assets/img/skip basert på fartøynavn; ellers placeholder2.jpg
$imageSrc = '/assets/img/skip/fartoydetaljer_1.jpg';
// Overstyr bilde: prøv lokalt i /assets/img/skip basert på fartøynavn; ellers placeholder2.jpg
$localFallback = '/assets/img/skip/placeholder2.jpg';
try {
    $shipName = isset($main['FartNavn']) ? trim((string)$main['FartNavn']) : '';
    if ($shipName !== '') {
        $imgDir  = __DIR__ . '/../assets/img/skip';
        $webBase = '/assets/img/skip';
        $baseName = preg_replace('/[^\p{L}0-9 _\-]+/u', '', $shipName);
        $variants = [];
        $variants[] = str_replace(' ', '_', $baseName);
        $variants[] = str_replace(' ', '', $baseName);
        $variants = array_values(array_unique(array_filter($variants)));
        $exts = ['jpg','jpeg','png','webp','JPG','JPEG','PNG','WEBP'];
        $found = null;
        foreach ($variants as $v) {
            foreach ($exts as $ext) {
                $fsPath = $imgDir . '/' . $v . '.' . $ext;
                if (file_exists($fsPath)) { $found = $webBase . '/' . $v . '.' . $ext; break 2; }
            }
        }
        if ($found) { $imageSrc = $found; }
        else { $imageSrc = $localFallback; }
    } else {
        $imageSrc = $localFallback;
    }
} catch (Throwable $e) {
    $imageSrc = '/assets/img/skip/placeholder2.jpg';
}
// relativ sti for hero-bakgrunn (fila ligger i /user)
$imageSrcRel = (substr($imageSrc, 0, 1) === '/') ? ('..' . $imageSrc) : $imageSrc;

// DigitaltMuseum-lenker (tblxDigMuseum) for denne navneoppføringen
$dimuList = [];
$stmt = $conn->prepare(
    "SELECT DIMUkode, COALESCE(Motiv,'') AS Motiv
     FROM tblxdigmuseum
     WHERE FartTid_ID = ? AND COALESCE(DIMUkode,'') <> ''
     ORDER BY SerID DESC"
);
if ($stmt) {
    $stmt->bind_param("i", $tid_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $kode  = trim((string)$row['DIMUkode']);
        $motiv = (string)$row['Motiv'];
        $dimuList[] = [
            'kode'  => $kode,
            'motiv' => $motiv,
            'url'   => 'https://digitaltmuseum.no/' . $kode
        ];
    }
    $stmt->close();
}


// Navnehistorikk for hele objektet
$navnehist = [];
$stmt = $conn->prepare(
    "SELECT t.FartTid_ID,
            t.YearTid,
            t.MndTid,
            t.FartNavn,
            COALESCE(t.FartType_ID, fs.FartType_ID) AS FartType_ID,
            zft.TypeFork,
            t.Rederi,
            t.RegHavn,
            zn.Nasjon
     FROM tblfarttid t
     LEFT JOIN tblfartspes  fs  ON fs.FartSpes_ID  = t.FartSpes_ID
     LEFT JOIN tblzfarttype zft ON zft.FartType_ID = COALESCE(t.FartType_ID, fs.FartType_ID)
     LEFT JOIN tblznasjon   zn  ON zn.Nasjon_ID    = t.Nasjon_ID
     WHERE t.FartObj_ID = ?
     ORDER BY COALESCE(t.YearTid,0),
              COALESCE(t.MndTid,0),
              t.FartTid_ID"
);
if ($stmt) {
    $stmt->bind_param("i", $obj_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $mm  = (int)$r['MndTid'];
        $mmS = $mm > 0 ? str_pad((string)$mm, 2, '0', STR_PAD_LEFT) : '00';
        $r['Tidspunkt'] = (string)val($r, 'YearTid', '') . '/' . $mmS;
        $prefix = trim((string)val($r,'TypeFork',''));
        $r['NavnKomp'] = ($prefix !== '' ? $prefix.' ' : '') . trim((string)val($r,'FartNavn',''));
        $navnehist[] = $r;
    }
    $stmt->close();
}

// Øvrige lenker (tblxFartLink) for denne navneoppføringen
$fartLinks = [];
$stmt = $conn->prepare(
    "SELECT COALESCE(LinkType,'') AS LinkType,
            COALESCE(LinkInnh,'') AS LinkInnh,
            Link,
            COALESCE(SerNo, 9999) AS SortNo
     FROM tblxfartlink
     WHERE FartTid_ID = ? AND COALESCE(Link,'') <> ''
     ORDER BY SerNo ASC"
);
if ($stmt) {
    $stmt->bind_param("i", $tid_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $fartLinks[] = $row;
    }
    $stmt->close();
}

// Hent objektdata (tblFartObj) med verftnavn for Leverandør og Skrogbygger,
// og STROK-navn fra tblzStroket (via StroketID)
$objRow = null;
$objStmt = $conn->prepare(
    "SELECT o.*,
            CONCAT_WS(', ', v1.VerftNavn, v1.Sted) AS LeverandorNavn,
            CONCAT_WS(', ', v2.VerftNavn, v2.Sted) AS SkrogbyggerNavn,
            zs.Strok AS StroketNavn
     FROM tblfartobj o
     LEFT JOIN tblverft    v1 ON v1.Verft_ID     = o.LeverID
     LEFT JOIN tblverft    v2 ON v2.Verft_ID     = o.SkrogID
     LEFT JOIN tblzstroket zs ON zs.Stroket_ID   = o.StroketID
     WHERE o.FartObj_ID = ?
     LIMIT 1"
);
if ($objStmt) {
    $objStmt->bind_param('i', $obj_id);
    $objStmt->execute();
    $objRes = $objStmt->get_result();
    if ($objRes) { $objRow = $objRes->fetch_assoc(); $objRes->free(); }
    $objStmt->close();
}

$fartNavn = trim((string)val($main, 'FartNavn', ''));

$byggetYear = null;
if (isset($objRow) && is_array($objRow) && isset($objRow['Bygget'])) {
    $byggetRaw = trim((string)$objRow['Bygget']);
    if (preg_match('/^\d{4}$/', $byggetRaw)) {
        $byggetYear = (int)$byggetRaw;
    }
}

$dimuSearchParts = [];
if ($typeFork !== '') { $dimuSearchParts[] = $typeFork; }
if ($fartNavn !== '') { $dimuSearchParts[] = $fartNavn; }
$searchOpts   = ['owner_code' => 'NMM', 'count' => 100];
$searchConfig = sw_build_dimu_search_url($dimuSearchParts, $byggetYear, $searchOpts);
$dimuSearchUrl  = $searchConfig['url'];
$dimuSearchTerm = $searchConfig['term'];

$dimuLowestId = null;

if ($imageSrc === $localFallback && $dimuSearchTerm !== '') {
    $dimuLowestId = sw_fetch_dimu_lowest_id($dimuSearchParts, $byggetYear, $searchOpts);
    if (is_string($dimuLowestId) && $dimuLowestId !== '') {
        $preview = sw_fetch_dimu_image('https://digitaltmuseum.no/' . ltrim($dimuLowestId, '/'));
        if (is_string($preview) && $preview !== '') {
            $imageSrc = $preview;
        }
    }
}

$imageSrcRel = (substr($imageSrc, 0, 1) === '/') ? ('..' . $imageSrc) : $imageSrc;

// Utled visningsfelter
$mmCur   = (int)val($main,'MndTid',0);
$mmCurS  = $mmCur > 0 ? str_pad((string)$mmCur, 2, '0', STR_PAD_LEFT) : '00';
$tidStr  = (string)val($main,'YearTid','') . '/' . $mmCurS;

$navn    = val($main,'FartNavn','(ukjent navn)');
$visNavn = $typeFork !== '' ? trim($typeFork.' '.$navn) : $navn;

?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<!-- Hero image -->
<div class="container">
    <section class="hero" style="background-image:url('<?= h($imageSrcRel) ?>'); background-size:cover; background-position:center;">
        <div class="hero-overlay"></div>
    </section>
</div>

<div class="container">
    <h1 style="text-align:center;">Fartøydetaljer</h1>

    <?php $qs = http_build_query(['obj_id' => $obj_id, 'tid_id' => $tid_id]); $printBase = rtrim(BASE_URL,'/') . '/user/print_fart_detalj.php?' . $qs; $csvUrl = rtrim(BASE_URL,'/') . '/user/export_fart_detalj.php?' . $qs; ?>
    <div class="export-actions" style="margin-bottom:.5rem;">
      <a class="mdc-button mdc-button--raised btn" href="<?= h($printBase . '&orient=P') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 portrett)</span></a>
      <a class="mdc-button mdc-button--raised btn" href="<?= h($printBase . '&orient=L') ?>" target="_blank" rel="noopener"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Utskriftsvisning (A4 landskap)</span></a>
      <a class="mdc-button mdc-button--raised btn" href="<?= h($csvUrl) ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Last ned CSV</span></a>
      <a class="mdc-button mdc-button--raised btn" href="<?= h($dimuSearchUrl) ?>" target="_blank" rel="noopener noreferrer"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Se NMMs bilder i DiMu</span></a>
    </div>
    <!-- Hovedinfo-boks -->
    <div class="mdc-card card mb-3" style="padding:1rem;">
      <h2 class="h4">Objektinformasjon</h2>
      <div class="mdc-data-table"><div class="mdc-data-table__table-container">
      <table class="mdc-data-table__table table compact table-sm table-borderless align-middle">
        <tbody class="mdc-data-table__content">
          <?php if (!empty($objRow['Bygget'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end" style="width:30%">Bygget (år)</th>
            <td class="mdc-data-table__cell"><?= h($objRow['Bygget']) ?></td>
          </tr>
          <?php endif; ?>

          <tr class="mdc-data-table__row">
            <th class="text-end" style="width:30%">Navn gitt ved bygging</th>
            <td class="mdc-data-table__cell"><?= h($objRow['NavnObj'] ?? '') ?></td>
          </tr>

          <?php if ($typeFork !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Fartøystype</th>
            <td class="mdc-data-table__cell"><?= h($typeFork) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['IMO'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">IMO</th>
            <td class="mdc-data-table__cell"><?= h($objRow['IMO']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Kontrahert'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Kontrahert</th>
            <td class="mdc-data-table__cell"><?= h($objRow['Kontrahert']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Kjolstrukket'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Kjølstrukket</th>
            <td class="mdc-data-table__cell"><?= h($objRow['Kjolstrukket']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Sjosatt'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Sjøsatt</th>
            <td class="mdc-data-table__cell"><?= h($objRow['Sjosatt']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Levert'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Levert</th>
            <td class="mdc-data-table__cell"><?= h($objRow['Levert']) ?></td>
          </tr>
          <?php endif; ?>

          <?php
            $levNavn = trim((string)val($objRow,'LeverandorNavn',''));
            $levID   = trim((string)val($objRow,'LeverID',''));
          ?>
          <?php if ($levNavn !== '' || $levID !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Leverandør</th>
            <td class="mdc-data-table__cell">
              <?= h($levNavn !== '' ? $levNavn : '') ?>
              <?php if ($levNavn === '' && $levID !== ''): ?>
                (ID: <?= h($levID) ?>)
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['ByggeNr'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Byggenr</th>
            <td class="mdc-data-table__cell"><?= h($objRow['ByggeNr']) ?></td>
          </tr>
          <?php endif; ?>

          <?php
            $skrogNavn = trim((string)val($objRow,'SkrogbyggerNavn',''));
            $skrogID   = trim((string)val($objRow,'SkrogID',''));
          ?>
          <?php if ($skrogNavn !== '' || $skrogID !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Skrogbygger</th>
            <td class="mdc-data-table__cell">
              <?= h($skrogNavn !== '' ? $skrogNavn : '') ?>
              <?php if ($skrogNavn === '' && $skrogID !== ''): ?>
                (ID: <?= h($skrogID) ?>)
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['BnrSkrog'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">BnrSkrog</th>
            <td class="mdc-data-table__cell"><?= h($objRow['BnrSkrog']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['StroketYear'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">StrøketYear</th>
            <td class="mdc-data-table__cell"><?= h($objRow['StroketYear']) ?></td>
          </tr>
          <?php endif; ?>

          <?php
            $strokNavn = trim((string)val($objRow,'StroketNavn',''));
            $strokID   = trim((string)val($objRow,'StroketID',''));
          ?>
          <?php if ($strokNavn !== '' || $strokID !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Strøket grunnet</th>
            <td class="mdc-data-table__cell">
              <?= h($strokNavn !== '' ? $strokNavn : '') ?>
              <?php if ($strokNavn === '' && $strokID !== ''): ?>
                (ID: <?= h($strokID) ?>)
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Historikk'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Historikk</th>
            <td class="mdc-data-table__cell"><?= nl2br(h($objRow['Historikk'])) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['ObjNotater'])): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">ObjNotater</th>
            <td class="mdc-data-table__cell"><?= nl2br(h($objRow['ObjNotater'])) ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div></div>
    </div>

    <div class="mdc-card card mb-3" style="padding:1rem;">
      <h2 class="h4">Navneoppføring</h2>
      <div class="mdc-data-table"><div class="mdc-data-table__table-container">
      <table class="mdc-data-table__table table compact table-sm table-borderless align-middle">
        <tbody class="mdc-data-table__content">
          <tr class="mdc-data-table__row">
            <th class="text-end" style="width:30%">Navn</th>
            <td class="mdc-data-table__cell"><?= h($visNavn) ?></td>
          </tr>
          <tr class="mdc-data-table__row">
            <th class="text-end">Tidspunkt</th>
            <td class="mdc-data-table__cell"><?= h($tidStr) ?></td>
          </tr>

          <?php if (val($main,'NasjonNavn','') !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Nasjon</th>
            <td class="mdc-data-table__cell"><?= h($main['NasjonNavn']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'RegHavn','') !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Reg.havn</th>
            <td class="mdc-data-table__cell"><?= h($main['RegHavn']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'Rederi','') !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Rederi</th>
            <td class="mdc-data-table__cell"><?= h($main['Rederi']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'Kallesignal','') !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Kallesignal</th>
            <td class="mdc-data-table__cell"><?= h($main['Kallesignal']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'PennantTiln','') !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Tilnavn/Pennant nr</th>
            <td class="mdc-data-table__cell"><?= h($main['PennantTiln']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'MMSI','') !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">MMSI</th>
            <td class="mdc-data-table__cell"><?= h($main['MMSI']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'Fiskerinr','') !== ''): ?>
          <tr class="mdc-data-table__row">
            <th class="text-end">Fiskerinr</th>
            <td class="mdc-data-table__cell"><?= h($main['Fiskerinr']) ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div></div>
    </div>
    <!-- Navnehistorikk-boks -->

    <h3 style="margin:.25rem 0 0.5rem;">Navnehistorikk (for objektet)</h3>
    <div class="card centered-card" style="padding:1rem; width:fit-content; margin:0 auto;">
      <div class="mdc-data-table table-wrap center">
        <div class="mdc-data-table__table-container">
        <table class="mdc-data-table__table table fit tight">
          <thead>
            <tr class="mdc-data-table__header-row">
              <th class="mdc-data-table__header-cell" style="padding:.15rem .5rem; line-height:1.2;">År/Mnd</th>
              <th class="mdc-data-table__header-cell" style="padding:.15rem .5rem; line-height:1.2; width:150px;">Navn</th>
              <th class="mdc-data-table__header-cell" style="padding:.15rem .5rem; line-height:1.2; width:100px;">Nasjon</th>
              <th class="mdc-data-table__header-cell" style="padding:.15rem .5rem; line-height:1.2; width:100px;">Reg.havn</th>
              <th class="mdc-data-table__header-cell" style="padding:.15rem .5rem; line-height:1.2; width:300px;">Rederi</th>
            </tr>
          </thead>
          <tbody class="mdc-data-table__content">
          <?php foreach ($navnehist as $n): ?>
            <?php $nhUrl = h(BASE_URL) . '/user/fart_detalj.php?obj_id=' . (int)$obj_id . '&tid_id=' . (int)$n['FartTid_ID']; ?>
            <tr class="mdc-data-table__row" data-href="<?= $nhUrl ?>" style="cursor:pointer;">
              <td class="mdc-data-table__cell" style="padding:.35rem .5rem;"><?= h($n['Tidspunkt']) ?></td>
              <td class="mdc-data-table__cell" style="padding:.35rem .5rem;"><?= h($n['NavnKomp']) ?></td>
              <td class="mdc-data-table__cell" style="padding:.35rem .5rem;"><?= h($n['Nasjon'] ?? '') ?></td>
              <td class="mdc-data-table__cell" style="padding:.35rem .5rem;"><?= h($n['RegHavn'] ?? '') ?></td>
              <td class="mdc-data-table__cell" style="padding:.35rem .5rem;"><?= h($n['Rederi'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div style="display:flex; gap:1.5rem; justify-content:center; align-items:flex-start; flex-wrap:wrap; margin-top:1.25rem;">
      <div>
        <h3 style="margin:0 0 0.5rem;">Digitalt Museum</h3>
        <div class="card centered-card" style="padding:1rem; width:fit-content; margin:0 auto;">
          <div class="mdc-data-table table-wrap center">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit tight">
            <thead>
              <tr class="mdc-data-table__header-row">
                <th class="mdc-data-table__header-cell" style="text-align:left; padding:.15rem .5rem; line-height:1.2;">Kode</th>
                <th class="mdc-data-table__header-cell" style="text-align:left; padding:.15rem .5rem; line-height:1.2;">Motiv</th>
              </tr>
            </thead>
            <tbody class="mdc-data-table__content dm-links">
              <?php foreach ($dimuList as $dm): ?>
              <tr class="mdc-data-table__row" data-open="<?= h($dm['url']) ?>" style="cursor:pointer;" title="Dobbeltklikk for å åpne">
                <td class="mdc-data-table__cell" style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($dm['kode']) ?></td>
                <td class="mdc-data-table__cell" style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($dm['motiv']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>
      <div>
        <h3 style="margin:0 0 0.5rem;">Andre lenker</h3>
        <div class="card centered-card" style="padding:1rem; width:fit-content; margin:0 auto;">
          <div class="mdc-data-table table-wrap center">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit tight">
            <thead>
              <tr class="mdc-data-table__header-row">
                <th class="mdc-data-table__header-cell" style="text-align:left; padding:.15rem .5rem; line-height:1.2;">Type / innhold</th>
              </tr>
            </thead>
            <tbody class="mdc-data-table__content">
              <?php foreach ($fartLinks as $lk): ?>
                <?php
                  $label = ($lk['LinkType'] !== '' ? $lk['LinkType'] : 'Lenke')
                         . ($lk['LinkInnh'] !== '' ? ': ' . $lk['LinkInnh'] : '');
                ?>
                <tr class="mdc-data-table__row" data-open="<?= h($lk['Link']) ?>" style="cursor:pointer;" title="Dobbeltklikk for å åpne">
                  <td class="mdc-data-table__cell" style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($label) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin:1.5rem 0 2rem;">
      <a class="mdc-button mdc-button--raised btn" href="<?= h(BASE_URL) ?>/user/fart_soknavn.php"><span class="mdc-button__ripple"></span><span class="mdc-button__label">← Tilbake</span></a>
      <?php if (!empty($main['FartSpes_ID'])): ?>
        <a class="mdc-button mdc-button--raised btn" href="<?= h(BASE_URL) ?>/user/fart_spes.php?spes_id=<?= (int)$main['FartSpes_ID'] ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Tekniske data</span></a>
      <?php else: ?>
        <span class="btn" style="opacity:.5; pointer-events:none;">Tekniske data (mangler)</span>
      <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
document.querySelectorAll('tbody.dm-links tr[data-open], .table-wrap tbody tr[data-open]').forEach(function(tr){
  tr.addEventListener('dblclick', function(){
    var url = tr.getAttribute('data-open');
    if (url) window.open(url, '_blank', 'noopener');
  });
});
document.querySelectorAll('tr[data-href]').forEach(function(tr){
  tr.addEventListener('dblclick', function(){
    var url = tr.getAttribute('data-href');
    if (url) window.location.href = url;
  });
});
</script>

