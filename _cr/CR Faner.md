Change Request (CR)
1. Mål (Goal)

Hva: Ønsker å rette feil i filer

Hvorfor: For å få feilfri visning av  filer


2. Scope (Omfang)

Berørte filer (eksakt sti):  Alle i /user, alle i /admin unntatt users.php og dashboard.php. 
Andre i repo kan være berørt.

Tillatte endringer: Lov å endre feil i alle direkte berørte, men ikke annet en feilen. Endringen skal ikke endre øvrig funksjonalitet og utseende av filene.


3. Rammer og referanser
Prosjektets styrende filer er:
"SkipsWeb_PRD v*.txt" 			Overordnete krav og utviklingsplan (v* er versjonsnummer)
"SkipsWeb_PS v*.txt" 			Prosjektets styrende strukturer og repo oversikt (v* er versjonsnummer)
"SkipsWeb_SCHEMA v*.sql"  		Schema til SkipsDb (v* er versjonsnummer)
"SkipsWeb_Fields v*.txt"		Angivelse av feltlenger for bruk i Forms i filer med to-kolonne tabeller (som i fartoy_edit.php, fartoy_nytt.php)
"constants.php"					Konstanter benyttet i prosjektet.

Sitens øvrige produserte og testete filene, sammen med SISTE VERSJON av de overnevnte filene, finnes på public GitHub,  https://github.com/Kringla/ProdSWeb. NB! Der finnes de i ett directory, uten subdirectories!
Hvis flere versjoner finnes, skal ALLTID versjonen med høyest versjonsnummer brukes (* i 'v*' angir versjonsnummer.)


4. Detaljert spesifikasjon

4a. Nåværende oppførsel: 
	i. 	I øverste venstre hjørne står det "Logg inn". Trykker man på lenken får en "File not found".
	ii.	Fanene "Home" og "Administrasjon" er ikke lenger synlig.
	iii.Fanen "Logg ut" var tidligere synlig.
	
4b. Ønsket oppførsel: 
	i.	"Logg inn" m/lenke fjernes helt.
	ii.	Fane merket "Hjem" skal, når valgt, bringe brukeren tilbake til /index.php. En fane merket "Administrasjon" skal bringe brukeren til /login.php. Fanen "Hjem" skal ikke være synlig når /index.php vises.
	iii.Fanen "Logg ut" skal ikke eksistere.

5. Gjennomføring
Les igjennom prosjektets dokumenter referert i pkt 3 over, som også ligger som Projektets dokumentasjon.

Les deg opp på filene i GitHub repo'et.

NB! Stopp videre arbeide, og si i fra hvis du ikke finner ønskete filer som vedlegg eller på GitHub referansen i pkt 3!

Bruk h() og basename() der det er relevant.

Les igjennom alle vedlagte filer. Vedlagte filer har prioritet over filene på GitHub og i Prosjektets dokumenter.

Vurder mest hensiktsmessig løsning. Kontroller at alle punkter i denne Change Requesten er imøtekommet. Gi anbefaling.

Når tillatelse til å gå videre, lag endringer basert på mine responser.

Gjør en kritisk revisjon av utarbeidete endringer, og utfør eventuelle korrigeringer som følge av det.

Gjør leveranse iht. pkt 6. under.
			
6. Anker i koden (for trygg innfasing)
Innsettingssted / Erstatning: Filenes opprinnelige, angitte plassering.
Leveranseformat: Diff-patch med angivelse av fil som skal endres, og 3 linjer før og etter innsetting av endring.


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