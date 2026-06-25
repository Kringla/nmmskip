|<?php
    /*
     * admin/fart_edit.php
     *
     * Dette scriptet lar en administrator redigere et eksisterende fartøy.
     * Både navne/tids‑rad (tblfarttid), teknisk spesifikasjon (tblfartspes)
     * og objektdata (tblfartobj) kan oppdateres. Lenker til fartøyet i
     * tblxfartlink håndteres også. Siden baserer seg på utseendet og
     * funksjonaliteten fra fart_nyfart.php, men jobber kun i én fase
     * (redigering av eksisterende data).
     *
     * Parametere:
     *   GET obj_id (int)  – ID til objektet som eies av tidsraden
     *   GET tid_id (int)  – ID til tidsraden som skal redigeres
     *
     * Siden forventer at brukeren er innlogget som administrator og
     * avviser forespørsler fra andre roller. Alle databaseoperasjoner
     * bruker prepared statements og transaksjoner for å sikre at
     * oppdateringer er konsistente. Hvis tidsraden ikke flagges som
     * «Objekt» vil objektfeltene vises som lesbare, men ikke være
     * redigerbare.
     */

    require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/../includes/auth.php';

    // Start session dersom den ikke allerede er startet
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Kun administratorer har lov til å redigere fartøyer
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        header('Location: ' . $base . '/');
        exit;
    }

    // Uses global h() from includes/functions.php

    // Bruk skrivbar tilkobling for denne admin-siden (vi leser og skriver i samme sesjon)
    $conn = db_rw();

    if (!function_exists('val')) {
        /**
         * Returner verdi fra array eller standardverdi dersom nøkkelen ikke finnes
         *
         * @param array  $arr
         * @param string $key
         * @param mixed  $def
         * @return mixed
         */
        function val(array $arr, string $key, $def = '') {
            return array_key_exists($key, $arr) ? $arr[$key] : $def;
        }
    }

    /**
     * Hent referanselister fra parametertabeller for dropdowns.
     * Tabellen må ha primærnøkkel og et feltnavn som skal vises.
     *
     * @param mysqli $conn
     * @param string $table
     * @param string $idField
     * @param string $nameField
     * @return array
     */
    function getOptions(mysqli $conn, string $table, string $idField, string $nameField, array $fallbackFields = []) : array {
        $fields = array_merge([$nameField], $fallbackFields);
        $lastException = null;
        foreach ($fields as $field) {
            $opts = [];
            $sql  = "SELECT {$idField} AS id, {$field} AS name FROM {$table} ORDER BY name";
            try {
                if ($result = $conn->query($sql)) {
                    while ($row = $result->fetch_assoc()) {
                        $opts[] = $row;
                    }
                    $result->free();
                }
                return $opts;
            } catch (mysqli_sql_exception $e) {
                $lastException = $e;
            }
        }
        if ($lastException instanceof mysqli_sql_exception) {
            throw $lastException;
        }
        return [];
    }

    // Hent siste spesifikasjonsrad for et objekt
    function getLastSpes(mysqli $conn, int $objId) : ?array {
        $stmt = $conn->prepare('SELECT * FROM tblfartspes WHERE FartObj_ID = ? ORDER BY YearSpes DESC, MndSpes DESC, FartSpes_ID DESC LIMIT 1');
        if (!$stmt) { return null; }
        $stmt->bind_param('i', $objId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) { $res->free(); }
        $stmt->close();
        return $row ?: null;
    }

    if (!function_exists('stmt_infer_types')) {
        /**
         * Bygger en type-streng for mysqli::bind_param basert p� PHP-verdier.
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
         * Binder parametre til en mysqli_stmt og h�ndterer referanser automatisk.
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

    // Hent GET-parametre og valider
    $objIdParam = isset($_GET['obj_id']) ? (int)$_GET['obj_id'] : 0;
    $tidIdParam = isset($_GET['tid_id']) ? (int)$_GET['tid_id'] : 0;
    if ($objIdParam <= 0 || $tidIdParam <= 0) {
        include __DIR__ . '/../includes/header.php';
        include __DIR__ . '/../includes/menu.php';
        $rows = [];
        $sql = "
            SELECT t.FartTid_ID, t.FartObj_ID, t.FartNavn, t.Nasjon_ID, n.Nasjon,
                   t.Objekt, zft.TypeFork
            FROM tblfarttid t
            LEFT JOIN tblzfarttype zft ON zft.FartType_ID = t.FartType_ID
            LEFT JOIN tblznasjon n ON n.Nasjon_ID = t.Nasjon_ID
            WHERE t.FartTid_ID IN (
                SELECT t2.FartTid_ID FROM tblfarttid t2
                WHERE t2.FartObj_ID = t.FartObj_ID
                ORDER BY COALESCE(t2.YearTid,0) DESC, COALESCE(t2.MndTid,0) DESC, t2.FartTid_ID DESC
                LIMIT 1
            )
            ORDER BY t.FartNavn ASC
            LIMIT 500
        ";
        if ($res = $conn->query($sql)) {
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            $res->free();
        }
        ?>
        <div class="container mt-3">
          <h1 class="text-center">Rediger fartøy</h1>
          <div class="d-flex gap-2 justify-content-center mb-3">
            <button class="mdc-button mdc-button--outlined btn btn-outline-primary" id="btnSpec" disabled title="Velg et fartøy først"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Spesifikasjonsendring</span></button>
            <button class="mdc-button mdc-button--outlined btn btn-outline-primary" id="btnOther" disabled title="Velg et fartøy først"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Andre endringer</span></button>
          </div>
          <div class="mdc-card card p-3">
            <label for="selFartoy" class="form-label">Velg fartøy</label>
            <select class="form-select" id="selFartoy">
              <option value="">-- Velg --</option>
              <?php foreach ($rows as $r): ?>
                <?php $star = ((int)($r['Objekt'] ?? 0) === 1) ? '*' : ''; ?>
                <option value="<?= (int)$r['FartTid_ID'] ?>" data-obj="<?= (int)$r['FartObj_ID'] ?>" data-objekt="<?= (int)($r['Objekt'] ?? 0) ?>">
                  <?= htmlspecialchars(($r['TypeFork'] ?? '') . ' ' . ($r['FartNavn'] ?? '') . " $star", ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <script>
        (function(){
          const sel = document.getElementById('selFartoy');
          const b1 = document.getElementById('btnSpec');
          const b2 = document.getElementById('btnOther');
          let currentMode = '';
          function filterOptions() {
            const isSpec = currentMode === 'spec';
            for (let i=0; i<sel.options.length; i++) {
              const opt = sel.options[i];
              if (!opt.value) { continue; }
              const isObj = opt.getAttribute('data-objekt') === '1';
              opt.hidden = isSpec ? (!isObj) : false;
            }
            sel.value = '';
          }
          function enableSelect(mode) {
            currentMode = mode;
            if (sel) {
              sel.disabled = false;
              filterOptions();
              sel.focus();
            }
          }
          function go(mode){
            const opt = sel.options[sel.selectedIndex];
            if(!opt || !sel.value){
              enableSelect(mode);
              return;
            }
            const tid = sel.value; const obj = opt.getAttribute('data-obj');
            const url = new URL(location.href);
            url.search = '?obj_id=' + encodeURIComponent(obj) + '&tid_id=' + encodeURIComponent(tid) + '&mode=' + encodeURIComponent(mode);
            location.href = url.toString();
          }
          if (b1) b1.onclick = function(){ go('spec'); };
          if (b2) b2.onclick = function(){ go('other'); };
          // initial: buttons enabled, select disabled (set in markup)
        })();
        </script>
        <?php include __DIR__ . '/../includes/footer.php'; exit; }

    // Hent referanselister som brukes i skjemaene
    $nasjoner     = getOptions($conn, 'tblznasjon',    'Nasjon_ID',   'Nasjon');
    $fartTyper    = getOptions($conn, 'tblzfarttype',  'FartType_ID', 'FartType');
    $tonnEnheter  = getOptions($conn, 'tblztonnenh',   'TonnEnh_ID',  'TonnFork');
    // Hent drektighetsenheter (forkortelse) og øvrige referanser
    $drektEnheter = getOptions($conn, 'tblzdrektenh', 'DrektEnh_ID', 'DrektFork');
    $fartMat      = getOptions($conn, 'tblzfartmat',     'FartMat_ID',  "CONCAT_WS(' - ', MatFork, Materiale)", ['MatFork']);
    $fartFunk     = getOptions($conn, 'tblzfartfunk',    'FartFunk_ID', 'TypeFunksjon');
    $fartSkrog    = getOptions($conn, 'tblzfartskrog',   'FartSkrog_ID','TypeSkrog');
    $fartDrift    = getOptions($conn, 'tblzfartdrift',   'FartDrift_ID','DriftMiddel');
    // Klassifisering
    $fartKlasse   = getOptions($conn, 'tblzfartklasse',  'FartKlasse_ID', 'KlasseNavn');
    $fartRigg     = getOptions($conn, 'tblzfartrigg', 'FartRigg_ID', 'RiggDetalj');
    $fartMotor    = getOptions($conn, 'tblzfartmotor','FartMotor_ID','MotorDetalj');
    $linkTyper    = getOptions($conn, 'tblzlinktype', 'LinkType_ID', 'LinkType');
    $stroker      = getOptions($conn, 'tblzstroket',  'Stroket_ID', 'Strok');

    // Hent verftliste (navn + sted i ett felt)
    $verft = [];
    $sqlVerft = "SELECT Verft_ID AS id, CONCAT_WS(', ', VerftNavn, Sted) AS name FROM tblverft ORDER BY name";
    if ($res = $conn->query($sqlVerft)) {
        while ($row = $res->fetch_assoc()) {
            $verft[] = $row;
        }
        $res->free();
    }

    // Hent tidsrad (tblfarttid) med tilknyttede rader
    $tidRow  = null;
    $stmtTid = $conn->prepare('SELECT * FROM tblfarttid WHERE FartTid_ID = ?');
    $stmtTid->bind_param('i', $tidIdParam);
    $stmtTid->execute();
    $resTid  = $stmtTid->get_result();
    if ($resTid) {
        $tidRow = $resTid->fetch_assoc();
        $resTid->free();
    }
    $stmtTid->close();
    if (!$tidRow) {
        http_response_code(404);
        echo 'Fant ingen tidsrad.';
        exit;
    }

    // Overstyr objekt‑ID dersom GET ikke samsvarer med databasen
    $objId = (int)$tidRow['FartObj_ID'];
    if ($objId <= 0) {
        http_response_code(404);
        echo 'Denne tidsraden er ikke knyttet til et fartøysobjekt.';
        exit;
    }

    // Hent objektdata (tblfartobj)
    $objRow = null;
    $stmtObj = $conn->prepare('SELECT * FROM tblfartobj WHERE FartObj_ID = ?');
    $stmtObj->bind_param('i', $objId);
    $stmtObj->execute();
    $resObj = $stmtObj->get_result();
    if ($resObj) {
        $objRow = $resObj->fetch_assoc();
        $resObj->free();
    }
    $stmtObj->close();

    // Hent spesifikasjon (tblfartspes) hvis tidsraden refererer til en spesifikasjon
    $spesRow = null;
    $spesId  = isset($tidRow['FartSpes_ID']) ? (int)$tidRow['FartSpes_ID'] : 0;
    if ($spesId > 0) {
        $stmtSp = $conn->prepare('SELECT * FROM tblfartspes WHERE FartSpes_ID = ?');
        $stmtSp->bind_param('i', $spesId);
        $stmtSp->execute();
        $resSp = $stmtSp->get_result();
        if ($resSp) {
            $spesRow = $resSp->fetch_assoc();
            $resSp->free();
        }
        $stmtSp->close();
    }

    // Hent lenker (tblxfartlink)
    $linkRows = [];
    $stmtLk = $conn->prepare(
    "SELECT LinkType_ID,
            COALESCE(LinkType,'') AS LinkType,
            COALESCE(LinkInnh,'') AS LinkInnh,
            Link,
            COALESCE(SerNo, 9999) AS SortNo
     FROM tblxfartlink
     WHERE FartTid_ID = ? AND COALESCE(Link,'') <> ''
     ORDER BY SortNo ASC"
    );
    $stmtLk->bind_param('i', $tidIdParam);
    $stmtLk->execute();
    $resLk = $stmtLk->get_result();
    if ($resLk) {
        while ($row = $resLk->fetch_assoc()) {
            $linkRows[] = $row;
        }
        $resLk->free();
    }
    $stmtLk->close();

    // Flag som indikerer at tidsraden representerer selve objektet
    $isObjectFlag = (int)($tidRow['Objekt'] ?? 0) === 1;

    // Velg arbeidsmodus fra dokumentet: spesifikasjonsendring / andre endringer
    $mode = isset($_REQUEST['mode']) ? trim((string)$_REQUEST['mode']) : '';

    $successMsg = '';

    // Ved POST: enten spesifikasjonsendring (ny spes + ny tidsrad) eller vanlig oppdatering/andre endringer
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Hurtigvei: spesifikasjonsendring (ny spes + ny tidsrad)
        if ($mode === 'spec' && isset($_POST['action']) && $_POST['action'] === 'save_spec_change') {
            $conn->begin_transaction();
            try {
                // Finn siste spes (grunnlag for arv)
                $lastSp = getLastSpes($conn, $objId) ?: [];
                $yearSpes = (val($_POST, 'YearSpes', '') !== '' ? (int)val($_POST, 'YearSpes') : (isset($lastSp['YearSpes']) ? (int)$lastSp['YearSpes'] : null));
                $mndSpes  = (val($_POST, 'MndSpes', '')  !== '' ? (int)val($_POST, 'MndSpes')  : (isset($lastSp['MndSpes']) ? (int)$lastSp['MndSpes'] : null));
                $verftId  = (val($_POST, 'Verft_ID', '') !== '' ? (int)val($_POST, 'Verft_ID') : (isset($lastSp['Verft_ID']) ? (int)$lastSp['Verft_ID'] : null));
                $objektF  = 0; // Ombygging: alltid Objekt=0 per SkipsWebLiv.md

                // Andre felter: arv fra siste spes ved mangel i POST
                $map = [
                    'Byggenr' => '', 'FartMat_ID' => null, 'FartType_ID' => null,
                    'FartFunk_ID' => null, 'FartSkrog_ID' => null, 'FartDrift_ID' => null, 'FunkDetalj' => '',
                    'TeknDetalj' => '', 'FartKlasse_ID' => null, 'Kapasitet' => '',
                    'FartRigg_ID' => null, 'FartMotor_ID' => null, 'MotorDetalj' => '', 'MotorEff' => '',
                    'MaxFart' => null, 'Lengde' => null, 'Bredde' => null, 'Dypg' => null, 'Tonnasje' => '',
                    'TonnEnh_ID' => null, 'Drektigh' => '', 'DrektEnh_ID' => null,
                ];
                $vals = [];
                foreach ($map as $k => $def) {
                    $postKey = $k;
                    $vals[$k] = val($_POST, $postKey, isset($lastSp[$k]) ? $lastSp[$k] : $def);
                }

                // Sett inn ny spesifikasjon
                $sqlSp = "INSERT INTO tblfartspes (
                    FartObj_ID, YearSpes, MndSpes, Verft_ID, Byggenr, FartMat_ID, FartType_ID, FartFunk_ID,
                    FartSkrog_ID, FartDrift_ID, FunkDetalj, TeknDetalj, FartKlasse_ID, Kapasitet,
                    FartRigg_ID, FartMotor_ID, MotorDetalj, MotorEff, MaxFart, Lengde, Bredde, Dypg, Tonnasje,
                    TonnEnh_ID, Drektigh, DrektEnh_ID, Objekt
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmtSp = $conn->prepare($sqlSp);
                if (!$stmtSp) { throw new Exception('Kunne ikke forberede ny spesifikasjon.'); }
                $insertValues = [
                    $objId,
                    $yearSpes, $mndSpes, $verftId,
                    $vals['Byggenr'], $vals['FartMat_ID'], $vals['FartType_ID'], $vals['FartFunk_ID'],
                    $vals['FartSkrog_ID'], $vals['FartDrift_ID'], $vals['FunkDetalj'], $vals['TeknDetalj'], $vals['FartKlasse_ID'],
                    $vals['Kapasitet'], $vals['FartRigg_ID'], $vals['FartMotor_ID'],
                    $vals['MotorDetalj'], $vals['MotorEff'], $vals['MaxFart'], $vals['Lengde'], $vals['Bredde'], $vals['Dypg'],
                    $vals['Tonnasje'], $vals['TonnEnh_ID'], $vals['Drektigh'], $vals['DrektEnh_ID'], $objektF
                ];
                stmt_bind_params($stmtSp, $insertValues);
                $stmtSp->execute();
                $stmtSp->close();
                $newSpesId = (int)$conn->insert_id;

                // Sjekk UNIQUE constraint (FartObj_ID, YearTid, MndTid) før INSERT
                $yearTid = $yearSpes; $mndTid = $mndSpes;
                $stmtChk = $conn->prepare('SELECT FartTid_ID FROM tblfarttid WHERE FartObj_ID = ? AND YearTid = ? AND MndTid = ?');
                $stmtChk->bind_param('iii', $objId, $yearTid, $mndTid);
                $stmtChk->execute();
                $chkRes = $stmtChk->get_result();
                if ($chkRes && $chkRes->num_rows > 0) {
                    $chkRes->free();
                    $stmtChk->close();
                    throw new Exception('Det finnes allerede en tidsrad for dette fartøyet med år=' . ($yearTid ?? '0') . ' og måned=' . ($mndTid ?? '0') . '. Velg en annen kombinasjon.');
                }
                if ($chkRes) $chkRes->free();
                $stmtChk->close();

                // Opprett ny tidsrad som arver fra valgt tidsrad
                $sqlTid = "INSERT INTO tblfarttid (
                    YearTid, MndTid, FartObj_ID, FartSpes_ID, FartNavn, FartType_ID, PennantTiln, Objekt,
                    Rederi, Nasjon_ID, RegHavn, MMSI, Kallesignal, Fiskerinr, Navning, Eierskifte, Annet
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmtTid = $conn->prepare($sqlTid);
                if (!$stmtTid) { throw new Exception('Kunne ikke forberede ny tidsrad.'); }
                $fartNavn = $tidRow['FartNavn'] ?? '';
                $tidValues = [
                    $yearTid, $mndTid, $objId, $newSpesId, $fartNavn,
                    $tidRow['FartType_ID'], $tidRow['PennantTiln'],
                    0,  // Objekt=0 (ombygging, ikke nybygg)
                    $tidRow['Rederi'], $tidRow['Nasjon_ID'], $tidRow['RegHavn'], $tidRow['MMSI'], $tidRow['Kallesignal'],
                    $tidRow['Fiskerinr'],
                    0,  // Navning=0 (ren ombygging)
                    0,  // Eierskifte=0 (ren ombygging)
                    0   // Annet=0 (ren ombygging)
                ];
                stmt_bind_params($stmtTid, $tidValues);
                $stmtTid->execute();
                $stmtTid->close();
                $newTidId = (int)$conn->insert_id;

                $conn->commit();
                $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                header('Location: ' . $base . '/admin/sw_admin.php');
                exit;
            } catch (Exception $ex) {
                $conn->rollback();
                $successMsg = 'Feil ved spesifikasjonsendring: ' . h($ex->getMessage());
            }
        }

        // Samle inn og sanitere input for tidsrad
        $YearTid   = (val($_POST, 'YearTid', '') !== '' ? (int)val($_POST, 'YearTid') : null);
        $MndTid    = (val($_POST, 'MndTid', '')  !== '' ? (int)val($_POST, 'MndTid')  : null);
        $FartNavn  = trim((string)val($_POST, 'FartNavn', ''));
        $FartTypeTid = (int)val($_POST, 'FartTypeTid', (int)$tidRow['FartType_ID']);
        $PennantTiln = trim((string)val($_POST, 'PennantTiln', ''));
        // behold objektflagget som det er
        $Rederi   = trim((string)val($_POST, 'Rederi', ''));
        $Nasjon_ID = (val($_POST, 'Nasjon_ID', '') !== '' ? (int)val($_POST, 'Nasjon_ID') : null);
        $RegHavn  = trim((string)val($_POST, 'RegHavn', ''));
        $MMSI     = trim((string)val($_POST, 'MMSI', ''));
        $Kallesignal = trim((string)val($_POST, 'Kallesignal', ''));
        $Fiskerinr   = trim((string)val($_POST, 'Fiskerinr', ''));
        $Navning  = isset($_POST['Navning'])  ? 1 : null;
        $Eierskifte = isset($_POST['Eierskifte']) ? 1 : null;
        $Annet    = isset($_POST['Annet'])    ? 1 : null;

        // Samle inn input for spesifikasjon (hvis det finnes en spesifikasjonsrad)
        $specData = [];
        if ($spesRow) {
            $specData = [
                'YearSpes'     => (val($_POST, 'YearSpes', '') !== '' ? (int)val($_POST, 'YearSpes') : null),
                'MndSpes'      => (val($_POST, 'MndSpes', '')  !== '' ? (int)val($_POST, 'MndSpes')  : null),
                'Verft_ID'     => (val($_POST, 'Verft_ID', '') !== '' ? (int)val($_POST, 'Verft_ID') : null),
                // Byggenr refers to the build number of the specific specification record. Use a distinct field name in the form to avoid clashing
                // with the object’s build number input. If the posted field is not set, default to empty string.
                'Byggenr'      => trim((string)val($_POST, 'SpecByggenr', val($_POST, 'Byggenr', ''))),
                'FartMat_ID'   => (val($_POST, 'FartMat_ID', '') !== '' ? (int)val($_POST, 'FartMat_ID') : null),
                'FartType_ID'  => (val($_POST, 'FartType_ID', '') !== '' ? (int)val($_POST, 'FartType_ID') : null),
                'FartFunk_ID'  => (val($_POST, 'FartFunk_ID', '') !== '' ? (int)val($_POST, 'FartFunk_ID') : null),
                'FartSkrog_ID' => (val($_POST, 'FartSkrog_ID', '') !== '' ? (int)val($_POST, 'FartSkrog_ID') : null),
                'FartDrift_ID' => (val($_POST, 'FartDrift_ID', '') !== '' ? (int)val($_POST, 'FartDrift_ID') : null),
                'FunkDetalj'   => trim((string)val($_POST, 'FunkDetalj', '')),
                'TeknDetalj'   => trim((string)val($_POST, 'TeknDetalj', '')),
                'Kapasitet'    => trim((string)val($_POST, 'Kapasitet', '')),
                'FartRigg_ID'  => (val($_POST, 'FartRigg_ID', '') !== '' ? (int)val($_POST, 'FartRigg_ID') : null),
                'FartMotor_ID' => (val($_POST, 'FartMotor_ID', '') !== '' ? (int)val($_POST, 'FartMotor_ID') : null),
                'MotorDetalj'  => trim((string)val($_POST, 'MotorDetalj', '')),
                'MotorEff'     => trim((string)val($_POST, 'MotorEff', '')),
                'MaxFart'      => (val($_POST, 'MaxFart', '') !== '' ? (int)val($_POST, 'MaxFart') : null),
                'Lengde'       => (val($_POST, 'Lengde', '')   !== '' ? (int)val($_POST, 'Lengde')   : null),
                'Bredde'       => (val($_POST, 'Bredde', '')   !== '' ? (int)val($_POST, 'Bredde')   : null),
                'Dypg'         => (val($_POST, 'Dypg', '')     !== '' ? (int)val($_POST, 'Dypg')     : null),
                'Tonnasje'     => trim((string)val($_POST, 'Tonnasje', '')),
                'TonnEnh_ID'   => (val($_POST, 'TonnEnh_ID', '') !== '' ? (int)val($_POST, 'TonnEnh_ID') : null),
                'Drektigh'     => trim((string)val($_POST, 'Drektigh', '')),
                'DrektEnh_ID'  => (val($_POST, 'DrektEnh_ID', '') !== '' ? (int)val($_POST, 'DrektEnh_ID') : null)
            ];
        }

        // Samle inn input for objekt (hvis denne tidsraden representerer objektet)
        $objData = [];
        if ($isObjectFlag && $objRow) {
            $objData = [
                'NavnObj'      => trim((string)val($_POST, 'NavnObj', $objRow['NavnObj'] ?? '')),
                'FartType_ID'  => (val($_POST, 'FartTypeObj', '') !== '' ? (int)val($_POST, 'FartTypeObj') : ($objRow['FartType_ID'] ?? null)),
                'IMO'          => (val($_POST, 'IMO', '') !== '' ? (int)val($_POST, 'IMO') : null),
                'Kontrahert'   => trim((string)val($_POST, 'Kontrahert', $objRow['Kontrahert'] ?? '')),
                'Kjolstrukket' => trim((string)val($_POST, 'Kjolstrukket', $objRow['Kjolstrukket'] ?? '')),
                'Sjosatt'      => trim((string)val($_POST, 'Sjosatt', $objRow['Sjosatt'] ?? '')),
                'Levert'       => trim((string)val($_POST, 'Levert', $objRow['Levert'] ?? '')),
                'Bygget'       => (val($_POST, 'Bygget', '') !== '' ? (int)val($_POST, 'Bygget') : null),
                'LeverID'      => (val($_POST, 'LeverID', '') !== '' ? (int)val($_POST, 'LeverID') : null),
                // Use a dedicated field name for the object’s build number to avoid conflict with the specification’s build number
                'ByggeNr'      => trim((string)val($_POST, 'ObjByggeNr', $objRow['ByggeNr'] ?? '')),
                'SkrogID'      => (val($_POST, 'SkrogID', '') !== '' ? (int)val($_POST, 'SkrogID') : null),
                'BnrSkrog'     => trim((string)val($_POST, 'BnrSkrog', $objRow['BnrSkrog'] ?? '')),
                'StroketYear'  => (val($_POST, 'StroketYear', '') !== '' ? (int)val($_POST, 'StroketYear') : null),
                'StroketID'    => (val($_POST, 'StroketID', '') !== '' ? (int)val($_POST, 'StroketID') : null),
                'Historikk'    => trim((string)val($_POST, 'Historikk', $objRow['Historikk'] ?? '')),
                'ObjNotater'   => trim((string)val($_POST, 'ObjNotater', $objRow['ObjNotater'] ?? '')),
                'IngenData'    => isset($_POST['IngenData']) ? 1 : null
            ];
        }

        // Hent lenker fra POST (arrays med samme indeks)
        $postLinkTypes  = isset($_POST['LinkType_ID']) && is_array($_POST['LinkType_ID']) ? $_POST['LinkType_ID'] : [];
        $postLinkInnh   = isset($_POST['LinkInnh'])   && is_array($_POST['LinkInnh'])   ? $_POST['LinkInnh']   : [];
        $postLinkUrls   = isset($_POST['Link'])       && is_array($_POST['Link'])       ? $_POST['Link']       : [];

        // Håndter 'Andre endringer' → ny tidsrad ved vesentlig endring
        $confirmMajor = isset($_POST['confirm_major']) && $_POST['confirm_major'] === '1';
        $triggerChanged = (
            $FartNavn !== (string)($tidRow['FartNavn'] ?? '') ||
            $Rederi   !== (string)($tidRow['Rederi']   ?? '') ||
            $Kallesignal !== (string)($tidRow['Kallesignal'] ?? '') ||
            (string)$Nasjon_ID !== (string)($tidRow['Nasjon_ID'] ?? '')
        );
        if ($mode === 'other' && $confirmMajor && $triggerChanged) {
            $conn->begin_transaction();
            try {
                // Sjekk UNIQUE constraint (FartObj_ID, YearTid, MndTid) før INSERT
                $stmtChk = $conn->prepare('SELECT FartTid_ID FROM tblfarttid WHERE FartObj_ID = ? AND YearTid = ? AND MndTid = ?');
                $chkYear = $YearTid; $chkMnd = $MndTid;
                $stmtChk->bind_param('iii', $objId, $chkYear, $chkMnd);
                $stmtChk->execute();
                $chkRes = $stmtChk->get_result();
                if ($chkRes && $chkRes->num_rows > 0) {
                    $chkRes->free();
                    $stmtChk->close();
                    throw new Exception('Det finnes allerede en tidsrad for dette fartøyet med år=' . ($chkYear ?? '0') . ' og måned=' . ($chkMnd ?? '0') . '. Velg en annen kombinasjon.');
                }
                if ($chkRes) $chkRes->free();
                $stmtChk->close();

                $sqlIns = "INSERT INTO tblfarttid (
                    YearTid, MndTid, FartObj_ID, FartSpes_ID, FartNavn, FartType_ID,
                    PennantTiln, Objekt, Rederi, Nasjon_ID, RegHavn, MMSI, Kallesignal,
                    Fiskerinr, Navning, Eierskifte, Annet
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmtIns = $conn->prepare($sqlIns);
                if (!$stmtIns) { throw new Exception('Kunne ikke forberede innsetting av ny tidsrad.'); }
                $insValues = [
                    $YearTid, $MndTid, $objId, $spesId,
                    $FartNavn,
                    $tidRow['FartType_ID'],
                    $tidRow['PennantTiln'],
                    0,  // Objekt=0 (ikke nybygg)
                    $Rederi, $Nasjon_ID, $RegHavn, $MMSI, $Kallesignal,
                    $Fiskerinr, $Navning, $Eierskifte, $Annet
                ];
                stmt_bind_params($stmtIns, $insValues);
                $stmtIns->execute();
                $stmtIns->close();
                $newTidId = (int)$conn->insert_id;
                $conn->commit();
                $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                header('Location: ' . $base . '/admin/sw_admin.php');
                exit;
            } catch (Exception $ex) {
                $conn->rollback();
                $successMsg = 'Feil ved opprettelse av ny tidsrad: ' . h($ex->getMessage());
            }
        }

        // Konsistenskontroll FartType_ID: tblfartspes er kilden til sannhet.
        // Ved Type A (feilretting) skal FartType_ID være lik i alle tre tabeller.
        if ($spesRow && isset($specData['FartType_ID']) && $specData['FartType_ID'] !== null) {
            $FartTypeTid = (int)$specData['FartType_ID'];
            if ($isObjectFlag && isset($objData['FartType_ID'])) {
                $objData['FartType_ID'] = (int)$specData['FartType_ID'];
            }
        }

        // Start transaksjon for vanlig oppdatering
        $conn->begin_transaction();
        try {
            // Oppdater tidsrad ved hjelp av dynamisk typebinding
            $sqlTid = "UPDATE tblfarttid
                        SET YearTid = ?, MndTid = ?, FartNavn = ?, FartType_ID = ?, PennantTiln = ?,
                            Rederi = ?, Nasjon_ID = ?, RegHavn = ?, MMSI = ?, Kallesignal = ?,
                            Fiskerinr = ?, Navning = ?, Eierskifte = ?, Annet = ?
                        WHERE FartTid_ID = ?";
            $stmt = $conn->prepare($sqlTid);
            if (!$stmt) {
                throw new Exception('Kunne ikke forberede tidsoppdatering.');
            }
            $tidValues = [
                $YearTid,
                $MndTid,
                $FartNavn,
                $FartTypeTid,
                $PennantTiln,
                $Rederi,
                $Nasjon_ID,
                $RegHavn,
                $MMSI,
                $Kallesignal,
                $Fiskerinr,
                $Navning,
                $Eierskifte,
                $Annet,
                $tidIdParam
            ];
            stmt_bind_params($stmt, $tidValues);
            $stmt->execute();
            $stmt->close();

            // Oppdater spesifikasjon dersom rad finnes
            if ($spesRow) {
                $sqlSpes = "UPDATE tblfartspes SET
                        YearSpes = ?, MndSpes = ?, Verft_ID = ?, Byggenr = ?,
                        FartMat_ID = ?, FartType_ID = ?, FartFunk_ID = ?, FartSkrog_ID = ?,
                        FartDrift_ID = ?, FunkDetalj = ?, TeknDetalj = ?, Kapasitet = ?, FartRigg_ID = ?,
                        FartMotor_ID = ?, MotorDetalj = ?, MotorEff = ?, MaxFart = ?,
                        Lengde = ?, Bredde = ?, Dypg = ?, Tonnasje = ?, TonnEnh_ID = ?,
                        Drektigh = ?, DrektEnh_ID = ?
                        WHERE FartSpes_ID = ?";
                $stmtSpes = $conn->prepare($sqlSpes);
                if (!$stmtSpes) {
                    throw new Exception('Kunne ikke forberede spesifikasjonsoppdatering.');
                }
                // Sett sammen verdier i riktig rekkefølge
                $spesValues = [
                    $specData['YearSpes'],
                    $specData['MndSpes'],
                    $specData['Verft_ID'],
                    $specData['Byggenr'],
                    $specData['FartMat_ID'],
                    $specData['FartType_ID'],
                    $specData['FartFunk_ID'],
                    $specData['FartSkrog_ID'],
                    $specData['FartDrift_ID'],
                    $specData['FunkDetalj'],
                    $specData['TeknDetalj'],
                    $specData['Kapasitet'],
                    $specData['FartRigg_ID'],
                    $specData['FartMotor_ID'],
                    $specData['MotorDetalj'],
                    $specData['MotorEff'],
                    $specData['MaxFart'],
                    $specData['Lengde'],
                    $specData['Bredde'],
                    $specData['Dypg'],
                    $specData['Tonnasje'],
                    $specData['TonnEnh_ID'],
                    $specData['Drektigh'],
                    $specData['DrektEnh_ID'],
                    $spesId
                ];
                stmt_bind_params($stmtSpes, $spesValues);
                $stmtSpes->execute();
                $stmtSpes->close();
            }

            // Oppdater objekt hvis relevant
            if ($isObjectFlag && $objRow) {
                $sqlObj = "UPDATE tblfartobj SET
                        NavnObj = ?, FartType_ID = ?, IMO = ?, Kontrahert = ?,
                        Kjolstrukket = ?, Sjosatt = ?, Levert = ?, Bygget = ?,
                        LeverID = ?, ByggeNr = ?, SkrogID = ?, BnrSkrog = ?,
                        StroketYear = ?, StroketID = ?, Historikk = ?, ObjNotater = ?,
                        IngenData = ?
                        WHERE FartObj_ID = ?";
                $stmtObjUpd = $conn->prepare($sqlObj);
                if (!$stmtObjUpd) {
                    throw new Exception('Kunne ikke forberede objektoppdatering.');
                }
                $objValues = [
                    $objData['NavnObj'],
                    $objData['FartType_ID'],
                    $objData['IMO'],
                    $objData['Kontrahert'],
                    $objData['Kjolstrukket'],
                    $objData['Sjosatt'],
                    $objData['Levert'],
                    $objData['Bygget'],
                    $objData['LeverID'],
                    $objData['ByggeNr'],
                    $objData['SkrogID'],
                    $objData['BnrSkrog'],
                    $objData['StroketYear'],
                    $objData['StroketID'],
                    $objData['Historikk'],
                    $objData['ObjNotater'],
                    $objData['IngenData'],
                    $objId
                ];
                stmt_bind_params($stmtObjUpd, $objValues);
                $stmtObjUpd->execute();
                $stmtObjUpd->close();
            }

            // Oppdater lenker: først slett eksisterende, deretter legg til nye
            $stmtDel = $conn->prepare('DELETE FROM tblxfartlink WHERE FartTid_ID = ?');
            $stmtDel->bind_param('i', $tidIdParam);
            $stmtDel->execute();
            $stmtDel->close();

            // Sett inn nye lenker
            if (!empty($postLinkTypes) || !empty($postLinkUrls)) {
                $sqlLk = "INSERT INTO tblxfartlink (FartTid_ID, LinkType_ID, LinkType, LinkInnh, Link, SerNo) VALUES (?, ?, ?, ?, ?, ?)";
                $stmtLkIns = $conn->prepare($sqlLk);
                if (!$stmtLkIns) {
                    throw new Exception('Kunne ikke forberede lenkeinnsetting.');
                }
                $serial = 1;
                $count = max(count($postLinkTypes), count($postLinkUrls), count($postLinkInnh));
                for ($i = 0; $i < $count; $i++) {
                    $ltId  = isset($postLinkTypes[$i]) && $postLinkTypes[$i] !== '' ? (int)$postLinkTypes[$i] : null;
                    $ltRow = null;
                    // Finn linktype tekst for å lagre LinkType
                    if ($ltId !== null) {
                        foreach ($linkTyper as $lt) {
                            if ((int)$lt['id'] === $ltId) { $ltRow = $lt; break; }
                        }
                    }
                    $ltName = $ltRow ? $ltRow['name'] : '';
                    $ltInnh = isset($postLinkInnh[$i]) ? trim((string)$postLinkInnh[$i]) : '';
                    $ltUrl  = isset($postLinkUrls[$i]) ? trim((string)$postLinkUrls[$i]) : '';
                    if ($ltId === null && $ltUrl === '' && $ltInnh === '') {
                        // hopp over tomme rader
                        continue;
                    }
                    $linkValues = [$tidIdParam, $ltId, $ltName, $ltInnh, $ltUrl, $serial];
                    stmt_bind_params($stmtLkIns, $linkValues);
                    $stmtLkIns->execute();
                    $serial++;
                }
                $stmtLkIns->close();
            }

            // Commit transaksjonen
            $conn->commit();
            $successMsg = 'Endringene ble lagret.';
            // Refetch data fra database for å vise oppdaterte verdier
            // NB: Kun oppdater refresh hvis alt gikk bra
            // Hent tidsrad på nytt
            $stmtTid = $conn->prepare('SELECT * FROM tblfarttid WHERE FartTid_ID = ?');
            $stmtTid->bind_param('i', $tidIdParam);
            $stmtTid->execute();
            $resTid = $stmtTid->get_result();
            if ($resTid) { $tidRow = $resTid->fetch_assoc(); $resTid->free(); }
            $stmtTid->close();
            // Spesifikasjon
            if ($spesId) {
                $stmtSp = $conn->prepare('SELECT * FROM tblfartspes WHERE FartSpes_ID = ?');
                $stmtSp->bind_param('i', $spesId);
                $stmtSp->execute();
                $resSp = $stmtSp->get_result();
                if ($resSp) { $spesRow = $resSp->fetch_assoc(); $resSp->free(); }
                $stmtSp->close();
            }
            // Objekt
            if ($objId) {
                $stmtObj = $conn->prepare('SELECT * FROM tblfartobj WHERE FartObj_ID = ?');
                $stmtObj->bind_param('i', $objId);
                $stmtObj->execute();
                $resObj = $stmtObj->get_result();
                if ($resObj) { $objRow = $resObj->fetch_assoc(); $resObj->free(); }
                $stmtObj->close();
            }
            // Lenker
            $linkRows = [];
            $stmtLk = $conn->prepare('SELECT * FROM tblxfartlink WHERE FartTid_ID = ? ORDER BY SerNo ASC, FartLk_ID ASC');
            $stmtLk->bind_param('i', $tidIdParam);
            $stmtLk->execute();
            $resLk = $stmtLk->get_result();
            if ($resLk) {
                while ($row = $resLk->fetch_assoc()) { $linkRows[] = $row; }
                $resLk->free();
            }
            $stmtLk->close();
        } catch (Exception $ex) {
            $conn->rollback();
            $successMsg = 'Feil oppstod under lagring: ' . h($ex->getMessage());
        }
    }

    // Vis skjema
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/menu.php';
    ?>
    <div class="container mt-3">
      <h1 class="text-center">Rediger fartøy</h1>
      <?php if ($successMsg): ?>
        <div class="alert alert-info"><?= h($successMsg) ?></div>
      <?php endif; ?>
      <!-- Valg av handling: spesifikasjonsendring / andre endringer -->
      <div class="d-flex gap-2 justify-content-center mb-3">
        <a class="mdc-button mdc-button--outlined btn btn-outline-primary<?= $mode === 'spec' ? ' active' : '' ?>" href="?obj_id=<?= (int)$objId ?>&tid_id=<?= (int)$tidIdParam ?>&mode=spec"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Spesifikasjonsendring</span></a>
        <a class="mdc-button mdc-button--outlined btn btn-outline-primary<?= $mode === 'other' ? ' active' : '' ?>" href="?obj_id=<?= (int)$objId ?>&tid_id=<?= (int)$tidIdParam ?>&mode=other"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Andre endringer</span></a>
      </div>

      <?php if ($mode === 'spec'): ?>
        <?php $lastSp = getLastSpes($conn, $objId) ?: []; ?>
        <div class="mdc-card card mb-4" style="padding:1rem;">
          <h2 class="h5">Ny spesifikasjon (arver forrige verdier)</h2>
          <form method="post" id="specForm">
            <input type="hidden" name="mode" value="spec">
            <input type="hidden" name="action" value="save_spec_change">
            <div class="row g-3">
              <div class="col-6">
                <label for="YearSpes" class="form-label">År spes.</label>
                <input type="number" class="form-control" name="YearSpes" id="YearSpes" <?= sw_len('YearSpes') ?> value="<?= h($lastSp['YearSpes'] ?? '') ?>">
              </div>
              <div class="col-6">
                <label for="MndSpes" class="form-label">Måned spes.</label>
                <input type="number" class="form-control" name="MndSpes" id="MndSpes" min="0" max="12" <?= sw_len('MndSpes') ?> value="<?= h($lastSp['MndSpes'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label for="Verft_ID" class="form-label">Verft</label>
                <select class="form-select" name="Verft_ID" id="Verft_ID">
                  <option value="">-- Velg --</option>
                  <?php foreach ($verft as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= (int)($lastSp['Verft_ID'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button type="submit" class="mdc-button mdc-button--raised btn btn-primary mt-3" id="btnSaveSpec" disabled title="Endre minst ett felt for å lagre"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre ny spesifikasjon</span></button>
          </form>
        </div>
      <?php endif; ?>
      <!-- Vis objektinformasjon øverst -->
      <div class="mdc-card card mb-4" style="padding:1rem;">
        <h2 class="h4">Objektinformasjon</h2>
        <?php if ($objRow): ?>
        <div class="mdc-data-table"><div class="mdc-data-table__table-container">
        <table class="mdc-data-table__table table compact table-sm table-borderless align-middle">
          <tbody class="mdc-data-table__content">
            <tr class="mdc-data-table__row">
              <th class="text-end" style="width:30%">Navn gitt ved bygging</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="NavnObj" form="editForm" <?= sw_len('NavnObj') ?> value="<?= h($objRow['NavnObj'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['NavnObj'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Fartøystype</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <select class="form-select" name="FartTypeObj" form="editForm">
                    <?php foreach ($fartTyper as $ft): ?>
                      <option value="<?= $ft['id'] ?>" <?= (int)($objRow['FartType_ID'] ?? 1) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                    $typeName = '';
                    foreach ($fartTyper as $ft) {
                        if ((int)$ft['id'] === (int)($objRow['FartType_ID'] ?? 0)) { $typeName = $ft['name']; break; }
                    }
                  ?>
                  <span><?= h($typeName) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">IMO</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="number" class="form-control" name="IMO" form="editForm" <?= sw_len('IMO') ?> value="<?= h($objRow['IMO'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['IMO'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Kontrahert</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="Kontrahert" form="editForm" <?= sw_len('Kontrahert') ?> value="<?= h($objRow['Kontrahert'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Kontrahert'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Kjølstrukket</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="Kjolstrukket" form="editForm" <?= sw_len('Kjolstrukket') ?> value="<?= h($objRow['Kjolstrukket'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Kjolstrukket'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Sjøsatt</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="Sjosatt" form="editForm" <?= sw_len('Sjosatt') ?> value="<?= h($objRow['Sjosatt'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Sjosatt'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Levert</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="Levert" form="editForm" <?= sw_len('Levert') ?> value="<?= h($objRow['Levert'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Levert'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Bygget (år)</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="number" class="form-control" name="Bygget" form="editForm" <?= sw_len('Bygget') ?> value="<?= h($objRow['Bygget'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Bygget'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Leverende verft</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <select class="form-select" name="LeverID" form="editForm">
                    <option value="">-- Velg --</option>
                    <?php foreach ($verft as $v): ?>
                      <option value="<?= $v['id'] ?>" <?= (int)($objRow['LeverID'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                    $leverNavn = '';
                    foreach ($verft as $v) {
                        if ((int)$v['id'] === (int)($objRow['LeverID'] ?? 0)) { $leverNavn = $v['name']; break; }
                    }
                  ?>
                  <span><?= h($leverNavn) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Byggenummer</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <!-- Use a distinct field name for the object's build number -->
                  <input type="text" class="form-control" name="ObjByggeNr" form="editForm" <?= sw_len('ObjByggeNr') ?> value="<?= h($objRow['ByggeNr'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['ByggeNr'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Skrogverft</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <select class="form-select" name="SkrogID" form="editForm">
                    <option value="">Samme som leverende verft</option>
                    <?php foreach ($verft as $v): ?>
                      <option value="<?= $v['id'] ?>" <?= (int)($objRow['SkrogID'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                    $skrogNavn = '';
                    foreach ($verft as $v) {
                        if ((int)$v['id'] === (int)($objRow['SkrogID'] ?? 0)) { $skrogNavn = $v['name']; break; }
                    }
                  ?>
                  <span><?= h($skrogNavn) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Byggenummer, skrog</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="BnrSkrog" form="editForm" <?= sw_len('BnrSkrog') ?> value="<?= h($objRow['BnrSkrog'] ?? '') ?>" placeholder="Standard til objekts byggenummer hvis tomt">
                <?php else: ?>
                  <span><?= h($objRow['BnrSkrog'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Strøket år</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <input type="number" class="form-control" name="StroketYear" form="editForm" <?= sw_len('StroketYear') ?> value="<?= h($objRow['StroketYear'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['StroketYear'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Strøket ID</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <select class="form-select" name="StroketID" form="editForm">
                    <option value="">-- Velg --</option>
                    <?php foreach ($stroker as $s): ?>
                      <option value="<?= $s['id'] ?>" <?= (int)($objRow['StroketID'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                    $strokeName = '';
                    foreach ($stroker as $s) {
                        if ((int)$s['id'] === (int)($objRow['StroketID'] ?? 0)) { $strokeName = $s['name']; break; }
                    }
                  ?>
                  <span><?= h($strokeName) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Historikk</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <textarea class="form-control" name="Historikk" form="editForm" rows="3" style="width:100%"><?= h($objRow['Historikk'] ?? '') ?></textarea>
                <?php else: ?>
                  <span><?= nl2br(h($objRow['Historikk'] ?? '')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Objektnotater</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <textarea class="form-control" name="ObjNotater" form="editForm" rows="3" style="width:100%"><?= h($objRow['ObjNotater'] ?? '') ?></textarea>
                <?php else: ?>
                  <span><?= nl2br(h($objRow['ObjNotater'] ?? '')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Ingen data</th>
              <td class="mdc-data-table__cell">
                <?php if ($isObjectFlag): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="IngenData" id="IngenData" form="editForm" <?= (int)($objRow['IngenData'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="IngenData">Ingen data</label>
                  </div>
                <?php else: ?>
                  <span><?= (int)($objRow['IngenData'] ?? 0) === 1 ? 'Ja' : 'Nei' ?></span>
                <?php endif; ?>
              </td>
            </tr>
          </tbody>
        </table>
        </div></div>
        <?php else: ?>
          <p>Ingen objektdata funnet.</p>
        <?php endif; ?>
      </div>

      <!-- Skjema for redigering av spesifikasjon og tidsrad -->
      <form method="post" id="editForm">
        <input type="hidden" name="mode" value="<?= h($mode) ?>">
        <!-- Teknisk spesifikasjon -->
        <?php if ($spesRow): ?>
          <h2 class="h4 mt-4">Teknisk spesifikasjon</h2>
          <div class="mdc-data-table"><div class="mdc-data-table__table-container">
          <table class="mdc-data-table__table table compact table-sm table-borderless align-middle">
            <tbody class="mdc-data-table__content">
              <tr class="mdc-data-table__row">
                <th class="text-end" style="width:30%">År spes.</th>
                <td class="mdc-data-table__cell" style="width:70%"><input type="number" class="form-control" name="YearSpes" <?= sw_len('YearSpes') ?> value="<?= h($spesRow['YearSpes'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Måned spes.</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="MndSpes" <?= sw_len('MndSpes') ?> value="<?= h($spesRow['MndSpes'] ?? '') ?>" min="0" max="12"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Verft</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="Verft_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($verft as $v): ?>
                      <option value="<?= $v['id'] ?>" <?= (int)($spesRow['Verft_ID'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Velg verft (ingen endring av verftlisten her).</div>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Byggenummer</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="SpecByggenr" <?= sw_len('SpecByggenr') ?> value="<?= h($spesRow['Byggenr'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Materiale</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartMat_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartMat as $m): ?>
                      <option value="<?= $m['id'] ?>" <?= (int)($spesRow['FartMat_ID'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Farttype (kode)</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartType_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartTyper as $ft): ?>
                      <option value="<?= $ft['id'] ?>" <?= (int)($spesRow['FartType_ID'] ?? 0) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Funksjon (kode)</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartFunk_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartFunk as $f): ?>
                      <option value="<?= $f['id'] ?>" <?= (int)($spesRow['FartFunk_ID'] ?? 0) === (int)$f['id'] ? 'selected' : '' ?>><?= h($f['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Skrogtype</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartSkrog_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartSkrog as $sk): ?>
                      <option value="<?= $sk['id'] ?>" <?= (int)($spesRow['FartSkrog_ID'] ?? 0) === (int)$sk['id'] ? 'selected' : '' ?>><?= h($sk['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Driftsmiddel</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartDrift_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartDrift as $d): ?>
                      <option value="<?= $d['id'] ?>" <?= (int)($spesRow['FartDrift_ID'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Funksjonsdetalj</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="FunkDetalj" <?= sw_len('FunkDetalj') ?> value="<?= h($spesRow['FunkDetalj'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Tekniske detaljer</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="TeknDetalj" <?= sw_len('TeknDetalj') ?> value="<?= h($spesRow['TeknDetalj'] ?? '') ?>"></td>
              </tr>
              <!-- Klassifikasjon og klassenavn fjernet (schema v12) -->
              <tr class="mdc-data-table__row">
                <th class="text-end">Kapasitet</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Kapasitet" <?= sw_len('Kapasitet') ?> value="<?= h($spesRow['Kapasitet'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Rigg (kode)</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartRigg_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartRigg as $r): ?>
                      <option value="<?= $r['id'] ?>" <?= (int)($spesRow['FartRigg_ID'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Motor (kode)</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartMotor_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartMotor as $m): ?>
                      <option value="<?= $m['id'] ?>" <?= (int)($spesRow['FartMotor_ID'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Motordetalj</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="MotorDetalj" <?= sw_len('MotorDetalj') ?> value="<?= h($spesRow['MotorDetalj'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Motoreffekt</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="MotorEff" <?= sw_len('MotorEff') ?> value="<?= h($spesRow['MotorEff'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Maxfart</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="MaxFart" <?= sw_len('MaxFart') ?> value="<?= h($spesRow['MaxFart'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Lengde (m)</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="Lengde" <?= sw_len('Lengde') ?> value="<?= h($spesRow['Lengde'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Bredde (m)</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="Bredde" <?= sw_len('Bredde') ?> value="<?= h($spesRow['Bredde'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Dypgående (m)</th>
                <td class="mdc-data-table__cell"><input type="number" class="form-control" name="Dypg" <?= sw_len('Dypg') ?> value="<?= h($spesRow['Dypg'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Tonnasje</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Tonnasje" <?= sw_len('Tonnasje') ?> value="<?= h($spesRow['Tonnasje'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Tonnasjenhet</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="TonnEnh_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($tonnEnheter as $te): ?>
                      <option value="<?= $te['id'] ?>" <?= (int)($spesRow['TonnEnh_ID'] ?? 0) === (int)$te['id'] ? 'selected' : '' ?>><?= h($te['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Klasse (kode)</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartKlasse_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartKlasse as $kl): ?>
                      <option value="<?= $kl['id'] ?>" <?= (int)($spesRow['FartKlasse_ID'] ?? 0) === (int)$kl['id'] ? 'selected' : '' ?>><?= h($kl['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Klasse (kode)</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="FartKlasse_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartKlasse as $kl): ?>
                      <option value="<?= $kl['id'] ?>" <?= (int)($spesRow['FartKlasse_ID'] ?? 0) === (int)$kl['id'] ? 'selected' : '' ?>><?= h($kl['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Drektighet</th>
                <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Drektigh" <?= sw_len('Drektigh') ?> value="<?= h($spesRow['Drektigh'] ?? '') ?>"></td>
              </tr>
              <tr class="mdc-data-table__row">
                <th class="text-end">Drektenhet</th>
                <td class="mdc-data-table__cell">
                  <select class="form-select" name="DrektEnh_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($drektEnheter as $de): ?>
                      <option value="<?= $de['id'] ?>" <?= (int)($spesRow['DrektEnh_ID'] ?? 0) === (int)$de['id'] ? 'selected' : '' ?>><?= h($de['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
          </div></div>
        <?php else: ?>
          <p>Ingen spesifikasjonsrad funnet for dette fartøyet.</p>
        <?php endif; ?>

        <!-- Navn/tidsopplysninger -->
        <h2 class="h4 mt-4">Navn og tidsdata</h2>
        <div class="mdc-data-table"><div class="mdc-data-table__table-container">
        <table class="mdc-data-table__table table compact table-sm table-borderless align-middle">
          <tbody class="mdc-data-table__content">
            <tr class="mdc-data-table__row">
              <th class="text-end" style="width:30%">År</th>
              <td class="mdc-data-table__cell" style="width:70%"><input type="number" class="form-control" name="YearTid" <?= sw_len('YearTid') ?> value="<?= h($tidRow['YearTid'] ?? '') ?>"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Måned</th>
              <td class="mdc-data-table__cell"><input type="number" class="form-control" name="MndTid" <?= sw_len('MndTid') ?> value="<?= h($tidRow['MndTid'] ?? '') ?>" min="0" max="12"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Fartøysnavn</th>
              <td class="mdc-data-table__cell"><input type="text" class="form-control" name="FartNavn" <?= sw_len('FartNavn') ?> value="<?= h($tidRow['FartNavn'] ?? '') ?>"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Fartøystype</th>
              <td class="mdc-data-table__cell">
                <select class="form-select" name="FartTypeTid">
                  <?php foreach ($fartTyper as $ft): ?>
                    <option value="<?= $ft['id'] ?>" <?= (int)($tidRow['FartType_ID'] ?? 1) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Pennant/Tilnavn</th>
              <td class="mdc-data-table__cell"><input type="text" class="form-control" name="PennantTiln" <?= sw_len('PennantTiln') ?> value="<?= h($tidRow['PennantTiln'] ?? '') ?>"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Rederi</th>
              <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Rederi" <?= sw_len('Rederi') ?> value="<?= h($tidRow['Rederi'] ?? '') ?>"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Flaggstat</th>
              <td class="mdc-data-table__cell">
                <select class="form-select" name="Nasjon_ID">
                  <option value="">-- Velg --</option>
                  <?php foreach ($nasjoner as $n): ?>
                    <option value="<?= $n['id'] ?>" <?= (int)($tidRow['Nasjon_ID'] ?? 0) === (int)$n['id'] ? 'selected' : '' ?>><?= h($n['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Registreringshavn</th>
              <td class="mdc-data-table__cell"><input type="text" class="form-control" name="RegHavn" <?= sw_len('RegHavn') ?> value="<?= h($tidRow['RegHavn'] ?? '') ?>"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">MMSI</th>
              <td class="mdc-data-table__cell"><input type="text" class="form-control" name="MMSI" <?= sw_len('MMSI') ?> value="<?= h($tidRow['MMSI'] ?? '') ?>"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Kallesignal</th>
              <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Kallesignal" <?= sw_len('Kallesignal') ?> value="<?= h($tidRow['Kallesignal'] ?? '') ?>"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Fiskerinr</th>
              <td class="mdc-data-table__cell"><input type="text" class="form-control" name="Fiskerinr" <?= sw_len('Fiskerinr') ?> value="<?= h($tidRow['Fiskerinr'] ?? '') ?>"></td>
            </tr>
            <tr class="mdc-data-table__row">
              <th class="text-end">Navneskifte/Eierskifte/Annet</th>
              <td class="mdc-data-table__cell">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="Navning" name="Navning" <?= (int)($tidRow['Navning'] ?? 0) === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="Navning">Navneskifte</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="Eierskifte" name="Eierskifte" <?= (int)($tidRow['Eierskifte'] ?? 0) === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="Eierskifte">Eierskifte</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="Annet" name="Annet" <?= (int)($tidRow['Annet'] ?? 0) === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="Annet">Annet</label>
                </div>
              </td>
            </tr>

          </tbody>
        </table>
        </div></div>

        <!-- Lenker -->
        <h2 class="h4 mt-4">Lenker</h2>
        <div class="card centered-card" style="padding:1rem;">
          <div class="mdc-data-table table-wrap center outline-brand">
            <div class="mdc-data-table__table-container">
            <table class="mdc-data-table__table table fit tight" id="links-table">
              <thead>
                <tr class="mdc-data-table__header-row">
                  <th class="mdc-data-table__header-cell" style="text-align:left; padding:.35rem .5rem;">Type / innhold</th>
                  <th class="mdc-data-table__header-cell" style="text-align:left; padding:.35rem .5rem;">Lenke</th>
                  <th class="mdc-data-table__header-cell" style="width:1%"></th>
                </tr>
              </thead>
              <tbody class="mdc-data-table__content">
              <?php
              // Vis eksisterende lenker eller en tom rad hvis ingen finnes
              $initialCount = max(1, count($linkRows));
              for ($i = 0; $i < $initialCount; $i++):
                  $lk = $linkRows[$i] ?? ['LinkType_ID' => '', 'LinkInnh' => '', 'Link' => ''];
                  $ltId   = $lk['LinkType_ID'];
                  $ltInnh = $lk['LinkInnh'];
                  $ltLink = $lk['Link'];
              ?>
              <tr class="mdc-data-table__row link-row">
                <td class="mdc-data-table__cell">
                  <div class="d-flex gap-2">
                    <select class="form-select" name="LinkType_ID[]" style="max-width: 220px;">
                      <option value="">-- Velg --</option>
                      <?php foreach ($linkTyper as $lt): ?>
                        <option value="<?= $lt['id'] ?>" <?= (int)$ltId === (int)$lt['id'] ? 'selected' : '' ?>><?= h($lt['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="text" class="form-control" name="LinkInnh[]" value="<?= h($ltInnh) ?>" placeholder="Beskrivelse">
                  </div>
                </td>
                <td class="mdc-data-table__cell">
                  <div class="d-flex gap-2">
                    <input type="url" class="form-control" name="Link[]" value="<?= h($ltLink) ?>" placeholder="https://...">
                    <a class="btn-small link-open" target="_blank" rel="noopener" href="<?= h($ltLink) ?>" title="Åpne">Åpne</a>
                  </div>
                </td>
                <td class="mdc-data-table__cell text-end">
                  <button type="button" class="btn-small" title="Slett rad">Fjern</button>
                </td>
              </tr>
              <?php endfor; ?>
              </tbody>
            </table>
            </div>
          </div>
        </div>
        <button type="button" id="add-link" class="mdc-button mdc-button--outlined btn btn-outline-secondary mt-2 mb-3"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Legg til lenke</span></button>
        <?php if ($mode === 'other'): ?>
          <div class="mt-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" id="confirm_major" name="confirm_major">
              <label class="form-check-label" for="confirm_major">
                Vesentlig endring (navn, rederi, kallesignal eller flaggstat) – opprett ny tidsrad
              </label>
            </div>
          </div>
        <?php endif; ?>
        <div class="mt-4">
          <button type="submit" class="mdc-button mdc-button--raised btn btn-primary float-end" id="btnSaveEdit" disabled title="Endre minst ett felt for å lagre"><span class="mdc-button__ripple"></span><span class="mdc-button__label">Lagre</span></button>
        </div>
      </form>
    </div>
    <!-- JavaScript for å legge til/fjerne lenker -->
    <script>
    if(false && document.getElementById('add-link')) document.getElementById('add-link').addEventListener('click', function() {
        const linksSection = document.getElementById('links-section');
        const row = document.createElement('div');
        row.className = 'link-row mb-2';
        row.innerHTML = `
            <div>
                <label class="form-label">Type</label>
                <select class="form-select" name="LinkType_ID[]">
                    <option value="">-- Velg --</option>
                    <?php foreach ($linkTyper as $lt): ?>
                        <option value="<?= $lt['id'] ?>"><?= h($lt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Innhold</label>
                <input type="text" class="form-control" name="LinkInnh[]">
            </div>
            <div>
                <label class="form-label">URL</label>
                <input type="url" class="form-control" name="Link[]">
            </div>
            <div class="d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger remove-link">Fjern</button>
            </div>`;
        linksSection.appendChild(row);
    });
    // Event delegation for remove buttons
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-link')) {
            const row = e.target.closest('.link-row');
            if (row) row.remove();
        }
    });
    </script>
    <!-- Ny tabell-basert lenke-editor -->
    <script>
    (function(){
      const editForm = document.getElementById('editForm');
      const linkAddBtn = document.getElementById('add-link');
      const linkTable  = document.getElementById('links-table');
      const linkBody   = linkTable ? linkTable.querySelector('tbody') : null;

      function bindLinkRow(tr) {
        const urlInput = tr.querySelector('input[name="Link[]"]');
        const openBtn  = tr.querySelector('a.link-open');
        if (!urlInput || !openBtn) return;
        openBtn.addEventListener('click', function(e){
          const u = urlInput.value.trim();
          if (!u) { e.preventDefault(); return; }
          openBtn.setAttribute('href', u);
        });
      }

      if (linkBody) {
        linkBody.querySelectorAll('tr').forEach(bindLinkRow);
      }

      if (linkAddBtn && linkBody) {
        linkAddBtn.addEventListener('click', function(){
          const tr = document.createElement('tr');
          tr.className = 'link-row';
          tr.innerHTML = `
            <td>
              <div class="d-flex gap-2">
                <select class="form-select" name="LinkType_ID[]" style="max-width: 220px;">
                  <option value="">-- Velg --</option>
                  <?php foreach ($linkTyper as $lt): ?>
                    <option value="<?= $lt['id'] ?>"><?= h($lt['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" class="form-control" name="LinkInnh[]" placeholder="Beskrivelse">
              </div>
            </td>
            <td>
              <div class="d-flex gap-2">
                <input type="url" class="form-control" name="Link[]" placeholder="https://...">
                <a class="btn-small link-open" target="_blank" rel="noopener" href="" title="Åpne">Åpne</a>
              </div>
            </td>
            <td class="text-end">
              <button type="button" class="btn-small" title="Slett rad">Fjern</button>
            </td>`;
          linkBody.appendChild(tr);
          bindLinkRow(tr);
          if (editForm) {
            editForm.dispatchEvent(new Event('change', { bubbles: true }));
          }
        });
      }

      document.addEventListener('click', function(e){
        const btn = e.target;
        if (!btn) return;
        const isRemove = btn.matches('button.remove-link') || btn.matches('button[title="Slett rad"]');
        if (!isRemove) return;
        const tr = btn.closest('tr');
        if (!tr) return;
        if (tr.parentElement) {
          tr.remove();
          if (editForm) {
            editForm.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      });
    })();
    </script>
    <script>
    (function(){
      function snapshot(form) {
        const fields = Array.from(form.querySelectorAll('input, select, textarea'));
        return fields.map(function(field, index){
          const key = (field.name || field.id || ('field_' + index)) + '#' + index;
          let value;
          if (field.type === 'checkbox' || field.type === 'radio') {
            value = field.checked ? '1' : '0';
          } else {
            value = field.value ?? '';
          }
          return key + '=' + value;
        }).join('\u0001');
      }

      function initDirtyWatcher(formId, buttonId) {
        const form = document.getElementById(formId);
        const button = document.getElementById(buttonId);
        if (!form || !button) return;
        const initial = snapshot(form);

        function updateState() {
          const current = snapshot(form);
          button.disabled = (current === initial);
        }

        form.addEventListener('input', updateState);
        form.addEventListener('change', updateState);

        const observer = new MutationObserver(function(){
          updateState();
        });
        observer.observe(form, {subtree: true, childList: true});

        updateState();
      }

      document.addEventListener('DOMContentLoaded', function(){
        initDirtyWatcher('specForm', 'btnSaveSpec');
        initDirtyWatcher('editForm', 'btnSaveEdit');

        const addLinkBtn = document.getElementById('add-link');
        function triggerChange() {
          const form = document.getElementById('editForm');
          if (form) form.dispatchEvent(new Event('change', {bubbles: true}));
        }
        if (addLinkBtn) {
          addLinkBtn.addEventListener('click', triggerChange);
        }

        document.addEventListener('click', function(e){
          const target = e.target;
          if (!target) return;
          if (target.matches('button.remove-link') || target.getAttribute('title') === 'Slett rad') {
            const form = document.getElementById('editForm');
            if (form) form.dispatchEvent(new Event('change', {bubbles: true}));
          }
        });
      });
    })();
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
