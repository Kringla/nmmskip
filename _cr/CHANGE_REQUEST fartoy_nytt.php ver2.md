1. Mål (Goal)

Hva: Ønsker å lage fartoy_nytt.php med eventuelle andre .php-filer hvis hensiktsmessig.

Hvorfor: Fjerne feil og forbedre lay-out.


2. Scope (Omfang)

Berørte filer (eksakt sti):/admin/fartoy_nytt.php

Tillatte endringer: Tillatt å skape ny(e) fil(er). Tillatt å legge til/fjerne redundante klasser i /assets/css/app.css


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
Legg merke til at:
i. Filene /admin/fart_new.php og /admin/fart_edit.php ikke eksisterer.
ii. Denne filen som nå utvikles, fartoy_nytt.php, vil bli brukt som mal for senere utarbeidelse av ny fartoy_edit.php når alt er på plass og /admin/fartoy_nytt.php er ferdig testet.
iii. /admin/app.css kan endres for å reflektere bortfallet av filene /admin/fart_new.php og /admin/fart_edit.php.

4. Detaljert spesifikasjon

Nåværende oppførsel: Ingen

Ønsket oppførsel/krav til utvikling:
a. Forståelsen av hva som er et "nytt fartøy" i denne sammenheng er essensiell for riktig utforming av fartoy_nytt.php. Et nytt fartøy oppstår hvis:
a1: Nytt fartøysobjekt, dvs. et nybygd fartøy ved et verft. 
a2: Endret teknisk spesifikasjon for et eksisterende fartøy.
a3: Nytt navn, fartøystype (utført i tblFartSpes), rederi, registreringshavn og/eller flaggstat (nasjonan) på et eksisterende fartøy
a4: fartoy_new.doc er et flytdiagram som beskriver prosessen som brukeren følger for å legge inn et nytt fartøy. fartoy_new.php og eventuelt andre filer, reflekterer denne prosessen.

b. De eksisterende klassene i app.css er uendret.

c. Alle kolonner i tblfartobj/tblfartspes/tblFartTid skal kunne settes, med unntak av historie i tblFartTid.

d. Alle Ja/nei valgmuligheter vises med buttons

e1: I tblFartObj angis Bygget angis som helt år. For angivelse av verft gjelder: Hvis ikke eget Skrogverft, settes SkrogID = LeverID og BnrSkrog = Byggenr.
e2: Søk etter eksisterende fartøy for å velge fartøysobjekt kan gjøres ved gjenbruk av fartoy_navn_sok.php, men da begrenset til fartøuyer i tblFartObj.

f1: I tblFartSpes hører feltene Tonnasje og TonnEnh_ID sammen, og Drekigh og DrektEnh_ID sammen, og vises slik.
f2: tblztonnenh er parametertabell både for TonnEnh_ID og DrektEnh_ID.

g. Nytt verft skal kunnelegges inn både i forbindelse med verftsangivelser i tblFartObj og tblFartSpes.

h. I forbindelse med tblFartTid skal de være mulig å legge inn lenker til tblxFartLink.

i. Endring av fartøystype (FartType_ID) skjer kun ved konstruksjonsendringer (endret driftsform, skrogdimensjoner, motor, o.l.. Derfor skal det opprettes ny rad i tblFartSpes og tblFartTid.

j. Objekt, Navning og Eierskifte settes til true ved nytt fartøy

k. Feltene er ikke være bredere enn nødvendig. Generelt har tidligere forslag vært unødvendig brede. Multikolonner kan brukes der det har vært hensiktsmessig. Kun Rederi og Historikk felt kan forsvare felt på hele rader. 


5. Gjennomføring:
Du skal lage en plan for hvordan filen, eventuelt filene, er tenkt å fungere og hvordan de kommer til å se ut. Den skal du presentere for meg.
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