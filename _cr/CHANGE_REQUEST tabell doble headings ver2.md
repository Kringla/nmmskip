1. Mål (Goal)

Hva: Ønsker å rette feil i filer

Hvorfor: For å få feilfri visning av tabeller brukt i filer


2. Scope (Omfang)

Berørte filer (eksakt sti):  /user/fartoy_navn_sok.php, /assets/css/app.css og /assets/css/v2/components.css
Andre i repo kan være berørt.

Tillatte endringer: Lov å endre alle direkte berørte


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)
"SkipsWeb_Fields v*.txt			Angivelse av feltlenger for bruk i Forms i filer med to-kolonne tabeller (som i fartoy_edit.php, fartoy_nytt.php)

Sitens øvrige produserte og testete filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på public GitHub,  https://github.com/Kringla/ProdSWeb. NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnes, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)


4. Detaljert spesifikasjon

Nåværende oppførsel: Sidens heading og tabellen står fast på siden, og forsvinner ved scrolling av siden. "Vis" knappen uforholdsmessig stor. Skulle vært som tidlige .btn-small 


Ønsket oppførsel: Fast heading og tabell-container. Tabelloverskrift står fast ved scrolling i tabellen. Liten "Vis"-knapp.

5. Gjennomføring
Les igjennom prosjektets dokumenter referert i pkt 3 over, som også ligger som Projektets dokumentasjon.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønskete filer som vedlegg eller på GitHub referansen i pkt 3!

Bruk h() og basename() der det er relevant.

Les igjennom alle vedlagte filer. Vedlagte filer har prioritet over filene på GitHub og i Prosjektets dokumenter.

Vurder mest hensiktsmessig løsning. Kontroller at alle punkter i denne Change Requesten er imøtekommet. Gi anbefaling.

Når tillatelse til å gå videre, lag endringer basert på mine responser.

Gjør en kritisk revisjon av utarbeidete endringer, og utfør eventuelle korrigeringer som følge av det.

Gjør leveranse iht. pkt 6. under.
			
6. Anker i koden (for trygg innfasing)
Innsettingssted / Erstatning: filenes opprinnelige, angitte plassering.
Leveranseformat: Fullt ferdig, oppdatert(e) filer, levert som nedlastbare filer.


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