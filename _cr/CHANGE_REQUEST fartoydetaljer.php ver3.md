1. Mål (Goal)

Hva: Ønsker å endre fartoydetaljer.php

Hvorfor: For å forbedre.


2. Scope (Omfang)

Berørte filer (eksakt sti): /user/fartoydetaljer.php.
Siste versjon av fartoydetaljer.php og schema (skipsWeb_Schema v12.sql) er vedlagt.

Tillatte endringer: Lov å å endre alt mellom <!-- Hovedinfo-boks --> og <!-- Navnehistorikk-boks --> i berørte fil.


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

Nåværende oppførsel: Grei, men ønsker at hoveddataene skal vises som i filen fartoy_edit.php (vedlagt) for dataen fra tblFartObjID og tblFartTidID.

Ønsket oppførsel:
a. Lay-out med samme "look and feel" 
c. Ingen øvrige endringer av formattering/layout.

5. Gjennomføring
Les igjennom prosjektetss dokumenter referert i pkt 3 over.
Les igjennom andre vedlagte filer. Vedlagte filer har prioritet over filene på GitHub og i Prosjektets dokumenter.
Lag endringer.
Kontroller at alle punkter i denne Change Requesten er imøtekommet. Ikke lever noe før denne sjekken er gjort!!
Gjør en kritisk revisjon av utarbeidete endringer, og utfør eventuelle korrigeringer som følge av det.
Gjør leveranse iht. pkt 6. under.
			
6. Anker i koden (for trygg innfasing)
Innsettingssted / Erstatning: /user
Leveranseformat: Fullt ferdig, oppdatert kode levert i chatten. IKKE diff-patch!


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