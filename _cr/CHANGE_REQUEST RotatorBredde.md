1. Mål (Goal)

Hva: Ønsker å redusere rotrator-blokken i iondex.php

Hvorfor: Forbedring


2. Scope (Omfang)

Berørte filer (eksakt sti):

 /index.php, assets/css/app.css


Tillatte endringer:

layout/markup

 Lov å lendre CSS-klasser i app.css


3. Rammer og referanser

Prosjektfiler (siste versjon, GitHub): index.php
Bruk alltid nyeste filer i Kringla/ProdSWeb.

Bruk h() og basename() der det gjelder

SCHEMA: SkipsWeb_SCHEMA v6.sql

4. Detaljert spesifikasjon

Nåværende oppførsel: Rotator bruker hele skjerm-bredden

Ønsket oppførsel: Rotator bruker 1000px.


			
5. Anker i koden (for trygg innfasing)
 Patch (diff) med før/etter

6. Akseptkriterier (Acceptance Criteria)

 Visuelt: endringen er tydelig i riktig blokk/komponent

 Ingen andre sider påvirkes (spesielt index.php/hero‑rotator)

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