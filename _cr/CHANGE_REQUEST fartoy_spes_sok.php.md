1. Mål (Goal)

Hva: Ønsker endre fartoy_spes_sok.php

Hvorfor: Forbedre lay-out.


2. Scope (Omfang)

Berørte filer (eksakt sti):/user/fartoy_spes_sok.php, app.css
Siste versjon av begge er vedlagt.

Tillatte endringer: Lov å å endre berørte fil og app.css.


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)

Sitens øvrige produserte og tesstete filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på  https://github.com/Kringla/ProdSWeb NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnes, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)
Les deg alltid opp på SkipsWeb_PS og SkipsWeb_SCHEMA, som også ligger som projektdokumentasjon.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønskete filer som vedlegg eller på GitHub referansen over!

Bruk h() og basename() der det er relevant.

4. Detaljert spesifikasjon

Nåværende oppførsel: Kontrollboksene kommer fortløpende, og titler kommer noen ganger på andre linjer enn input-boksen den hører til. 

Ønsket oppførsel:
a. Overskriften "Søk fartøysspesifikasjoner" og knapper under kontrollboksene er bra plassert, og skal IKKE endres!
b. PLASSERINGEN (IKKE utseende) av kontrollboksene endres slik:
 b1. Maksimalt tre kontrollbokser på hver linje, med tittel midtstilt over kontrollboksen. Padding over hver linje og under siste linje 16px.
 b2. Rekkefølge/plassering av kontrollboksene:
		Linje 1: Type, funksjon
		Linje 2: Driftsmiddel, rigg og motor
		Linje 3: Skrog og materiale
		Linje 4: Klasse

Gjennomføring: Når en løsning er funnet, gjør en revisjon av løsningen, før løsningen oversendes etter eventuell forbedring.
			
5. Anker i koden (for trygg innfasing)
Innsettingssted / Erstatning: /user
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