1. Mål (Goal)

Hva: Ønsker få mulighet til å rette opp, slette og tilføye fartøyer og fartøysdata.

Hvorfor: Utvidelse av eksisterende site.


2. Scope (Omfang)

Berørte filer (eksakt sti): /index.php, /login.php, /user/fartoy_navn_sok.php, /user/fartoydetaljer.php, /user/fartoyspes.php. og /assets/css/app.css.
Siste versjon av disse er vedlagt.

Tillatte endringer:

 SQL og layout/markup

 Lov å legge til å endre berørte filer, og til å skape nye filer og CSS-klasser nederst i app.css


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

Nåværende oppførsel: Ønsket funksjonalitet finnes ikke på det nåværende.

Ønsket oppførsel: 
A. Kun brukere i admin-rollen skal ha adgang til filen(e)
B. Det skal være mulig å finne fartøyet på samme måte som i fartoy_navn_sok.php, og utføre rettelser i de feltene som er tilgjengelig i fartoydetaljer.php og fartoyspes.php.
C. Det skal være mulig å slette fartøyer i sin helhet. Hvis fartøyet er et objekt, dvs et fartøy som er nybygd ved et verft og som er markert med 'true' i objektfeltet, skal alle fartøy som er basert på dette fartøyet også slettet. Fartøy som ikke er objekt kan slettes alene, uten at andre fartøyer påvirkes.
D. Det skal være mulig å legge til nye fartøyer som nybygg (objekt = True) eller som nye navn/eiere/nasjoner på eksisterende fartøysobjekter.
			
5. Anker i koden (for trygg innfasing)


Innsettingssted / Erstatning: Nye filer erstatter ingen. De legges mest hensiktsmessig i /user


Leveranseformat: Fullt ferdig php-fil. Som patch (diff) med før/etter for endringer av app.css.


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