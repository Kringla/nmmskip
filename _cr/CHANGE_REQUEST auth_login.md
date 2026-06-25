1. Mål (Goal)

Hva: Ønsker få mulighet til å rette opp, slette og tilføye fartøyer og fartøysdata.

Hvorfor: Utvidelse av eksisterende site.


2. Scope (Omfang)

Berørte filer (eksakt sti):/auth_login.php
Siste versjon er vedlagt.

Tillatte endringer: Lov å å endre html-kode i berørte fil.


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)

Sitens øvrige produserte og tesstete filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på  https://github.com/Kringla/ProdSWeb NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnes, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)
Les deg alltid opp på SkipsWeb_PS og SkipsWeb_SCHEMA, som også liggere som projektdokumentasjon.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønskete filer som vedlegg eller på GitHub referansen over!

Bruk h() og basename() der det er relevant.

4. Detaljert spesifikasjon

Nåværende oppførsel: Layout ikke i tråd med resten av siten.

Ønsket oppførsel: Bedret layout uten bruk av / referanse til eksisterende css-fil.
Layouten skal ha et toppbånd 38px høyt, med bakgrunnsfarge #003f7d. 
I toppbåndet skal logo fra /assets/img/skipsweb-logo.jpg vises midtstilt
Under toppbåndet skal alt på siden
- være midtstilt
- med max bredde 1100px, og
- bakgrunnsfargen ha fargekode #f5f9fc og tekstfargen fargekode #14213d.
Øverst på siden skal tittelen være "Innlogging på SkipsWeb"
Epostfeltet skal stå over passordfeltet. Feltene der en legger inn epost og passord skal være like store og  aligned venstre. 
"Logg inn"-knappen skal være 2 linjer under passord-feltet.

			
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