Change Request (CR)
1. Mål (Goal)

Hva: Ønsker å endre søksfunksjonalitet i fil

Hvorfor: For å få forbedret visning av  filer


2. Scope (Omfang)

Berørte filer (eksakt sti):  /user/rederi_sok.php /user/verft_sok.php
Ingen andre  i repoet er berørt.

Tillatte endringer: Lov å endre visningslogikken i filene. Endringen skal ikke endre noe annet.


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)
"SkipsWeb_Fields v*.txt"		Angivelse av feltlenger for bruk i Forms i filer med to-kolonne tabeller (som i fartoy_edit.php, fartoy_nytt.php)
"constants.php"					Konstanter benyttet i prosjektet.

Sitens øvrige produserte og testete filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på public repo på GitHub,  https://github.com/Kringla/SkipsWeb. 
Hvis flere versjoner finnes, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)


4. Detaljert spesifikasjon

4a. Nåværende oppførsel: 
	i. 	Søkeresultatet på rederi i /user/rederi_sok.php og verft i /user/verft_sok.php kan ofte gi resultater som bestå av mange rader når antallet blir over 10 synes ikke de respektive, tilhørende fartøystabeller. 
	
	
4b. Ønsket oppførsel: 
	i.	Kun de 10 første radene i søkeresultatet skal vises, sammen med angivelse av totalt antall funnet. Ved hjelp av paginering som i /user/fartoy_spes_sok.php, kan en bla seg gjennom hele resultatlisten.

5. Gjennomføring
Les igjennom prosjektets dokumenter referert i pkt 3 over, som også ligger som Projektets dokumentasjon.

Les deg opp på filen i GitHub repo'et.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønsket(e) fil(er) som er sagt at er vedlegg eller på GitHub referansen i pkt 3!

Bruk h() og basename() der det er relevant.

Les igjennom alle vedlagte filer. Vedlagte filer har prioritet over filene på GitHub og i Prosjektets dokumenter.

Vurder mest hensiktsmessig løsning. Kontroller at alle punkter i denne CRen er imøtekommet. Gi anbefaling.

Når tillatelse til å gå videre, lag endringer basert på mine responser.

Gjør en kritisk revisjon av utarbeidete endringer, og utfør eventuelle korrigeringer som følge av det.
Hvis diff-patch, kontroller at patchen refererer til vedlagt(e) fil(er) eller siste GitHub versjon hvis ikke vedlagt.


Gjør leveranse iht. pkt 6. under.
			
6. Anker i koden (for trygg innfasing)
Innsettingssted / Erstatning: Filenes opprinnelige, angitte plassering.
Leveranseformat:Nedlastbare, endrete filer


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