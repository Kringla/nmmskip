<?php
if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
function is_valid_id($id) {
    return ctype_digit(strval($id)) && intval($id) > 0;
}
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * URL-helper som respekterer BASE_URL.
 * Eksempel: url('user/fart_soknavn.php')
 */
function url(string $path = ''): string {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    return $base . '/' . ltrim($path, '/');
}

if (!function_exists('asset')) {
    /**
     * asset('/assets/img/hero1.jpg')  ->  '/assets/img/hero1.jpg?v=1699999999'
     * Legger på mtime for cache-busting og respekterer BASE_URL.
     */
    function asset(string $path): string {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $rel  = '/' . ltrim($path, '/');
        $fs   = rtrim(__DIR__ . '/..', '/') . $rel; // prosjektrot = includes/..
        $v    = @filemtime($fs);
        return $base . $rel . ($v ? ('?v=' . $v) : '');
    }
}
/* ========================================================================
 * sw_len() – TABELL-UAVHENGIG oppslag basert på feltnavn (SkipsWeb_Fields v1)
 *  Bruk:
 *    <?= sw_len('FartNavn') ?>
 *    <?= sw_len('tblIGNORERT','FartNavn') ?>   // tabellargumentet ignoreres
 *
 *  Returnerer en ferdig streng med HTML-attributter:
 *    - tekst/tallfelt:  maxlength="N" size="S" style="max-width: calc(Nch  1.5rem);"
 *    - textarea (width=max): style="max-width: 100%;"
 *    - checkbox: (tom streng)
 *
 *  Deduplisering: første forekomst vinner (rekkefølge: tblfartobj → tblfartspes → tblfarttid)
 * ======================================================================== */
if (!function_exists('sw_len')) {
    /**
     * @param string      $fieldOrTable Feltnavn ELLER (bakoverkomp.) et tabellnavn
     * @param string|null $maybeField   Feltnavn hvis to-args brukes (tabell ignoreres)
     * @param array       $opts         Valgfritt: ['sizeCap'=>int] – visuell tak på size (default 60)
     * @return string                   Ferdig HTML-attributtstreng (eller tom for checkbox)
     */
    function sw_len(string $fieldOrTable, ?string $maybeField = null, array $opts = []): string
    {
        $sizeCap = isset($opts['sizeCap']) && is_int($opts['sizeCap']) ? max(1, $opts['sizeCap']) : 60;
        // 1-arg: sw_len('Felt')  |  2-arg: sw_len('tabell','Felt')  (tabell ignoreres)
        $field = $maybeField === null ? trim($fieldOrTable) : trim($maybeField);

        $def = _sw_field_def($field);   // ['kind'=>'cols|max|checkbox','width'=>int|null] | null

        // Ukjent felt → trygg fallback (48)
        if ($def === null) {
            $maxlength = 48;
            $size      = min($maxlength, $sizeCap);
            $style     = 'max-width: calc(' . $maxlength . 'ch + 1.5rem);';
            return 'maxlength="'.$maxlength.'" size="'.$size.'" style="'.$style.'"';
        }

        // Checkbox → ingen lengdeattributter
        if ($def['kind'] === 'checkbox') {
            return '';
        }
        // Store tekstblokker → textarea-stilhint
        if ($def['kind'] === 'max') {
            return 'style="max-width: 100%;"';
        }

        // Vanlige tekst-/tallfelt med kjent bredde (cols)
        $maxlength = is_int($def['width']) ? $def['width'] : 48;
        $size      = min($maxlength, $sizeCap);
        $style     = 'max-width: calc(' . $maxlength . 'ch + 1.5rem);';
        return 'maxlength="'.$maxlength.'" size="'.$size.'" style="'.$style.'"';
    }
}

// -------------------------- interne hjelpere -------------------------------
if (!function_exists('_sw_field_def')) {
    /**
     * Flater ut feltnavn fra relevante tabeller til ett oppslag – første forekomst vinner.
     * Rekkefølge: tblfartobj → tblfartspes → tblfarttid.
     * @return array{kind:string,width:?int}|null
     */
    function _sw_field_def(string $field)
    {
        static $flat = null;
        if ($flat === null) {
            $flat = [];
            $tables = _sw_fields_map_tables();
            $order  = ['tblfartobj','tblfartspes','tblfarttid'];
            foreach ($order as $tbl) {
                if (!isset($tables[$tbl])) continue;
                foreach ($tables[$tbl] as $fname => $def) {
                    if (!isset($flat[$fname])) $flat[$fname] = $def;  // første forekomst vinner
                }
            }
        }
        return $flat[$field] ?? null;
    }
}

if (!function_exists('_sw_fields_map_tables')) {
    /**
     * Rå tabellvise definisjoner hentet fra SkipsWeb_Fields v1 (utdrag for admin-skjema).
     * kind: 'cols' | 'max' | 'checkbox'
     */
    function _sw_fields_map_tables(): array
    {
        return [
            // ---------------- tblfartobj ----------------
            'tblfartobj' => [
                'FartObj_ID'  => ['kind'=>'cols','width'=>6],
                'NavnObj'     => ['kind'=>'cols','width'=>48],
                'FartType_ID' => ['kind'=>'cols','width'=>6],
                'IMO'         => ['kind'=>'cols','width'=>12],
                'Kontrahert'  => ['kind'=>'cols','width'=>12],
                'Kjolstrukket'=> ['kind'=>'cols','width'=>12],
                'Sjosatt'     => ['kind'=>'cols','width'=>12],
                'Levert'      => ['kind'=>'cols','width'=>12],
                'Bygget'      => ['kind'=>'cols','width'=>8],
                'LeverID'     => ['kind'=>'cols','width'=>6],
                'ByggeNr'     => ['kind'=>'cols','width'=>12],
                'SkrogID'     => ['kind'=>'cols','width'=>6],
                'BnrSkrog'    => ['kind'=>'cols','width'=>12],
                'StroketYear' => ['kind'=>'cols','width'=>6],
                'StroketID'   => ['kind'=>'cols','width'=>6],
                'Historikk'   => ['kind'=>'max','width'=>null],
                'ObjNotater'  => ['kind'=>'max','width'=>null],
                'IngenData'   => ['kind'=>'checkbox','width'=>null],
            ],

            // ---------------- tblfartspes ----------------
            'tblfartspes' => [
                'FartSpes_ID'  => ['kind'=>'cols','width'=>6],
                'FartObj_ID'   => ['kind'=>'cols','width'=>6],
                'YearSpes'     => ['kind'=>'cols','width'=>8],
                'MndSpes'      => ['kind'=>'cols','width'=>5],
                'Verft_ID'     => ['kind'=>'cols','width'=>6],
                'Byggenr'      => ['kind'=>'cols','width'=>12],
                'FartMat_ID'   => ['kind'=>'cols','width'=>6],
                'FartType_ID'  => ['kind'=>'cols','width'=>6],
                'FartFunk_ID'  => ['kind'=>'cols','width'=>6],
                'FartSkrog_ID' => ['kind'=>'cols','width'=>6],
                'FartDrift_ID' => ['kind'=>'cols','width'=>6],
                'FunkDetalj'   => ['kind'=>'cols','width'=>60],
                'TeknDetalj'   => ['kind'=>'cols','width'=>60],
                'FartKlasse_ID'=> ['kind'=>'cols','width'=>6],
                'Kapasitet'    => ['kind'=>'cols','width'=>120],
                'FartRigg_ID'  => ['kind'=>'cols','width'=>6],
                'FartMotor_ID' => ['kind'=>'cols','width'=>6],
                'MotorDetalj'  => ['kind'=>'cols','width'=>60],
                'MotorEff'     => ['kind'=>'cols','width'=>12],
                'MaxFart'      => ['kind'=>'cols','width'=>6],
                'Lengde'       => ['kind'=>'cols','width'=>6],
                'Bredde'       => ['kind'=>'cols','width'=>6],
                'Dypg'         => ['kind'=>'cols','width'=>6],
                'Tonnasje'     => ['kind'=>'cols','width'=>12],
                'TonnEnh_ID'   => ['kind'=>'cols','width'=>6],
                'Drektigh'     => ['kind'=>'cols','width'=>12],
                'DrektEnh_ID'  => ['kind'=>'cols','width'=>6],
                'Objekt'       => ['kind'=>'checkbox','width'=>null],
            ],

            // ---------------- tblfarttid ----------------
            'tblfarttid' => [
                'FartTid_ID'  => ['kind'=>'cols','width'=>6],
                'YearTid'     => ['kind'=>'cols','width'=>8],
                'MndTid'      => ['kind'=>'cols','width'=>5],
                'FartObj_ID'  => ['kind'=>'cols','width'=>6],
                'FartSpes_ID' => ['kind'=>'cols','width'=>6],
                'FartNavn'    => ['kind'=>'cols','width'=>48],
                'FartType_ID' => ['kind'=>'cols','width'=>6],
                'PennantTiln' => ['kind'=>'cols','width'=>24],
                'Objekt'      => ['kind'=>'checkbox','width'=>null],
                'Rederi'      => ['kind'=>'cols','width'=>255],
                'Nasjon_ID'   => ['kind'=>'cols','width'=>6],
                'RegHavn'     => ['kind'=>'cols','width'=>60],
                'MMSI'        => ['kind'=>'cols','width'=>12],
                'Kallesignal' => ['kind'=>'cols','width'=>18],
                'Fiskerinr'   => ['kind'=>'cols','width'=>18],
                'Navning'     => ['kind'=>'checkbox','width'=>null],
                'Eierskifte'  => ['kind'=>'checkbox','width'=>null],
                'Annet'       => ['kind'=>'checkbox','width'=>null],
            ],

        ];
    }
}
