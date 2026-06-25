1. Mål (Goal)

Hva: Ønsker å lage ny fartoy_new.php med eventuelle andre .php-filer hvis hensiktsmessig.

Hvorfor: Fjerne feil og forbedre lay-out.


2. Scope (Omfang)

Berørte filer (eksakt sti):/admin/fartoy_edit.php

Tillatte endringer: Tillatt å skape ny(e) fil(er). Tillatt å legge til klasser i /assets/css/app.css


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)

Siste versjon av alle sitens filer, inkludert SISTE VERSJON av de overnevnte filene, finnes på et public-område på GitHub, https://github.com/Kringla/ProdSWeb. NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnes der, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)
Les deg alltid opp på SkipsWeb_PS og SkipsWeb_SCHEMA, som forøvrig også ligger som projektdokumentasjon.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønskete filer som vedlegg eller på GitHub referansen over!

Bruk h() og basename() der det er relevant.

4. Detaljert spesifikasjon

Nåværende oppførsel: Ingen

Ønsket oppførsel:
a. Forståelsen av hva som er et "nytt fartøy" i denne sammenheng er essensiell for riktig utforming av fartoy_new.php. Et nytt fartøy oppstår hvis:
a1: Nytt fartøysobjekt, dvs. et nybygd fartøy ved et verft.
a2: Endret teknisk spesifikasjon for et eksisterende fartøy.
a3: Nytt navn, fartøystype (utført i tblFartSpes), rederi, registreringshavn og/eller flaggstat (nasjonan) på et eksisterende fartøy
b. I tblFartSpes hører feltene Tonnasje og TonnEnh_ID sammen, og Drekigh og DrektEnh_ID sammen, og vises slik.
c. fartoy_new.doc er et flytdiagram som beskriver prosessen som brukeren følger for å legge inn et nytt fartøy. fartoy_new.php og eventuelt andre filer, reflekterer denne prosessen. 
d. Feltene er ikke være bredere enn nødvendig. <Multikolonner er brukt der det har vært hensiktsmessig.
e. De eksisterende klassene i app.css er uendret.

5. Gjennomføring:
Du skal lage en plan for hvordan filen, eventuelt filene, er tenkt å fungere, Den skal du presentere for meg.
Jeg vil kommentere planen, og gi beskjed om videre fremdrift.
			
6. Anker i koden (for trygg innfasing)
Innsettingssted / Erstatning: I roten
Leveranseformat: Fullt ferdig, oppdatert php-fil. 


7. Akseptkriterier (Acceptance Criteria)
 Responsiv oppførsel (mobil/desktop) er intakt

 Ingen PHP warnings/notices

 Ingen XSS/sti risiko (bruk h() og basename() der det gjelder)

 Eksplisitte “før/etter”-skjermbilder vedlagt (valgfritt men ønskelig)

8. Testplan (hvordan testes dette)

Lokal (XAMPP):

 Laste siden(e) direkte og verifisere layout

 CTRL+F5 hard refresh (cache)

Browsere:

 Chrome

 
Skjermbredder:


 max 1100px

9. Sikkerhet og kvalitet

HTML/PHP:

 Bruk h() for output escaping

 Bruk basename() på filnavn hvis dynamiske bildekilder

CSS:

 Ikke bruk !important med mindre nødvendig

 Scope endringer (f.eks. .card.centered-card)

JS:

 Ikke endre hero-rotator.js

10. Risiko og rollback


Rollback/plan: Hele filen reverteres

Avhengigheter: Ingen