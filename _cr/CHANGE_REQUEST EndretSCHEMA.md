1. Mål (Goal)

Hva: Å rette opp SQL uttrykk i filer etter endringer i SCHEMA

Hvorfor: SCHEMA er endret til "SkipsWeb_SCHEMA v7.sql". 
I tabell tblFartObj er lagt til nytt felt `ByggeNr` varchar(50) DEFAULT NULL og `BnrSkrog` er endret fra varchar(255) til varchar(50). 
I Tabell tblFartSpes er feltene `SkrogID` og `BnrSkrog` strøket.
I tabell tblFartTid er feltene `Historie`og `FartNavn_ID` strøket. Det er lagt til nye felt `FartNavn` varchar(100) DEFAULT NULL, `FartType_ID` int(11) DEFAULT NULL, og `PennantTiln` varchar(50) DEFAULT NULL.
Tabell tblFartNavn er strøket.
Endringer i nøkkelbruk vises i SCHEMA.

2. Scope (Omfang)

Berørte filer (eksakt sti):  /user/fartoydetaljer.php, /user/fartoyspes.php, /user/fartoy_spes_sok.php og /user/fartoy_navn_sok.php. 
Siste versjon av disse er vedlagt. Prosjektets dokumenter er oppdatert med siste SkipsWeb_SCHEMA og SkipsWeb_PS.

Tillatte endringer:
SQL og layout/markup
Lov å legge til å endre de navngitte, berørte filene.


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)

Sitens øvrige produserte og tesstete filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på  https://github.com/Kringla/ProdSWeb NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnes, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)
Les deg opp på SkipsWeb_PS og SkipsWeb_SCHEMA, som også ligger som projektdokumentasjon.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønskete filer som vedlegg eller på GitHub referansen over!

Bruk h() og basename() der det er relevant.

4. Detaljert spesifikasjon

Nåværende oppførsel: Feil i bruk av felter.

Ønsket oppførsel: Riktig bruk av felter i berørte filer.

Gjennomføring: Du skal gjennomføre dette i tre trinn:
Trinn 1: Les deg opp på prosjektets dokumentasjon og vedlagte filer. Lag en plan for gjennomføring av endringene. Presenter planen.
Trinn 2: Når OK fra meg, lag utkast til anlle endringene. Si fra når du er ferdig.
Trinn 3: Gjennomfør en kritisk vurdering av løsningene, og foreslå endringer for meg.
Trinn 4: Lag de endelige filene

5. Anker i koden (for trygg innfasing)


Innsettingssted / Erstatning: Nye filer erstatter gamle.


Leveranseformat: Fullt ferdig php-fil.


6. Akseptkriterier (Acceptance Criteria)


 Responsiv oppførsel (mobil/desktop) er intakt

 Ingen PHP warnings/notices

 Ingen XSS/sti risiko (bruk h() og basename() der det gjelder)

 Eksplisitte “før/etter”-skjermbilder vedlagt (valgfritt men ønskelig)

7. Testplan (hvordan testes dette)

Lokal (XAMPP):

 Laste siden(e) direkte og verifisere layout

 CTRL+F5 hard refresh (cache)

Browsere:

 Chrome

 
Skjermbredder:


 max 1100px

8. Sikkerhet og kvalitet

HTML/PHP:

 Bruk h() for output escaping

 Bruk basename() på filnavn hvis dynamiske bildekilder

CSS:

 Ikke bruk !important med mindre nødvendig

 Scope endringer (f.eks. .card.centered-card)

JS:

 Ikke endre hero-rotator.js

9. Risiko og rollback


Rollback/plan: Hele filen reverteres

Avhengigheter: Ingen