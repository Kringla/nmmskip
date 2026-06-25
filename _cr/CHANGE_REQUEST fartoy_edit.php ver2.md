1. Mål (Goal)

Hva: Ønsker å lage fartoy_edit.php

Hvorfor: For å kunne endre viktige deler av databasens innhold


2. Scope (Omfang)

Berørte filer (eksakt sti): Ny fil:/admin/fartoy_edit.php samt muligens fartoy_admin.
Siste versjon av fartoy_nytt.php er vedlagt.

Tillatte endringer: Lov å å endre berørte fil. Tillatt å skape ny(e) fil(er)


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)

Sitens øvrige produserte og testete filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på public GitHub,  https://github.com/Kringla/ProdSWeb. NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnes, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)
Les deg alltid opp på SkipsWeb_PS og SkipsWeb_SCHEMA, som også ligger som projektdokumentasjon.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønskete filer som vedlegg eller på GitHub referansen over!

Bruk h() og basename() der det er relevant.

4. Detaljert spesifikasjon

Nåværende oppførsel: Filen eksisterer ikke.

Ønsket oppførsel:
a. Kunne endre alle data i tblFartObj (hvis merket "Objekt" i tblFartTid), tblFartSpes og tblFartTid, samt tblxFartLink
b. "Look and feel" som i fartoy_nytt.php.
c. Under Tittelen skal det vises detaljer fra tblFartObj for det fartøyet som skal editeres. Hvis fartøyet som editeres ikke er merket som "Objekt", sakl feltene for tblFartObj IKKE kunne endres.
			
5. Anker i koden (for trygg innfasing)
Innsettingssted / Erstatning: I roten
Leveranseformat: Fullt ferdig, oppdatert php-fil. 


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