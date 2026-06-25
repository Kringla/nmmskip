1. Mål (Goal)

Ønsker å få en gul ramme rundt resultattabellen i fartoy_navn_sok.php


Hvorfor: Forbedring


2. Scope (Omfang)

Berørte filer er

 /user/fartoy_navn-sok.php
 


Tillatte endringer:

Hele filen

Lov å endre CSS-klasser i app.css


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)

De produserte filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på  https://github.com/Kringla/ProdSWeb NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnesd, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)
Les deg alltid opp på SkipsWeb_PS og SkipsWeb_SCHEMA og relevante/berørte filer.

Si i fra hvis du ikke finner ønskete filer på GitHub referansen over!

Bruk h() og basename() der det er relevant.

4. Detaljert spesifikasjon

Nåværende oppførsel: Tabellen har ingen tydelig, synlig ramme.
Ønsket oppførsel: Tabellen skal ha en 3px ramme i gul farge (#ffff00).

Spør hvis denne spesifikasjonen synes mangelfull eller uklar.
			
5. Anker i koden (for trygg innfasing): Patch (diff) med før/etter!!

6. Akseptkriterier (Acceptance Criteria)

 Visuelt: endringen er tydeliggjort

 Ingen andre sider påvirkes

 Responsiv oppførsel (mobil/desktop) er intakt

 Ingen PHP warnings/notices


7. Testplan (hvordan testes dette)

Lokal (XAMPP):

 Laste siden(e) direkte og verifisere layout

 CTRL+F5 hard refresh (cache)

Browsere:

 Chrome

 
Skjermbredder:


 > 1200px

8. Sikkerhet og kvalitet

HTML/PHP:

 Bruk h() for output escaping

 Bruk basename() på filnavn hvis dynamiske bildekilder

CSS:

 Ikke bruk !important med mindre nødvendig

 Scope endringer (f.eks. .card.centered-card)



9. Risiko og rollback


Rollback/plan: Hele filen reverteres

Avhengigheter: Ingen