# Hvordan et fartøys livsløp representeres i databasen 'SkipsWeb'

Et fartøy eksisterer i databasen som i virkeligheten: Etter at det har oppstått, dvs. bygget ved et verft med byggenummer og IMO-nummer, kan det få ny eier, nasjonalitet, registreringshavn og kallesignal, få en ny type fremdriftsmiddel eller på annen måte ombygges, eller kombinasjoner av disse.
Den fysiske gjenstanden, fartøyet, har et livsløp som skal reflekteres i databasen ved tabellene `tblFartObj`, `tblfartspes`og `tblfarttid`.

## Tabelloversikt

I denne oversikten angis noen av de viktige feltene i tabellene `tblfartobj`, `tblfartspes` og `tblfarttid`. For fullstendig liste over feltene i tabellene under refereres det til  databasens  schema i `SkipsWeb_Schema.sql`

| Tabell | Formål | Nøkkel | Relasjon |
|--------|--------|--------|----------|
| `tblfartobj` | Det fysiske fartøyet — uforanderlig identitet | `FartObj_ID` | — |
| `tblfartspes` | Teknisk spesifikasjon (ny rad ved nybygg og ved ombygning) | `FartSpes_ID` | → `FartObj_ID` |
| `tblfarttid` | Tidslinjehendelse — knytter sammen alt | `FartTid_ID` | → `FartObj_ID`, `FartSpes_ID`|

### Viktigste felt i `tblfartobj`
- `FartObj_ID` (PK, auto), `NavnObj`, `FartType_ID` (FK → `tblzfarttype`), `IMO`, `LeverID` (FK → `tblverft.Verft_ID`),`Bygget` (år bygget ved leverende verft (`LeverID`)), `ByggeNr` (Byggenummer ved leverende verft (`LeverID`)), `SkrogID` (FK → `tblverft.Verft_ID`, skrogbyggende verft), `BnrSkrog` (Byggenummer ved skrogbyggende verft (`SkrogID`)), `StroketYear` (år strøket fra register), `StroketID` (FK → `tblzstroket`).

### Viktigste felt i `tblfartspes`
- `FartSpes_ID` (PK, auto), `FartObj_ID` (FK), `YearSpes`, `MndSpes`, `Verft_ID` (FK → `tblVerft`), `Byggenr`, `FartType_ID` (FK → `tblzfarttype`), `FartFunk_ID` (FK → `tblzfartfunk`), `FartSkrog_ID` (FK → `tblzfartskrog`), `FartDrift_ID` (FK → `tblzfartdrift`), `Tonnasje`, `TonnEnh_ID` (FK → `tblztonnenh`), `Drektigh`, `DrektEnt_ID` (FK → `tblzdrektenh`), `FartRigg_ID` (FK → `tblzfartrigg`), `FartMotor_ID` (FK → `tblzfartmotor`), `FartMat_ID` (FK → `tblzfartmat`), `FartKlasse_ID` (FK → `tblzfartklasse`), `Objekt`

### Viktigste felt i `tblfarttid`
- `FartTid_ID` (PK, auto), `YearTid`, `MndTid`, `FartObj_ID` (FK), `FartSpes_ID` (FK), `FartNavn`, `Rederi`, `Nasjon_ID` (FK → `tblzNasjon`), `RegHavn`, `MMSI`, `Kallesignal`, `Fiskerinr`, `Objekt`, `Bygging`, `Navning`, `Eierskifte`


## Det fysiske fartøyets 'fødsel'

### Wizard-flyt i 'fødselen'

Registrering av et nytt fartøy gjøres gjennom de 3-stegs wizard som ikke kan avbrytes underveis:
1. **Steg 1**: Opprett `tblfartobj` (fartøynavn/type, verft, byggenr, IMO m.m.) `Objekt` er forhåndsavkrysset.
2. **Steg 2**: Legg til spesifikasjon i `tblfartspes` (tekniske data).  Verft, fartøytype, m.m. forhåndsutfylt fra steg 1.
3. **Steg 3**: Legg til tidslinjehendelse i `tblfarttid`.  Flaggene `Objekt`, `Navning` og `Eierskifte` forhåndsavkrysses.

Et nybygd fartøy er beskrevet ved en rad i hver av disse tre tabellene. Her er detaljene i sammenhengen når et fartøy bygges.

### Steg 1

I dette trinnet får tabellen `tblfartobj` en ny rad. I den nye raden verdi-settes `FartObj_ID` (automatisk ved lagring) og minst `NavnObj`, `FartType_ID`, `Bygget`, `LeverID` og `Byggenr`. Hvis `SkrogID` og/eller `BnrSkrog` ikke er gitt verdi, får disse feltene verdien av henholdsvis `LeverID` og `Byggenr`. Deretter lagres raden, men holdes aktiv i session.

### Steg 2

1. Når den nye raden i tabellen `tblfartobj` lagres, skal det automatisk oppstå en ny rad i tabellen `tblfartspes`. I denne raden verdi-settes `FartSpes_ID` (automatisk ved lagring), og følgende felt får verdier fra den nylig lagrete raden i `tblfartobj`:
- `FartObj_ID` =  `tblfartobj.FartObj_ID` 
- `YearSpes` = `Bygget`
- `FartType_ID` =  `tblfartobj.FartType_ID`
- `Verft_ID` = `LeverID`
- `Byggenr` =  `tblfartobj.Byggenr`
2. `Objekt` skal automatisk settes til '1', og`MndSpes` settes til '01'. 
3. Hvis noen av feltene som har 'arvet' verdier fra `tblfartobj` endres i denne nye raden i tabellen `tblfartspes`, må de tilsvarende feltene i `tblfartobj` automatisk oppdateres/endres.
4. Når feltene `FartFunk_ID`, `FartSkrog_ID`, `FartDrift_ID` og `FartMat_ID` er gitt verdier kan raden lagres, men holdes aktiv i session.

### Steg 3

Når den nye raden i tabellen `tblfartspes` lagres skal det automatisk oppstå en ny rad i tabellen `tblfarttid`. Her fylles følgende felt automatisk:
	- `YearTid` = `YearSpes` fra tabellen `tblfartspes`
	- `MndTid` = `MndSpes` fra tabellen `tblfartspes`
	- `FartObj_ID` = `FartObj_ID` fra tabellen `tblfartobj`/`tblfartspes`
	- `FartSpes_ID` = `FartSpes_ID` fra tabellen `tblfartspes`
	- `FartNavn` = `NavnObj` fra tabellen `tblfartobj`
	- `FartType_ID` = `FartType_ID` fra tabellen `tblfartspes`
	- `Objekt` = '1' (`Objekt` = 1 betyr at raden inneholder data om et nybygg)
	- `Navning` = '1' (`Navning` = 1 betyr at raden inneholder data om en navneendring (`Fartnavn`), eller navngivning ved nybygg)
	- `Eierskifte` = '1' (`Eierskifte` = 1 betyr at raden inneholder data om et eierskifte (`Rederi`), eller eier ved nybygg)
	- `Annet` = '1' (`Annet` = 1 betyr at raden inneholder data om et skifte av registreringshavn, nasjon, kallesignal/MMSI og/eller fiskerinr, samt ved nybygg)

Feltene `Rederi` og `Nasjon_ID` (`Nasjon_ID` fra tabellen `tblzNasjon`) samt `RegHavn` og `Kallesignal` gis en verdi før lagring kan skje.
Når den nye raden i tabellen `tblFartTid` er lagret, er det nybygde fartøyet definert, og lagrete rader i session kan frigis. 

### Generelt, for alle stegene
Endringer som gjøres underveis i registreringen av et nytt fartøy må oppdatere relaterte felt i hver av de andre av disse tre tabellene. Dette gjelder kun under disse wizard-stegene.

## Endringer i det fysiske fartøyets 'liv'

### Endringsregler

1. Et fartøys `FartObj_ID` kan **aldri** endres.
2. Fartøytype (`FartType_ID`) har sin kildeverdi i feltet `FartType_ID` i tabellen `tblfartspes`. Feltene `FartType_ID` (i `tblfartobj`) og `FartType_ID` (i `tblfarttid`) er **avledede** — de kopieres fra `FartType_ID` og skal ikke settes uavhengig. Endring av fartøytype skjer **kun** ved å opprette en ny rad i `tblfartspes`.
3. Feltet `Objekt` i tabellen `tblfartspes` og feltet `Objekt` i tabellen `tblfarttid` kan ikke endres. I nye rader i disse tabellene med samme `FartObj_ID` skal verdien av disse feltene alltid være = '0'.

**NB**: Redigering av eksisterende rader brukes kun for å korrigere feil. Reelle endringer i fartøyets livsløp skal alltid registreres som **nye rader** i henhold til prosedyrene i det etterfølgende.

### Type endring

#### Type A - **Endringer av feil i fartøydata**

Endringer kan skje i alle tabellene. **ALLE** slike endringer **MÅ** bekreftes eksplisitt før de iverksettes. Alle slike endringer må automatisk inkludere kontroll av konsistens mellom relevante felt i tabellene.

#### Type B - **Endringer som ikke er feil i data**. 
Det er endringer som innebærer tillegg av nye rader i tabellene `tblfartspes` og `tblfarttid`, eller bare `tblfarttid`. **NB!** Tabellen `tblfartobj` endres aldri!

Velg fartøy i tabellen `tblfartobj`, slik det er beskrevet i '## Søk for visning og/eller endring av detaljer ' nederst i dette dokumentet. I pop-up skjema angis hvilke endringer som ønskes. Dette gjøres ved å angi **en eller flere** av:
	- 'Ombygging' = '1' (Når verdien av 'Ombygging' = 1 betyr at ombygning har skjedd). Nye rader i tabellene `tblfartspes` og `tblfarttid` opprettes. 
	- 'Endre navn' = '1' ('Endre navn' = 1 betyr at raden inneholder data om en navneendring (`Fartnavn`) og/eller Pennantnummer/Tilnavn (`PennantTiln`)). Ny rad i tabellen `tblfarttid` opprettes, `Navning` settes = '1' og raden redigeres.
	- 'Endre eierskap' = '1' ('Endre eierskap' = 1 betyr at raden inneholder data om et eierskifte (`Rederi` og ev. `Nasjon_ID` og `RegHavn`). Ny rad i tabellen `tblfarttid` opprettes, `Eierskifte` settes = '1' og raden redigeres.
	- 'Andre endringer' = '1' ('Andre endringer' = 1 betyr at raden inneholder data om endret nasjon (`Nasjon_ID`), registreringshavn (`RegHavn`) kallesignal (`Kallesignal`), fiskerinr (`Fiskerinr`) og/eller MMSI-nr (`MMSI`)). Ny rad i tabellen `tblfarttid` opprettes, `Annet` settes = '1' og raden redigeres.

NB! Verdien av `Objekt` skal ved alle typer endringer være = '0', siden det ikke er et nybygg!


### Utførelse av endringer Type B

- Felles for alle endringer i løpet av et fartøys liv er at de skal utføres ved at det alltid opprettes en ny rad i tabellen `tblfarttid`. 
Det er kun ved ombygging (valg av 'Ombygging' i pop-up-skjemaet) at også ny rad i tabellen `tblfartspes` opprettes, som detaljert under. 
- Det kan bare være en rad i tabellen `tblfarttid` med samme `FartObj_ID`, `YearTid` og `MndTid`. Alle endringer som følge av en eller flere av følgende verdier er sanne gjøres i den samme raden: `Navning=1`, `Eierskifte=1`, `Annet=1`. Når relevante felt, som beskrevet under, er ferdig oppdatert den nye, oppdaterte raden i tabellen `tblfarttid` lagres.

#### Ombygging = '1'

1. Ny rad opprettes i tabellen `tblfartspes` som er en kopi av raden med høyeste `FartSpes_ID` og samtidig `FartObj_ID` = `FartObj_ID` fra den valgte raden i tabellen `tblfartobj`. Når minimum `YearSpes` og et annet felt er endret i raden, kan den lagres.
2. Ved lagring opprettes en ny rad i tabellen `tblfarttid` som er kopi av den raden med høyest `FartTid_ID` og samtidig at `FartObj_ID` = `tblfartspes.FartObj_ID`. I den nye raden settes `FartObj_ID` = `FartObj_ID` fra tabellen `tblfartobj` og `FartSpes_ID` = `FartSpes_ID` i den nye raden  i tabellen `tblfartspes`, samt `YearTid` = `YearSpes` og `MndTid` = `MndSpes` fra den nye raden i tabellen `tblfartspes`.
3. Den nye raden i tabellen `tblfarttid` kan også benyttes når samtidig 'Navning'=1, 'Eierskifte'=1, og eller 'Annet'=1, hvis disse var valgt i pop-up-skjemaet. 

#### Felles for 'Endre navn' = 1, 'Endre eierskap' = 1, 'Andre endringer' = 1

En ny rad i tabellen `tblfarttid` opprettes. Det er er kopi av den raden med høyest `FartTid_ID` og samtidig at `FartObj_ID` = `tblfartobj.FartObj_ID`. I den nye raden settes `Navning=1`, `Eierskifte=1` og/eller `Annet=1`, avhengig av valgt endringstype. `FartObj_ID` = `FartObj_ID` fra tabellen `tblfartobj`.

#### Spesielt når 'Endre navn' = 1

I nyopprettet rad i tabellen `tblfarttid` , endres `FartNavn` og `Navning=1`, samt `YearTid` og `MndTid`. 

#### Spesielt når 'Endre eierskap' = 1

I nyopprettet rad i tabellen `tblfarttid`, endres minimum feltet `Rederi` og `Eierskifte=1`, samt `YearTid` og `MndTid`. I tillegg kan et eller flere av feltene `Nasjon_ID` (`Nasjon_ID` fra tabellen `tblzNasjon`) og `RegHavn` endres. 

#### Spesielt når 'Andre endringer' = 1

I nyopprettet rad i tabellen `tblfarttid`, endres minimum et av feltene `Nasjon_ID` (`Nasjon_ID` fra tabellen `tblzNasjon`), `RegHavn`, `Kallesignal`, `Fiskerinr` eller `MMSI`, samt `Annet=1`, `YearTid` og `MndTid`. 

### Fartøyets 'død' — strøket fra register

Når et fartøy utgår av offentlige registere, registreres dette i `tblfartobj`:
- `StroketYear` — året fartøyet ble strøket
- `StroketID` (`Stroket_ID` fra tabellen `tblzstroket`)— årsaken (f.eks. hugget, forlist, solgt til utlandet)

Disse feltene representerer livsløpets endepunkt i databasen. Et strøket fartøy beholdes i databasen med all historikk intakt.

### Sletting av rader

- En `tblfartspes`-rad kan **ikke** slettes uten at tidslinjereferansene først er fjernet. Ved sletting informer om kaskadesletting av tidslinjen.
- En `tblfarttid`-rad kan **alltid** slettes hvis den ikke er referert i indirekte via `tblxFartLink` (URL-koblinger) som refererer `FartTid_ID`. Fjern referansene først eller informer om kaskadesletting av tidslinjen og tilhørende `tblx`-tabeller.
- Et `tblfartobj` kan **ikke** slettes hvis det finnes rader i `tblfartspes` eller `tblfarttid` med samme `FartObj_ID`. Fjern referansene først eller informer om kaskadesletting av alle underordnede rader.

## Søk for visning og/eller endring av detaljer 

Når en har søkt på feltet `FartNavn` og åpner en funnet rad, skal alltid raden fra `tblfartobj` åpnes med alle tilhørende rader fra tabellene `tblfartspes` og `tblfarttid`.
