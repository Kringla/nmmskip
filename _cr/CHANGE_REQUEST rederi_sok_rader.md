1. Mål (Goal)

Ønsker å forbedre rederi-sok.php


Hvorfor: Forbedring


2. Scope (Omfang)

Berørte filer er

/user/rederi_sok.php
 


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
Les deg alltid opp på SkipsWeb_PS og SkipsWeb_SCHEMA.

Si i fra hvis du ikke finner ønskete filer på GitHub referansen over!

Bruk h() og basename() der det er relevant.

4. Detaljert spesifikasjon

Nåværende oppførsel: Rederisøket gjøres i hele rederiet feltet. Rederier som er er funnet og deres fartøyer vises med stor høyde på hver rad pga. 'Velg'-knappenes størrelse.
Ønsket oppførsel: Ønsker å begrense søket i 'rederi'-feltet. Alt til høyre for det som kommer sist av siste komma ',', eller siste lukkende parantes (')'), skal ikke inngå i søket.
I listene må 'Velg'-knappenes størrelse reduseres vesentlig, eller erstattes av en firkant under overskrift 'Velg'. Høyden på hver rad må ikke være større en det som kreves for å få lesbar tekst.

Listeblokkene skal stå sentrert på siden.

Spør hvis denne spesifikasjonen synes mangelfull eller uklar.
			
5. Anker i koden (for trygg innfasing): Patch (diff) med før/etter

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