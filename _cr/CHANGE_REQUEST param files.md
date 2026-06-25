1. Mål (Goal)

Hva: Ønsker å legge til ny funksjonalitet.

Hvorfor: For å muliggjørte endringer i alle parametertabeller. Parametertabell starter alle med 'tblz'


2. Scope (Omfang)

Berørte filer (eksakt sti): /admin/fartoy_admin.php


Tillatte endringer: Lov å endre /admin/fartoy_admin.php


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)
"SkipsWeb_Fields v*.txt			Angivelse av feltlenger for bruk i Forms i filer med to-kolonne tabeller (som i fartoy_edit.php, fartoy_nytt.php)

Sitens øvrige produserte og testete filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på public GitHub,  https://github.com/Kringla/ProdSWeb. NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnes, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)
Les deg alltid opp på SkipsWeb_PS og SkipsWeb_SCHEMA, som også ligger som projektdokumentasjon.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønskete filer som vedlegg eller på GitHub referansen over!

Bruk h() og basename() der det er relevant.

4. Detaljert spesifikasjon

Nåværende oppførsel: Endringer av parametre i parametertabeller ikke mulig. 


Ønsket oppførsel: Mulig å finne, endre, legge til og slette rader i ALLE parametertabellene.

5. Gjennomføring
Les igjennom prosjektets dokumenter referert i pkt 3 over.
Les igjennom andre vedlagte filer. Vedlagte filer har prioritet over filene på GitHub og i Prosjektets dokumenter.
Vurder mest hensiktsmessig løsning, gi anbefaling.
Når tillatelse til å gå videre, lag endringer basert på mine responser.
Kontroller at alle punkter i denne Change Requesten er imøtekommet. Ikke lever noe før denne sjekken er gjort!!
Gjør en kritisk revisjon av utarbeidete endringer, og utfør eventuelle korrigeringer som følge av det.
Gjør leveranse iht. pkt 6. under.
			
6. Anker i koden (for trygg innfasing)
Innsettingssted / Erstatning: /user
Leveranseformat: Fullt ferdig, oppdatert(e) filer.


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