<?php
declare(strict_types=1);
/*
 * admin/fart_nyfart.php
 * Opprett nytt fartøy via én form i henhold til CR CodexFartøyNytt.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) {
    header('Location: ' . url('/login.php'));
    exit;
}

$mysqli = db_rw();
if (!$mysqli) {
    throw new RuntimeException('Databaseforbindelse mangler.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

/**
 * ---------- Hjelpefunksjoner ----------
 */
function sw_post_int(string $key, ?array $src = null): ?int
{
    $source = $src ?? $_POST;
    if (!isset($source[$key])) {
        return null;
    }
    $raw = trim((string) $source[$key]);
    if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
        return null;
    }
    return (int) $raw;
}

function sw_post_str(string $key, ?array $src = null): string
{
    $source = $src ?? $_POST;
    return isset($source[$key]) ? trim((string) $source[$key]) : '';
}

if (!function_exists('stmt_infer_types')) {
    /**
     * Bygger en type-streng for mysqli::bind_param basert på PHP-verdier.
     */
    function stmt_infer_types(array $params): string {
        $types = '';
        foreach ($params as $value) {
            if (is_int($value) || is_bool($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_null($value)) {
                $types .= 's';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
}

if (!function_exists('stmt_bind_params')) {
    /**
     * Binder parametre til en mysqli_stmt og håndterer referanser automatisk.
     *
     * @param mysqli_stmt $stmt
     * @param array       $params Verdier som skal bindes (sendes som referanser)
     * @param string|null $types  Valgfri type-streng (auto-infer hvis null)
     */
    function stmt_bind_params(mysqli_stmt $stmt, array &$params, ?string $types = null): void {
        $types = $types ?? stmt_infer_types($params);
        $refs = [$types];
        foreach ($params as $key => &$value) {
            $refs[] = &$value;
        }
        unset($value);
        $stmt->bind_param(...$refs);
    }
}

function sw_get_options(mysqli $conn, string $table, string $idField, string $nameField): array
{
    $safeTable = preg_replace('/[^a-z0-9_]/i', '', $table);
    $safeId = preg_replace('/[^a-z0-9_]/i', '', $idField);
    $safeName = preg_replace('/[^a-z0-9_]/i', '', $nameField);
    $sql = "SELECT {$safeId} AS id, {$safeName} AS name FROM {$safeTable} ORDER BY name";
    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    return $rows;
}

function sw_get_verft_options(mysqli $conn): array
{
    $sql = <<<SQL
        SELECT v.Verft_ID   AS id,
               v.VerftNavn AS navn,
               v.Sted      AS sted,
               n.Nasjon    AS nasjon
          FROM tblverft v
          LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID
         ORDER BY v.VerftNavn
    SQL;
    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    return $rows;
}

function sw_format_verft_label(array $row): string
{
    $navn = trim((string) ($row['navn'] ?? ''));
    $sted = trim((string) ($row['sted'] ?? ''));
    $nasjon = trim((string) ($row['nasjon'] ?? ''));
    $parts = [$navn];
    $suffix = '';
    if ($sted !== '') {
        $suffix = $sted;
    }
    if ($nasjon !== '') {
        $suffix .= ($suffix !== '' ? ' ' : '') . '(' . $nasjon . ')';
    }
    if ($suffix !== '') {
        $parts[] = $suffix;
    }
    return implode(' – ', array_filter($parts, static fn($part) => $part !== ''));
}

function sw_links_from_post(): array
{
    $links = $_POST['links'] ?? [];
    if (!is_array($links) || $links === []) {
        return [['LinkType_ID' => '1', 'LinkInnh' => '', 'Link' => '']];
    }
    $normalised = [];
    foreach ($links as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalised[] = [
            'LinkType_ID' => (string) ($row['LinkType_ID'] ?? '1'),
            'LinkInnh'    => (string) ($row['LinkInnh'] ?? ''),
            'Link'        => (string) ($row['Link'] ?? ''),
        ];
    }
    return $normalised;
}

function sw_insert_links(mysqli $conn, array $linkTypeMap, int $fartTidId, array $links): int
{
    if ($links === []) {
        return 0;
    }

    $stmtMax = $conn->prepare('SELECT COALESCE(MAX(SerNo), 0) FROM tblxfartlink WHERE FartTid_ID = ?');
    if (!$stmtMax) {
        throw new RuntimeException($conn->error);
    }
    $maxParams = [$fartTidId];
    stmt_bind_params($stmtMax, $maxParams);
    $stmtMax->execute();
    $stmtMax->bind_result($maxSerNo);
    $stmtMax->fetch();
    $stmtMax->close();

    $nextSerNo = (int) $maxSerNo;
    $stmt = $conn->prepare('INSERT INTO tblxfartlink (FartTid_ID, LinkType_ID, LinkType, LinkInnh, Link, SerNo) VALUES (?,?,?,?,?,?)');
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $inserted = 0;
    foreach ($links as $row) {
        if (!is_array($row)) {
            continue;
        }
        $linkTypeId = sw_post_int('LinkType_ID', $row) ?? 1;
        $linkInnh   = sw_post_str('LinkInnh', $row);
        $linkUrl    = sw_post_str('Link', $row);

        if ($linkInnh === '' && $linkUrl === '') {
            continue;
        }

        $nextSerNo++;
        $linkValues = [
            $fartTidId,
            $linkTypeId,
            $linkTypeMap[$linkTypeId] ?? null,
            $linkInnh !== '' ? $linkInnh : null,
            $linkUrl !== '' ? $linkUrl : null,
            $nextSerNo
        ];
        stmt_bind_params($stmt, $linkValues);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException($stmt->error);
        }
        $inserted++;
    }
    $stmt->close();

    return $inserted;
}

function sw_require_csrf(string $token): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($token, (string) $_POST['csrf_token'])) {
        throw new RuntimeException('Ugyldig forespørsel (CSRF).');
    }
}

/**
 * ---------- AJAX: Opprett verft ----------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_verft') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        sw_require_csrf($csrf_token);

        $navn = sw_post_str('verft_navn');
        $sted = sw_post_str('verft_sted');
        $nasjonId = sw_post_int('verft_nasjon_id') ?? 1;

        if ($navn === '') {
            throw new InvalidArgumentException('Verftnavn må fylles ut.');
        }

        $stmt = $mysqli->prepare('INSERT INTO tblverft (VerftNavn, Sted, Nasjon_ID) VALUES (?,?,?)');
        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }
        $stedVal = $sted !== '' ? $sted : null;
        $verftInsertValues = [$navn, $stedVal, $nasjonId];
        stmt_bind_params($stmt, $verftInsertValues);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();

        $verftRes = $mysqli->prepare(
            'SELECT v.VerftNavn AS navn, v.Sted AS sted, n.Nasjon AS nasjon
               FROM tblverft v
               LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID
              WHERE v.Verft_ID = ?'
        );
        if (!$verftRes) {
            throw new RuntimeException($mysqli->error);
        }
        $verftLookup = [$newId];
        stmt_bind_params($verftRes, $verftLookup);
        $verftRes->execute();
        $label = '';
        if ($result = $verftRes->get_result()) {
            if ($row = $result->fetch_assoc()) {
                $label = sw_format_verft_label($row);
            }
            $result->free();
        }
        $verftRes->close();
        if ($label === '') {
            $label = $navn;
        }

        echo json_encode([
            'ok' => true,
            'verft' => [
                'id' => $newId,
                'label' => $label,
            ],
        ], JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_THROW_ON_ERROR);
    }
    exit;
}

/**
 * ---------- Oppslagsdata ----------
 */
$fartTyper   = [];
if ($res = $mysqli->query("SELECT FartType_ID AS id, TypeFork, FartType FROM tblzfarttype ORDER BY TypeFork, FartType")) {
    while ($row = $res->fetch_assoc()) {
        $typeFork = trim((string)($row['TypeFork'] ?? ''));
        $fartType = trim((string)($row['FartType'] ?? ''));
        if ($typeFork === '') {
            $typeFork = $fartType;
        }
        $full = $typeFork;
        if ($fartType !== '' && strcasecmp($fartType, $typeFork) !== 0) {
            $full .= ' ' . $fartType;
        }
        $fartTyper[] = [
            'id' => (int)$row['id'],
            'typefork' => $typeFork,
            'farttype' => $fartType,
            'full' => $full
        ];
    }
    $res->free();
}
$fartFunk    = sw_get_options($mysqli, 'tblzfartfunk', 'FartFunk_ID', 'TypeFunksjon');
$fartSkrog   = sw_get_options($mysqli, 'tblzfartskrog', 'FartSkrog_ID', 'TypeSkrog');
$fartDrift   = sw_get_options($mysqli, 'tblzfartdrift', 'FartDrift_ID', 'DriftMiddel');
$fartKlasse  = sw_get_options($mysqli, 'tblzfartklasse', 'FartKlasse_ID', 'KlasseNavn');
$fartMat     = sw_get_options($mysqli, 'tblzfartmat', 'FartMat_ID', 'MatFork');
$fartMotor   = sw_get_options($mysqli, 'tblzfartmotor', 'FartMotor_ID', 'MotorDetalj');
$tonnEnheter = sw_get_options($mysqli, 'tblztonnenh', 'TonnEnh_ID', 'TonnFork');
$drektEnheter= sw_get_options($mysqli, 'tblzdrektenh', 'DrektEnh_ID', 'DrektFork');
$nasjoner    = sw_get_options($mysqli, 'tblznasjon', 'Nasjon_ID', 'Nasjon');
$stroker     = sw_get_options($mysqli, 'tblzstroket', 'Stroket_ID', 'Strok');
$linkTyper   = sw_get_options($mysqli, 'tblzlinktype', 'LinkType_ID', 'LinkType');
$linkTypeMap = [];
foreach ($linkTyper as $ltRow) {
    $linkTypeMap[(int)($ltRow['id'] ?? 0)] = $ltRow['name'] ?? '';
}
$verftList   = sw_get_verft_options($mysqli);

/**
 * ---------- Wizard-tilstand (3-stegs) ----------
 */
$wizardKey = 'wizard_nytt';

// Nullstill wizard ved forespørsel
if (isset($_GET['reset'])) {
    unset($_SESSION[$wizardKey]);
    header('Location: ' . url('/admin/fart_nyfart.php'));
    exit;
}

// Wizard-session
if (!isset($_SESSION[$wizardKey])) {
    $_SESSION[$wizardKey] = ['step' => 1, 'data' => []];
}
$step = (int)($_SESSION[$wizardKey]['step'] ?? 1);
$wizardData = $_SESSION[$wizardKey]['data'] ?? [];

$persistedFartObjId  = $wizardData['FartObj_ID'] ?? null;
$persistedFartSpesId = $wizardData['FartSpes_ID'] ?? null;
$persistedFartTidId  = $wizardData['FartTid_ID'] ?? null;
$isSaved = ($step > 3) || ($persistedFartTidId !== null);

// Defaults for step 1
$ensureDefault = static function (string $key, string $value): void {
    if (!isset($_POST[$key]) || $_POST[$key] === '') {
        $_POST[$key] = $value;
    }
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $defaultMap = [
        'FartType_ID'   => '1',
        'FartFunk_ID'   => '4',
        'FartSkrog_ID'  => '2',
        'FartDrift_ID'  => '2',
        'FartMat_ID'    => '2',
        'FartMotor_ID'  => '1',
        'FartKlasse_ID' => '1',
        'Verft_ID'      => '1',
        'LeverID'       => '1',
        'SkrogID'       => '1',
        'StroketID'     => '1',
        'TonnEnh_ID'    => '1',
        'DrektEnh_ID'   => '1',
        'Nasjon_ID'     => '1',
    ];
    foreach ($defaultMap as $key => $value) {
        $ensureDefault($key, $value);
    }
    $ensureDefault('MndSpes', '1');
    $ensureDefault('MndTid', '1');

    // Pre-fill fra wizard-session ved steg 2/3
    if ($step >= 2 && !empty($wizardData)) {
        foreach ($wizardData as $k => $v) {
            if ($v !== null && $v !== '') {
                $_POST[$k] = (string)$v;
            }
        }
    }
}

$linksPost = sw_links_from_post();

$msgErr = '';
$msgOk  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = null;
    if (isset($_POST['save_step1'])) {
        $action = 'save_step1';
    } elseif (isset($_POST['save_step2'])) {
        $action = 'save_step2';
    } elseif (isset($_POST['save_step3'])) {
        $action = 'save_step3';
    } elseif (isset($_POST['save_links'])) {
        $action = 'save_links';
    }

    if ($action !== null) {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, (string) $_POST['csrf_token'])) {
            $msgErr = 'Ugyldig forespørsel (CSRF).';
        } else {
            // ====== STEG 1: Lagre tblfartobj ======
            if ($action === 'save_step1' && $step === 1) {
                try {
                    $FartType_ID = sw_post_int('FartType_ID') ?? 1;
                    $NavnObj = sw_post_str('NavnObj');
                    $Bygget = sw_post_str('Bygget');
                    $LeverID = sw_post_int('LeverID') ?? 1;
                    $BnrLever = sw_post_str('BnrLever');

                    if ($NavnObj === '' || $Bygget === '' || !$FartType_ID) {
                        throw new RuntimeException('Fyll ut påkrevde felter: Navn, Byggeår og Fartøytype.');
                    }

                    $Kontrahert = sw_post_str('Kontrahert');
                    $Kjolstrukket = sw_post_str('Kjolstrukket');
                    $Sjosatt = sw_post_str('Sjosatt');
                    $Levert = sw_post_str('Levert');
                    $SkrogID = sw_post_int('SkrogID') ?? $LeverID;
                    $BnrSkrog = sw_post_str('BnrSkrog');
                    $IMO = sw_post_int('IMO');
                    $StroketYear = sw_post_int('StroketYear');
                    $StroketID = sw_post_int('StroketID') ?? 1;
                    $Historikk = sw_post_str('Historikk');
                    $ObjNotater = sw_post_str('ObjNotater');

                    $stmt1 = $mysqli->prepare(
                        'INSERT INTO tblfartobj (
                            FartType_ID, NavnObj, Bygget, Kontrahert, Kjolstrukket, Sjosatt, Levert,
                            LeverID, ByggeNr, SkrogID, BnrSkrog, IMO, StroketYear, StroketID, Historikk, ObjNotater, IngenData
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)'
                    );
                    if (!$stmt1) {
                        throw new RuntimeException($mysqli->error);
                    }
                    $objValues = [
                        $FartType_ID, $NavnObj,
                        $Bygget !== '' ? $Bygget : null,
                        $Kontrahert !== '' ? $Kontrahert : null,
                        $Kjolstrukket !== '' ? $Kjolstrukket : null,
                        $Sjosatt !== '' ? $Sjosatt : null,
                        $Levert !== '' ? $Levert : null,
                        $LeverID,
                        $BnrLever !== '' ? $BnrLever : null,
                        $SkrogID,
                        $BnrSkrog !== '' ? $BnrSkrog : null,
                        $IMO, $StroketYear, $StroketID,
                        $Historikk !== '' ? $Historikk : null,
                        $ObjNotater !== '' ? $ObjNotater : null
                    ];
                    stmt_bind_params($stmt1, $objValues);
                    if (!$stmt1->execute()) {
                        throw new RuntimeException($stmt1->error);
                    }
                    $FartObj_ID = (int)$stmt1->insert_id;
                    $stmt1->close();

                    // Lagre i wizard-session og gå til steg 2
                    $_SESSION[$wizardKey] = [
                        'step' => 2,
                        'data' => [
                            'FartObj_ID'  => $FartObj_ID,
                            'NavnObj'     => $NavnObj,
                            'FartType_ID' => $FartType_ID,
                            'Bygget'      => $Bygget,
                            'LeverID'     => $LeverID,
                            'SkrogID'     => $SkrogID,
                            'BnrLever'    => $BnrLever,
                            'StroketID'   => $StroketID,
                            // Arv til steg 2: YearSpes=Bygget, Verft_ID=LeverID, Byggenr=BnrLever
                            'YearSpes'    => $Bygget,
                            'MndSpes'     => '1',
                            'Verft_ID'    => (string)$LeverID,
                            'SpecByggenr' => $BnrLever,
                            'FartType_ID_spes' => (string)$FartType_ID,
                        ]
                    ];
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                    header('Location: ' . url('/admin/fart_nyfart.php'));
                    exit;
                } catch (Throwable $e) {
                    $msgErr = 'Lagring av objekt feilet: ' . $e->getMessage();
                }

            // ====== STEG 2: Lagre tblfartspes ======
            } elseif ($action === 'save_step2' && $step === 2) {
                try {
                    $FartObj_ID = (int)($wizardData['FartObj_ID'] ?? 0);
                    if ($FartObj_ID <= 0) {
                        throw new RuntimeException('Wizard-feil: mangler FartObj_ID fra steg 1.');
                    }

                    $FartType_ID = sw_post_int('FartType_ID_spes') ?? sw_post_int('FartType_ID') ?? (int)($wizardData['FartType_ID'] ?? 1);
                    $YearSpes = sw_post_str('YearSpes');
                    $MndSpes = sw_post_str('MndSpes');
                    $MndSpes = $MndSpes === '' ? '1' : $MndSpes;
                    $Verft_ID = sw_post_int('Verft_ID') ?? (int)($wizardData['LeverID'] ?? 1);
                    $SpecByggenr = sw_post_str('SpecByggenr');
                    $FartMat_ID = sw_post_int('FartMat_ID') ?? 2;
                    $FartFunk_ID = sw_post_int('FartFunk_ID') ?? 4;
                    $FartSkrog_ID = sw_post_int('FartSkrog_ID') ?? 2;
                    $FartDrift_ID = sw_post_int('FartDrift_ID') ?? 2;
                    $FunkDetalj = sw_post_str('FunkDetalj');
                    $TeknDetalj = sw_post_str('TeknDetalj');
                    $FartKlasse_ID = sw_post_int('FartKlasse_ID') ?? 1;
                    $Kapasitet = sw_post_str('Kapasitet');
                    $FartMotor_ID = sw_post_int('FartMotor_ID') ?? 1;
                    $MotorDetalj = sw_post_str('MotorDetalj');
                    $MotorEff = sw_post_str('MotorEff');
                    $MaxFart = sw_post_int('MaxFart');
                    $Lengde = sw_post_int('Lengde');
                    $Bredde = sw_post_int('Bredde');
                    $Dypg = sw_post_int('Dypg');
                    $Tonnasje = sw_post_str('Tonnasje');
                    $TonnEnh_ID = sw_post_int('TonnEnh_ID') ?? 1;
                    $Drektigh = sw_post_str('Drektigh');
                    $DrektEnh_ID = sw_post_int('DrektEnh_ID') ?? 1;

                    $stmt2 = $mysqli->prepare(
                        'INSERT INTO tblfartspes (
                            FartObj_ID, YearSpes, MndSpes, Verft_ID, Byggenr,
                            FartMat_ID, FartType_ID, FartFunk_ID, FartSkrog_ID, FartDrift_ID,
                            FunkDetalj, TeknDetalj, FartKlasse_ID, Kapasitet, FartMotor_ID,
                            MotorDetalj, MotorEff, MaxFart, Lengde, Bredde, Dypg,
                            Tonnasje, TonnEnh_ID, Drektigh, DrektEnh_ID, Objekt
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
                    );
                    if (!$stmt2) {
                        throw new RuntimeException($mysqli->error);
                    }
                    $specValues = [
                        $FartObj_ID,
                        $YearSpes !== '' ? (int)$YearSpes : null,
                        (int)$MndSpes,
                        $Verft_ID,
                        $SpecByggenr !== '' ? $SpecByggenr : null,
                        $FartMat_ID, $FartType_ID, $FartFunk_ID, $FartSkrog_ID, $FartDrift_ID,
                        $FunkDetalj !== '' ? $FunkDetalj : null,
                        $TeknDetalj !== '' ? $TeknDetalj : null,
                        $FartKlasse_ID,
                        $Kapasitet !== '' ? $Kapasitet : null,
                        $FartMotor_ID,
                        $MotorDetalj !== '' ? $MotorDetalj : null,
                        $MotorEff !== '' ? $MotorEff : null,
                        $MaxFart, $Lengde, $Bredde, $Dypg,
                        $Tonnasje !== '' ? $Tonnasje : null,
                        $TonnEnh_ID,
                        $Drektigh !== '' ? $Drektigh : null,
                        $DrektEnh_ID
                    ];
                    stmt_bind_params($stmt2, $specValues);
                    if (!$stmt2->execute()) {
                        throw new RuntimeException($stmt2->error);
                    }
                    $FartSpes_ID = (int)$stmt2->insert_id;
                    $stmt2->close();

                    // Bidireksjonell synk: oppdater tblfartobj hvis FartType_ID eller Byggenr endret
                    $origType = (int)($wizardData['FartType_ID'] ?? 0);
                    $origBnr  = (string)($wizardData['BnrLever'] ?? '');
                    if ($FartType_ID !== $origType || $SpecByggenr !== $origBnr) {
                        $stmtSync = $mysqli->prepare('UPDATE tblfartobj SET FartType_ID = ?, ByggeNr = ? WHERE FartObj_ID = ?');
                        $syncBnr = $SpecByggenr !== '' ? $SpecByggenr : null;
                        $syncValues = [$FartType_ID, $syncBnr, $FartObj_ID];
                        stmt_bind_params($stmtSync, $syncValues);
                        $stmtSync->execute();
                        $stmtSync->close();
                    }

                    // Lagre i wizard-session og gå til steg 3
                    $prevData = $_SESSION[$wizardKey]['data'];
                    $prevData['FartSpes_ID'] = $FartSpes_ID;
                    $prevData['FartType_ID'] = $FartType_ID;
                    $prevData['YearSpes'] = $YearSpes;
                    $prevData['MndSpes'] = $MndSpes;
                    // Arv til steg 3
                    $prevData['YearTid'] = $YearSpes;
                    $prevData['MndTid'] = $MndSpes;
                    $prevData['FartNavn'] = $prevData['NavnObj'];
                    $prevData['FartType_ID_tid'] = (string)$FartType_ID;
                    $_SESSION[$wizardKey] = ['step' => 3, 'data' => $prevData];
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                    header('Location: ' . url('/admin/fart_nyfart.php'));
                    exit;
                } catch (Throwable $e) {
                    $msgErr = 'Lagring av spesifikasjon feilet: ' . $e->getMessage();
                }

            // ====== STEG 3: Lagre tblfarttid ======
            } elseif ($action === 'save_step3' && $step === 3) {
                try {
                    $FartObj_ID = (int)($wizardData['FartObj_ID'] ?? 0);
                    $FartSpes_ID = (int)($wizardData['FartSpes_ID'] ?? 0);
                    $FartType_ID = (int)($wizardData['FartType_ID'] ?? 1);
                    if ($FartObj_ID <= 0 || $FartSpes_ID <= 0) {
                        throw new RuntimeException('Wizard-feil: mangler IDer fra steg 1/2.');
                    }

                    $FartNavn = sw_post_str('FartNavn');
                    $Rederi = sw_post_str('Rederi');
                    $Nasjon_ID = sw_post_int('Nasjon_ID');
                    if ($FartNavn === '' || $Rederi === '' || !$Nasjon_ID) {
                        throw new RuntimeException('Fyll ut påkrevde felter: Fartøynavn, Rederi og Nasjon.');
                    }

                    $YearTid = sw_post_str('YearTid');
                    $MndTid = sw_post_str('MndTid');
                    $MndTid = $MndTid === '' ? '1' : $MndTid;
                    $PennantTiln = sw_post_str('PennantTiln');
                    $RegHavn = sw_post_str('RegHavn');
                    $MMSI = sw_post_str('MMSI');
                    $Kallesignal = sw_post_str('Kallesignal');
                    $Fiskerinr = sw_post_str('Fiskerinr');

                    $stmt3 = $mysqli->prepare(
                        'INSERT INTO tblfarttid (
                            YearTid, MndTid, FartObj_ID, FartSpes_ID, FartNavn, FartType_ID,
                            PennantTiln, Objekt, Rederi, Nasjon_ID, RegHavn,
                            MMSI, Kallesignal, Fiskerinr, Navning, Eierskifte, Annet
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    if (!$stmt3) {
                        throw new RuntimeException($mysqli->error);
                    }
                    $tidValues = [
                        $YearTid !== '' ? (int)$YearTid : null,
                        (int)$MndTid,
                        $FartObj_ID, $FartSpes_ID, $FartNavn, $FartType_ID,
                        $PennantTiln !== '' ? $PennantTiln : null,
                        1,  // Objekt=1 (nybygg)
                        $Rederi, $Nasjon_ID,
                        $RegHavn !== '' ? $RegHavn : null,
                        $MMSI !== '' ? $MMSI : null,
                        $Kallesignal !== '' ? $Kallesignal : null,
                        $Fiskerinr !== '' ? $Fiskerinr : null,
                        1,  // Navning=1
                        1,  // Eierskifte=1
                        1   // Annet=1
                    ];
                    stmt_bind_params($stmt3, $tidValues);
                    if (!$stmt3->execute()) {
                        throw new RuntimeException($stmt3->error);
                    }
                    $FartTid_ID = (int)$stmt3->insert_id;
                    $stmt3->close();

                    sw_insert_links($mysqli, $linkTypeMap, $FartTid_ID, $linksPost);

                    // Wizard ferdig — lagre IDer og gå til steg 4 (ferdig)
                    $persistedFartObjId = $FartObj_ID;
                    $persistedFartSpesId = $FartSpes_ID;
                    $persistedFartTidId  = $FartTid_ID;
                    $isSaved = true;
                    $_SESSION[$wizardKey] = [
                        'step' => 4,
                        'data' => array_merge($wizardData, [
                            'FartTid_ID' => $FartTid_ID,
                        ])
                    ];
                    $step = 4;
                    $msgOk = 'Fartøyet er lagret.';
                } catch (Throwable $e) {
                    $msgErr = 'Lagring av tidsrad feilet: ' . $e->getMessage();
                }

            // ====== Lenker (etter wizard) ======
            } elseif ($action === 'save_links') {
                if (!$isSaved || !$persistedFartTidId) {
                    $msgErr = 'Fullfør wizard først.';
                } else {
                    try {
                        $inserted = sw_insert_links($mysqli, $linkTypeMap, (int)$persistedFartTidId, $linksPost);
                        if ($inserted > 0) {
                            $msgOk = 'Lenker er lagret.';
                        } else {
                            $msgErr = 'Ingen nye lenker å lagre.';
                        }
                    } catch (Throwable $e) {
                        $msgErr = 'Lagring av lenker feilet: ' . $e->getMessage();
                    }
                }
            }
        }

        if ($msgErr === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            $csrf_token = $_SESSION['csrf_token'];
        }
    }
}
?>
<?php
$page_title = 'Nytt fartoy';
$page_class = 'page-fartoy-nytt';
$bodyClass = trim(($bodyClass ?? '') . ' page-fartoy-nytt');
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>

<div class="container page-form py-4">
    <h1 class="h3 mb-4">Nytt fartøy — Steg <?= $step <= 3 ? $step : 3 ?> av 3</h1>

    <?php if ($msgOk): ?>
      <div class="alert alert-success"><?= h($msgOk) ?></div>
    <?php endif; ?>
    <?php if ($msgErr): ?>
      <div class="alert alert-danger"><?= h($msgErr) ?></div>
    <?php endif; ?>

    <form method="post" id="form_all" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

      <div class="page-action-top text-center mb-4">
        <?php if ($isSaved): ?>
          <a class="mdc-button mdc-button--outlined btn btn-outline-secondary" href="<?= url('/admin/sw_admin.php') ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Tilbake</span></a>
          <a class="mdc-button mdc-button--outlined btn btn-outline-secondary" href="<?= url('/admin/fart_nyfart.php?reset=1') ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Nytt fartøy</span></a>
        <?php else: ?>
          <a class="mdc-button mdc-button--outlined btn btn-outline-secondary" href="<?= url('/admin/sw_admin.php') ?>"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Avbryt</span></a>
        <?php endif; ?>
      </div>

      <!-- Objektinformasjon (tblfartobj) — Steg 1 -->
      <div class="fartoy-grid">
      <div class="mdc-card card">
        <div class="card-body<?= $step >= 2 ? " locked" : "" ?>">
          <h2 class="h5 mb-3">Objektinformasjon</h2>
          <div class="mdc-data-table"><div class="mdc-data-table__table-container"><table class="mdc-data-table__table table table-sm align-middle sw-form-table">
            <colgroup><col style="width:25%"><col style="width:75%"></colgroup>
            <tbody class="mdc-data-table__content">
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartType_ID_obj">Fartøytype</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-9" name="FartType_ID" id="FartType_ID_obj" data-mirror="farttype" data-role="farttype">
                    <?php foreach ($fartTyper as $opt): ?>
                      <?php $selected = ((int) ($_POST['FartType_ID'] ?? 1) === (int) $opt['id']); ?>
                      <option value="<?= (int) $opt['id'] ?>" data-typefork="<?= h($opt['typefork']) ?>" data-full="<?= h($opt['full']) ?>" <?= $selected ? 'selected' : '' ?>><?= h($selected ? $opt['typefork'] : $opt['full']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="NavnObj">Objektets navn</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="NavnObj" id="NavnObj" <?= sw_len('NavnObj') ?> value="<?= h($_POST['NavnObj'] ?? '') ?>" data-mirror="navn"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Bygget">Bygget (år)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Bygget" id="Bygget" <?= sw_len('Bygget') ?> value="<?= h($_POST['Bygget'] ?? '') ?>" data-mirror="year"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Kontrahert">Kontrahert</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Kontrahert" id="Kontrahert" <?= sw_len('Kontrahert') ?> value="<?= h($_POST['Kontrahert'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Kjolstrukket">Kjølstrukket</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Kjolstrukket" id="Kjolstrukket" <?= sw_len('Kjolstrukket') ?> value="<?= h($_POST['Kjolstrukket'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Sjosatt">Sjøsatt</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Sjosatt" id="Sjosatt" <?= sw_len('Sjosatt') ?> value="<?= h($_POST['Sjosatt'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Levert">Levert</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Levert" id="Levert" <?= sw_len('Levert') ?> value="<?= h($_POST['Levert'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="LeverID">Leverende verft</label></th>
                <td class="mdc-data-table__cell">
                  <div class="sw-field-inline">
                    <select class="form-select" name="LeverID" id="LeverID">
                      <?php foreach ($verftList as $verft): ?>
                        <?php $label = sw_format_verft_label($verft); ?>
                        <option value="<?= (int) $verft['id'] ?>" <?= ((int) ($_POST['LeverID'] ?? 1) === (int) $verft['id']) ? 'selected' : '' ?>><?= h($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="helper-link" data-open-verft="LeverID">Legg til nytt verft</button>
                  </div>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="BnrLever">Byggenr (lever)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="BnrLever" id="BnrLever" <?= sw_len('ByggeNr') ?> value="<?= h($_POST['BnrLever'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="SkrogID">Skrogverft</label></th>
                <td class="mdc-data-table__cell">
                  <div class="sw-field-inline">
                    <select class="form-select" name="SkrogID" id="SkrogID">
                      <?php foreach ($verftList as $verft): ?>
                        <?php $label = sw_format_verft_label($verft); ?>
                        <option value="<?= (int) $verft['id'] ?>" <?= ((int) ($_POST['SkrogID'] ?? $_POST['LeverID'] ?? 1) === (int) $verft['id']) ? 'selected' : '' ?>><?= h($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="helper-link" data-open-verft="SkrogID">Legg til nytt verft</button>
                  </div>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="BnrSkrog">Byggenr (skrog)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="BnrSkrog" id="BnrSkrog" <?= sw_len('BnrSkrog') ?> value="<?= h($_POST['BnrSkrog'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="IMO">IMO</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="IMO" id="IMO" value="<?= h($_POST['IMO'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="StroketYear">Strøket år</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="StroketYear" id="StroketYear" value="<?= h($_POST['StroketYear'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="StroketID">Årsak til strøket</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-48" name="StroketID" id="StroketID">
                    <?php foreach ($stroker as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['StroketID'] ?? 1) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Historikk">Historikk</label></th>
                <td class="mdc-data-table__cell"><textarea class="form-control" name="Historikk" id="Historikk" rows="3"><?= h($_POST['Historikk'] ?? '') ?></textarea></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="ObjNotater">Notater (objekt)</label></th>
                <td class="mdc-data-table__cell"><textarea class="form-control" name="ObjNotater" id="ObjNotater" rows="2"><?= h($_POST['ObjNotater'] ?? '') ?></textarea></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row">Objekt</th>
                <td class="mdc-data-table__cell"><span class="badge">True</span></td>
              </tr>
            </tbody>
          </table></div></div>
        </div>
      </div>
      <!-- Teknisk spesifikasjon (tblfartspes) — Steg 2 -->
      <?php if ($step >= 2): ?>
      <div class="mdc-card card">
        <div class="card-body<?= $step >= 3 ? " locked" : "" ?>">
          <h2 class="h5 mb-3">Teknisk spesifikasjon</h2>
          <div class="mdc-data-table"><div class="mdc-data-table__table-container"><table class="mdc-data-table__table table table-sm align-middle sw-form-table">
            <colgroup><col style="width:25%"><col style="width:75%"></colgroup>
            <tbody class="mdc-data-table__content">
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartType_ID_spes">Fartøytype</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-9" name="FartType_ID_spes" id="FartType_ID_spes" data-mirror="farttype" data-role="farttype">
                    <?php $selType = (int) ($_POST['FartType_ID_spes'] ?? $_POST['FartType_ID'] ?? 1); ?>
                    <?php foreach ($fartTyper as $opt): ?>
                      <?php $selected = ($selType === (int) $opt['id']); ?>
                      <option value="<?= (int) $opt['id'] ?>" data-typefork="<?= h($opt['typefork']) ?>" data-full="<?= h($opt['full']) ?>" <?= $selected ? 'selected' : '' ?>><?= h($selected ? $opt['typefork'] : $opt['full']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="YearSpes">År</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="YearSpes" id="YearSpes" <?= sw_len('YearSpes') ?> value="<?= h($_POST['YearSpes'] ?? '') ?>" data-mirror="year"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="MndSpes">Måned</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="MndSpes" id="MndSpes" <?= sw_len('MndSpes') ?> value="<?= h($_POST['MndSpes'] ?? '0') ?>" data-mirror="month"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Verft_ID">Verft</label></th>
                <td class="mdc-data-table__cell">
                  <div class="sw-field-inline">
                    <select class="form-select sw-select-120" name="Verft_ID" id="Verft_ID">
                      <?php foreach ($verftList as $verft): ?>
                        <?php $label = sw_format_verft_label($verft); ?>
                        <option value="<?= (int) $verft['id'] ?>" <?= ((int) ($_POST['Verft_ID'] ?? 1) === (int) $verft['id']) ? 'selected' : '' ?>><?= h($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="helper-link" data-open-verft="Verft_ID">Legg til nytt verft</button>
                  </div>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="SpecByggenr">Byggenr</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="SpecByggenr" id="SpecByggenr" <?= sw_len('Byggenr') ?> value="<?= h($_POST['SpecByggenr'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartMat_ID">Materiale</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-24" name="FartMat_ID" id="FartMat_ID">
                    <?php foreach ($fartMat as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['FartMat_ID'] ?? 2) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartFunk_ID">Hovedfunksjon</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-48" name="FartFunk_ID" id="FartFunk_ID">
                    <?php foreach ($fartFunk as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['FartFunk_ID'] ?? 4) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartSkrog_ID">Skrogtype</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-48" name="FartSkrog_ID" id="FartSkrog_ID">
                    <?php foreach ($fartSkrog as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['FartSkrog_ID'] ?? 2) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartDrift_ID">Fremdriftsmiddel</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-48" name="FartDrift_ID" id="FartDrift_ID">
                    <?php foreach ($fartDrift as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['FartDrift_ID'] ?? 2) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FunkDetalj">Funksjonell detalj</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="FunkDetalj" id="FunkDetalj" <?= sw_len('FunkDetalj') ?> value="<?= h($_POST['FunkDetalj'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="TeknDetalj">Teknisk detalj</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="TeknDetalj" id="TeknDetalj" <?= sw_len('TeknDetalj') ?> value="<?= h($_POST['TeknDetalj'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartKlasse_ID">Klasse/Type</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-48" name="FartKlasse_ID" id="FartKlasse_ID">
                    <?php foreach ($fartKlasse as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['FartKlasse_ID'] ?? 1) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Kapasitet">Kapasitet</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Kapasitet" id="Kapasitet" <?= sw_len('Kapasitet') ?> value="<?= h($_POST['Kapasitet'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartMotor_ID">Motortype</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-48" name="FartMotor_ID" id="FartMotor_ID">
                    <?php foreach ($fartMotor as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['FartMotor_ID'] ?? 1) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="MotorDetalj">Motor detalj</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="MotorDetalj" id="MotorDetalj" <?= sw_len('MotorDetalj') ?> value="<?= h($_POST['MotorDetalj'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="MotorEff">Motor effekt (BHK)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="MotorEff" id="MotorEff" <?= sw_len('MotorEff') ?> value="<?= h($_POST['MotorEff'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="MaxFart">Fart, maks (knop)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="MaxFart" id="MaxFart" <?= sw_len('MaxFart') ?> value="<?= h($_POST['MaxFart'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Lengde">Lengde, maks (fot)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Lengde" id="Lengde" <?= sw_len('Lengde') ?> value="<?= h($_POST['Lengde'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Bredde">Bredde (fot)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Bredde" id="Bredde" <?= sw_len('Bredde') ?> value="<?= h($_POST['Bredde'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Dypg">Dypgående (fot)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Dypg" id="Dypg" <?= sw_len('Dypg') ?> value="<?= h($_POST['Dypg'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Tonnasje">Tonnasje</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Tonnasje" id="Tonnasje" <?= sw_len('Tonnasje') ?> value="<?= h($_POST['Tonnasje'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="TonnEnh_ID">Tonnasje enh.</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-12" name="TonnEnh_ID" id="TonnEnh_ID">
                    <?php foreach ($tonnEnheter as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['TonnEnh_ID'] ?? 1) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Drektigh">Drektighet (full)</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Drektigh" id="Drektigh" <?= sw_len('Drektigh') ?> value="<?= h($_POST['Drektigh'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="DrektEnh_ID">Drektighets enh.</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-12" name="DrektEnh_ID" id="DrektEnh_ID">
                    <?php foreach ($drektEnheter as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['DrektEnh_ID'] ?? 1) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row">Objekt</th>
                <td class="mdc-data-table__cell"><span class="badge">True</span></td>
              </tr>
            </tbody>
          </table></div></div>
        </div>
      </div>
      <?php endif; // step >= 2 ?>
      <!-- Navn og tidsdata (tblfarttid) — Steg 3 -->
      <?php if ($step >= 3): ?>
      <div class="mdc-card card">
        <div class="card-body<?= $step >= 4 ? " locked" : "" ?>">
          <h2 class="h5 mb-3">Navn og tidsdata</h2>
          <div class="mdc-data-table"><div class="mdc-data-table__table-container"><table class="mdc-data-table__table table table-sm align-middle sw-form-table">
            <colgroup><col style="width:25%"><col style="width:75%"></colgroup>
            <tbody class="mdc-data-table__content">
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="YearTid">Fra år</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="YearTid" id="YearTid" <?= sw_len('YearTid') ?> value="<?= h($_POST['YearTid'] ?? '') ?>" data-mirror="year"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartType_ID_tid">Fartøytype</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-9" name="FartType_ID_tid" id="FartType_ID_tid" data-mirror="farttype" data-role="farttype">
                    <?php foreach ($fartTyper as $opt): ?>
                      <?php $selected = ($selType === (int) $opt['id']); ?>
                      <option value="<?= (int) $opt['id'] ?>" data-typefork="<?= h($opt['typefork']) ?>" data-full="<?= h($opt['full']) ?>" <?= $selected ? 'selected' : '' ?>><?= h($selected ? $opt['typefork'] : $opt['full']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="FartNavn">Fartøynavn</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="FartNavn" id="FartNavn" <?= sw_len('FartNavn') ?> value="<?= h($_POST['FartNavn'] ?? '') ?>" data-mirror="navn"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="PennantTiln">Pennant nr/tilnavn</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="PennantTiln" id="PennantTiln" <?= sw_len('PennantTiln') ?> value="<?= h($_POST['PennantTiln'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Kallesignal">Kjenningssignal</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Kallesignal" id="Kallesignal" <?= sw_len('Kallesignal') ?> value="<?= h($_POST['Kallesignal'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="MMSI">MMSI nr</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="MMSI" id="MMSI" <?= sw_len('MMSI') ?> value="<?= h($_POST['MMSI'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Fiskerinr">Fiskerinr</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Fiskerinr" id="Fiskerinr" <?= sw_len('Fiskerinr') ?> value="<?= h($_POST['Fiskerinr'] ?? '') ?>"></td>
              </tr>
            </tbody>
          </table></div></div>
        </div>
      </div>

      <div class="mdc-card card">
        <div class="card-body<?= $step >= 4 ? " locked" : "" ?>">
          <h2 class="h5 mb-3">Navn og tidsdata</h2>
          <div class="mdc-data-table"><div class="mdc-data-table__table-container"><table class="mdc-data-table__table table table-sm align-middle sw-form-table">
            <colgroup><col style="width:25%"><col style="width:75%"></colgroup>
            <tbody class="mdc-data-table__content">
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="MndTid">Fra måned nr</label></th>
                <td><input class="form-control" name="MndTid" id="MndTid" <?= sw_len('MndTid') ?> value="<?= h($_POST['MndTid'] ?? '0') ?>" data-mirror="month"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Rederi">Rederi</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="Rederi" id="Rederi" <?= sw_len('Rederi') ?> value="<?= h($_POST['Rederi'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="RegHavn">Registrerhavn</label></th>
                <td class="mdc-data-table__cell"><input class="form-control" name="RegHavn" id="RegHavn" <?= sw_len('RegHavn') ?> value="<?= h($_POST['RegHavn'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row"><label for="Nasjon_ID">Nasjon</label></th>
                <td class="mdc-data-table__cell">
                  <select class="form-select sw-select-60" name="Nasjon_ID" id="Nasjon_ID">
                    <?php foreach ($nasjoner as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($_POST['Nasjon_ID'] ?? 1) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row">Objekt</th>
                <td class="mdc-data-table__cell"><span class="badge">True</span></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row">Navning</th>
                <td class="mdc-data-table__cell"><span class="badge">True</span><input type="hidden" name="Navning" value="1"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row">Eierskifte</th>
                <td class="mdc-data-table__cell"><span class="badge">True</span><input type="hidden" name="Eierskifte" value="1"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th scope="row">Annet</th>
                <td class="mdc-data-table__cell"><span class="badge">True</span><input type="hidden" name="Annet" value="1"></td>
              </tr>
            </tbody>
          </table></div></div>
        </div>
      </div>
      <?php endif; // step >= 3 ?>
      </div><!-- /.fartoy-grid -->

      <div class="page-save-bar text-center mb-4">
        <?php if ($step === 1): ?>
          <button type="submit" class="mdc-button mdc-button--raised btn btn-primary" id="btn-save" name="save_step1" value="1" disabled><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre objekt &rarr; Steg 2</span></button>
        <?php elseif ($step === 2): ?>
          <button type="submit" class="mdc-button mdc-button--raised btn btn-primary" id="btn-save" name="save_step2" value="1"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre spesifikasjon &rarr; Steg 3</span></button>
        <?php elseif ($step === 3): ?>
          <button type="submit" class="mdc-button mdc-button--raised btn btn-primary" id="btn-save" name="save_step3" value="1"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre tidsrad &rarr; Fullfør</span></button>
        <?php else: ?>
          <span class="btn btn-primary" style="opacity:0.6;">Lagret</span>
        <?php endif; ?>
      </div>

      <!-- Lenker (tblxfartlink) — tilgjengelig etter wizard -->
      <?php if ($step >= 3): ?>
      <div class="mdc-card card">
        <div class="card-body">
          <h2 class="h5 mb-3">Lenker</h2>
          <p class="text-muted mb-3">Registrer relevante lenker for fartøyet. Minst ett felt ma fylles for at raden skal lagres.</p>
          <div class="mdc-data-table"><div class="mdc-data-table__table-container"><table class="mdc-data-table__table table table-sm align-middle sw-form-table" id="link-table">
            <colgroup><col style="width:25%"><col style="width:75%"></colgroup>
            <?php foreach ($linksPost as $idx => $linkRow): ?>
              <tbody class="link-group mdc-data-table__content" data-index="<?= (int) $idx ?>">
                <tr class="mdc-data-table__row">
                  <th colspan="2" class="mdc-data-table__cell">
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="link-heading">Lenke <?= (int) $idx + 1 ?></span>
                      <button type="button" class="mdc-button mdc-button--outlined btn btn-outline-danger btn-sm remove-link" <?= count($linksPost) === 1 ? 'disabled' : '' ?>><span class="mdc-button__ripple"></span><span class="mdc-button__label">Fjern</span></button>
                    </div>
                  </th>
                </tr>
                <tr class="mdc-data-table__row">
                  <th scope="row"><label for="links_<?= (int) $idx ?>_LinkType_ID">LinkType (kode)</label></th>
                  <td class="mdc-data-table__cell">
                    <select class="form-select sw-select-60" name="links[<?= (int) $idx ?>][LinkType_ID]" id="links_<?= (int) $idx ?>_LinkType_ID">
                      <?php foreach ($linkTyper as $opt): ?>
                        <option value="<?= (int) $opt['id'] ?>" <?= ((int) ($linkRow['LinkType_ID'] ?? 1) === (int) $opt['id']) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
                <tr class="mdc-data-table__row">
                  <th scope="row"><label for="links_<?= (int) $idx ?>_LinkInnh">Lenketittel</label></th>
                  <td class="mdc-data-table__cell"><input class="form-control" name="links[<?= (int) $idx ?>][LinkInnh]" id="links_<?= (int) $idx ?>_LinkInnh" value="<?= h($linkRow['LinkInnh'] ?? '') ?>"></td>
                </tr>
                <tr class="mdc-data-table__row">
                  <th scope="row"><label for="links_<?= (int) $idx ?>_Link">URL</label></th>
                  <td class="mdc-data-table__cell"><input class="form-control" name="links[<?= (int) $idx ?>][Link]" id="links_<?= (int) $idx ?>_Link" value="<?= h($linkRow['Link'] ?? '') ?>"></td>
                </tr>
              </tbody>
            <?php endforeach; ?>
          </table></div></div>
          <button type="button" class="mdc-button mdc-button--outlined btn btn-outline-primary mt-3" id="add-link" <?= $isSaved ? '' : 'disabled' ?>><span class="mdc-button__ripple"></span><span class="mdc-button__label">Legg til lenke</span></button>
          <button type="submit" class="mdc-button mdc-button--raised btn btn-primary mt-3" id="btn-save-links" name="save_links" value="1" <?= $isSaved ? '' : 'disabled' ?>><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre lenker</span></button>
        </div>
      </div>
      <?php endif; // step >= 3 (links) ?>
    </form>
  </div>

  <div class="modal-overlay" id="verft-modal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="verft-modal-title">
      <h3 id="verft-modal-title">Registrer nytt verft</h3>
      <div class="text-muted mb-3" id="verft-modal-context"></div>
      <form id="verft-form">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="action" value="create_verft">
        <input type="hidden" name="target" id="verft-target" value="">
        <div class="mb-3">
          <label for="verft_navn" class="form-label">Verftnavn</label>
          <input type="text" class="form-control" name="verft_navn" id="verft_navn" <?= sw_len('VerftNavn') ?> required>
        </div>
        <div class="mb-3">
          <label for="verft_sted" class="form-label">Sted</label>
          <input type="text" class="form-control" name="verft_sted" id="verft_sted" <?= sw_len('Sted') ?>>
        </div>
        <div class="mb-3">
          <label for="verft_nasjon_id" class="form-label">Nasjon</label>
          <select class="form-select" name="verft_nasjon_id" id="verft_nasjon_id">
            <?php foreach ($nasjoner as $opt): ?>
              <option value="<?= (int) $opt['id'] ?>"><?= h($opt['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="mdc-button mdc-button--outlined btn btn-outline-secondary" id="verft-cancel"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Avbryt</span></button>
          <button type="submit" class="mdc-button mdc-button--raised btn btn-primary"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre verft</span></button>
        </div>
      </form>
      <div class="alert alert-danger mt-3 d-none" id="verft-error"></div>
    </div>
  </div>

  <template id="link-template">
    <tbody class="link-group mdc-data-table__content" data-index="__index__">
      <tr class="mdc-data-table__row">
        <th colspan="2" class="mdc-data-table__cell">
          <div class="d-flex justify-content-between align-items-center">
            <span class="link-heading">Lenke __no__</span>
            <button type="button" class="mdc-button mdc-button--outlined btn btn-outline-danger btn-sm remove-link"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Fjern</span></button>
          </div>
        </th>
      </tr>
      <tr class="mdc-data-table__row">
        <th scope="row"><label for="links___index___LinkType_ID">LinkType (kode)</label></th>
        <td class="mdc-data-table__cell">
          <select class="form-select" name="links[__index__][LinkType_ID]" id="links___index___LinkType_ID">
            <?php foreach ($linkTyper as $opt): ?>
              <option value="<?= (int) $opt['id'] ?>"><?= h($opt['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr class="mdc-data-table__row">
        <th scope="row"><label for="links___index___LinkInnh">Lenketittel</label></th>
        <td class="mdc-data-table__cell"><input class="form-control" name="links[__index__][LinkInnh]" id="links___index___LinkInnh"></td>
      </tr>
      <tr class="mdc-data-table__row">
        <th scope="row"><label for="links___index___Link">URL</label></th>
        <td class="mdc-data-table__cell"><input class="form-control" name="links[__index__][Link]" id="links___index___Link"></td>
      </tr>
    </tbody>
  </template>
  <script>
  (function() {
    const form = document.getElementById('form_all');
    const btnSave = document.getElementById('btn-save');
    const mirrorGroups = {
      year: Array.from(document.querySelectorAll('[data-mirror="year"]')),
      month: Array.from(document.querySelectorAll('[data-mirror="month"]')),
      navn: Array.from(document.querySelectorAll('[data-mirror="navn"]')),
      farttype: Array.from(document.querySelectorAll('[data-mirror="farttype"]'))
    };

    function syncGroup(group, source) {
      const value = source.value;
      mirrorGroups[group].forEach(el => {
        if (el !== source) {
          el.value = value;
        }
      });
    }

    Object.entries(mirrorGroups).forEach(([group, elements]) => {
      elements.forEach(el => {
        el.addEventListener(group === 'farttype' ? 'change' : 'input', () => {
          syncGroup(group, el);
          if (group === 'month' || group === 'year' || group === 'navn' || group === 'farttype') {
            validateEnable();
          }
        });
      });
    });

    const lever = document.getElementById('LeverID');
    const skrog = document.getElementById('SkrogID');
    const verftSelect = document.getElementById('Verft_ID');
    if (lever) {
      lever.addEventListener('change', () => {
        if (skrog) skrog.value = lever.value;
        if (verftSelect) verftSelect.value = lever.value;
      });
    }

    if (lever && verftSelect && verftSelect.value !== lever.value) {
      verftSelect.value = lever.value;
    }

    // Steg-basert validering
    const wizardStep = <?= (int)$step ?>;

    function hasValue(el) {
      return el && el.value.trim().length > 0;
    }

    // Steg 1: NavnObj, Bygget, FartType_ID
    // Steg 2: Knappen er alltid aktiv (felter har arvet verdier)
    // Steg 3: FartNavn, Rederi, Nasjon_ID
    const requiredFields = wizardStep === 1
      ? {
          fartType: document.getElementById('FartType_ID_obj'),
          navnObj: document.getElementById('NavnObj'),
          year: document.getElementById('Bygget')
        }
      : wizardStep === 3
        ? {
            fartNavn: document.getElementById('FartNavn'),
            rederi: document.getElementById('Rederi'),
            nasjon: document.getElementById('Nasjon_ID')
          }
        : {};

    function validateEnable() {
      if (!btnSave) return;
      if (wizardStep === 2 || wizardStep >= 4) {
        // Steg 2: alltid aktiv. Steg 4+: ferdig.
        return;
      }
      let kravOppfylt = true;
      for (const [key, el] of Object.entries(requiredFields)) {
        if (key === 'fartType' || key === 'nasjon') {
          if (!el || parseInt(el.value, 10) <= 0) { kravOppfylt = false; break; }
        } else {
          if (!hasValue(el)) { kravOppfylt = false; break; }
        }
      }
      btnSave.disabled = !kravOppfylt;
    }

    Object.values(requiredFields).forEach(el => {
      if (!el) return;
      el.addEventListener('input', validateEnable);
      el.addEventListener('change', validateEnable);
    });

    validateEnable();

    // Lenker
    const linkTable = document.getElementById('link-table');
    const linkTemplateEl = document.getElementById('link-template');
    const addLinkBtn = document.getElementById('add-link');

    function getLinkGroups() {
      return linkTable ? Array.from(linkTable.querySelectorAll('.link-group')) : [];
    }

    if (addLinkBtn && linkTemplateEl && linkTable) {
      addLinkBtn.addEventListener('click', () => {
        const index = getLinkGroups().length;
        const html = linkTemplateEl.innerHTML.replace(/__index__/g, index).replace(/__no__/g, index + 1);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const group = wrapper.firstElementChild;
        if (group) {
          linkTable.appendChild(group);
          refreshLinkRemoveStates();
        }
      });
    }

    function refreshLinkRemoveStates() {
      const groups = getLinkGroups();
      groups.forEach(group => {
        const removeBtn = group.querySelector('.remove-link');
        if (!removeBtn) return;
        removeBtn.disabled = groups.length === 1;
        removeBtn.onclick = () => {
          if (getLinkGroups().length === 1) return;
          group.remove();
          refreshLinkRemoveStates();
        };
      });
      getLinkGroups().forEach((group, idx) => {
        group.dataset.index = idx;
        const heading = group.querySelector('.link-heading');
        if (heading) heading.textContent = `Lenke ${idx + 1}`;
        group.querySelectorAll('[id]').forEach(el => {
          el.id = el.id.replace(/links_\d+_/g, `links_${idx}_`);
        });
        group.querySelectorAll('[name]').forEach(el => {
          el.name = el.name.replace(/links\[\d+\]/g, `links[${idx}]`);
        });
      });
    }

    if (linkTable) {
      refreshLinkRemoveStates();
    }

    // Verft modal
    const modal = document.getElementById('verft-modal');
    const modalContext = document.getElementById('verft-modal-context');
    const modalTarget = document.getElementById('verft-target');
    const verftForm = document.getElementById('verft-form');
    const verftError = document.getElementById('verft-error');

    if (modal) {
      modal.style.display = 'none';
    }

    document.querySelectorAll('[data-open-verft]').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-open-verft');
        modalTarget.value = target;
        modalContext.textContent = target === 'LeverID'
          ? 'Oppretter verft for leverende verft. Skrogverft settes automatisk lik leverende verft.'
          : target === 'SkrogID'
            ? 'Oppretter verft for skrogbygging.'
            : 'Oppretter verft for teknisk spesifikasjon.';
        verftForm.reset();
        verftError.classList.add('d-none');
        modal.style.display = 'flex';
        document.getElementById('verft_navn').focus();
      });
    });

    document.getElementById('verft-cancel').addEventListener('click', closeVerftModal);
    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeVerftModal();
    });

    function closeVerftModal() {
      modal.style.display = 'none';
    }

    verftForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      verftError.classList.add('d-none');
      const formData = new FormData(verftForm);
      try {
        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData,
        });
        const data = await response.json();
        if (!data.ok) {
          throw new Error(data.error || 'Kunne ikke lagre verft.');
        }
        insertVerftOption(data.verft.id, data.verft.label);
        closeVerftModal();
      } catch (err) {
        verftError.textContent = err.message;
        verftError.classList.remove('d-none');
      }
    });

    function insertVerftOption(id, label) {
      const selects = [document.getElementById('LeverID'), document.getElementById('SkrogID'), document.getElementById('Verft_ID')];
      selects.forEach(sel => {
        if (!sel) return;
        let option = Array.from(sel.options).find(opt => parseInt(opt.value, 10) === id);
        if (!option) {
          option = document.createElement('option');
          option.value = id;
          sel.appendChild(option);
        }
        option.textContent = label;
      });
      const target = modalTarget.value;
      const targetSelect = document.getElementById(target);
      if (targetSelect) {
        targetSelect.value = String(id);
      }
      if (target === 'LeverID') {
        const skrogSelect = document.getElementById('SkrogID');
        if (skrogSelect) {
          skrogSelect.value = String(id);
        }
      }
      validateEnable();
    }
  })();
  </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

