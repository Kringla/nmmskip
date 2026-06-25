# FOREGÅENDE KOMMUNIKASJON

## ETTER ARBEIDE RAPPORTERTE DU:
`bind_param`-hjelperen med referanser må oppdateres på tvers av flere filer. Mitt forslag er at vi tar et nytt tak hvor vi systematisk kartlegger alle skriptene under `admin/`som gjør DB-skriving (f.eks. `fart_nyfart.php`, `fart_nyspes.php`, `fart_nytid.php`, `verftadmin.php`, `param_admin.php` osv.).
For hvert skript sjekker vi:
- at felt som ikke lenger finnes i databasen (som Materiale) er fjernet/erstattet,
- at bind_param brukes med en helper som håndterer referanser på en trygg måte,
- at skjemaet eksponerer riktige felter.
Etter hver endring kjører vi php -l (og gjerne en testlagring i GUI-et) for å verifisere at alt fungerer.
Hvis du ønsker, kan jeg ta den gjennomgangen på nytt step-by-step – da starter vi gjerne med å gjenopprette `admin/fart_edit.php`/`admin/fart_nyspes.php` til siste fungerende versjon og jobber derfra. Gi meg bare beskjed om hvordan du vil gå videre.

## MITT SVAR:
Jeg synes forslaget ditt er bra. Vi kjører ditt gjennomføringsopplegg. Start


## ETTER ARBEIDE RAPPORTERTE DU:
Fikk på plass utgangspunktet igjen (jeg hentet ferske kopier av `admin/fart_edit.php` og `admin/fart_nyspes.php` fra GitHub, tidligere backup ligger som .bak dersom vi vil se på dem). Neste steg blir å gå systematisk gjennom alle admin-skriptene som oppdaterer databasen. Jeg starter med å analysere hvilke felter og SQL-er som fortsatt refererer til utgåtte kolonner (som Materiale) eller trenger bedre bind_param-bruk. Jeg kommer tilbake med en liste over konkrete funn før vi gjør endringer, så vi kan verifisere at alt stemmer.

## MITT SVAR:
Bra. Fortsett


## ETTER ARBEIDE RAPPORTERTE DU:
Kartla nettopp admin-sidene som skriver til databasen:

`fart_edit.php` – oppdaterer både tblfarttid, tblfartspes, tblfartobj og lenketabellen.
`fart_nyspes.php` – lager ny spesifikasjon/tidsrad (bruker inserts).
`fart_nyfart.php` – oppretter helt nye fartøy (både objekt, spes og tid) samt lenker og verft.
`fart_nytid.php` – genererer ny tidsrad og lenker.
`verft.php` – registrerer/oppdaterer verft og verftslenker.
I tillegg finnes `fart_delete.php` (kun sletting) og `param_*`-sidene (håndterer parameter-tabeller), men de tre førstnevnte og `verft.php` trenger oppdatert SQL/bind_param.
Alle disse filene har fortsatt manuell bind_param-bruk og (i `fart_edit.php`/`fart_nyspes.php`/`fart_nyfart.php`) referanser til det utgåtte Materiale-feltet, eller til tekstfeltet i UI. Neste trinn blir å oppdatere hver av disse: legge inn en felles `stmt_bind_params()`-hjelper, fjerne Materiale/fritekstfeltet, og gå over SQL/verdilister slik at de håndterer `FartMat_ID`/`MatFork` og InnoDB sikkert. Når du bekrefter, tar jeg dem én etter én (starter med `fart_edit.php`, deretter `fart_nyspes.php`, `fart_nytid.php`, `fart_nyfart.php` og til slutt `verft.php`).