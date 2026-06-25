1. Mål (Goal)

Ønsker å forbedre rederi-sok.php


Hvorfor: Forbedring


2. Scope (Omfang)

Berørte filer er

 /user/verft_sok.php
 


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

Nåværende oppførsel: Verft som er er funnet vises ikke, kun en rekke fartøynavn og verftsnavn. Øvrige deler av filen Overskrift og søkefelt er bra som de er.
Ønsket oppførsel: Ønsker å se en verftliste over de verftene som passet til søkekriteriet. Listen skal ha tblVerft som basis for søket, og med nødvendige oppslag i andre tabeller. Verftlisten skal ha følgende felter: 'Verft' (VerftNavn), 'Sted' (Sted) og 'Nasjon' (Basert på oppslag basert på Nasjon_ID). Listens overskrift skal være 'Verft funnet'. skal være 'Verft funnet'. Det enkelte verftet skal være valgbart med museklikk. 

Under Verftlisten skal det vises en Leveranseliste med de fartøyene som har vært levert av det verftet som er valgt. Default er det øverste verftet i verftlisten. Listen tar utgangspunkt i tabell tblFartSpes som hovedtabell og med nødvendige oppslag i andre tabeller. 
Leveranselisten skal ha følgende felter: 'Byggenr' (Byggenr), 'Bygd År/mnd' (konkatinert YearSpes & "/" & MndSpes hvis MndSpes > 9, ellers YearSpes & "/0" & MndSpes), Fartøyets 'Navn' (konkatinert FartFork & " " & FartNavn i relevant rad i tblFartObnj), 'Reg. Havn' (RegHavn i relevant rad i tblFartTid), , 'Eier' (Rederi i relevant rad i tblFartTid) og 'Objekt'. Listens overskrift skal være 'Leveranser fra det valgte verftet'

Under Leveranselisten skal det vises en Skrogbyggliste med de fartøyene som har vært levert av det verftet som er valgt. Default er det øverste verftet i verftlisten. Listen tar utgangspunkt i tabell tblFartSpes som hovedtabell og med nødvendige oppslag i andre tabeller. NB! Verft_ID i tblVerft skal linkes til Skrog_ID i tblFartSpes, og det er kun rader der tabell tblFartSpes sitt Skrog_ID felt er forskjellig fra den samme tabellens Verfts_ID felt som er relevante for Skrogbygglisten. 
Skrogbygglisten skal ha følgende felter: 'Byggenr' (BnrSkrog), 'Bygd År/mnd' (konkatinert YearSpes & "/" & MndSpes hvis MndSpes > 9, ellers YearSpes & "/0" & MndSpes), Fartøyets 'Navn' (konkatinert FartFork & " " & FartNavn i relevant rad i tblFartObnj), 'Reg. Havn' (RegHavn i relevant rad i tblFartTid), , 'Eier' (Rederi i relevant rad i tblFartTid) og 'Objekt'. Listens overskrift skal være 'Skrogleveranser fra det valgte verftet'

Fartøy skal kunne velges, tilsvarende som for fartoy_navn_sok.php.

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