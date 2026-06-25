# Feltbredder i tabeller og utskrifter
## TABLE `tblfartobj`
	`FartObj_ID` width=6 ch
	`NavnObj` width=48 ch
	`FartType_ID` width=6 ch
	`IMO` width=12 ch 
	`Kontrahert` width=12 ch
	`Kjolstrukket` width=12 ch
	`Sjosatt` width=12 ch
	`Levert` width=12 ch
	`Bygget` width=6 ch
	`LeverID` width=6 ch
	`ByggeNr` width=12 ch
	`SkrogID` width=6 ch
	`BnrSkrog` width=12 ch
	`StroketYear` width=6 ch
	`StroketID` width=6 ch
	`Historikk` width= max ch
	`ObjNotater` width=max ch
	`IngenData` checkbox

## TABLE `tblfartspes`
	`FartSpes_ID` width=6 ch
	`FartObj_ID` width=6 ch
	`YearSpes` width=6 ch
	`MndSpes` width=3 ch
	`Verft_ID` width=6 ch
	`Byggenr` width=12 ch
	`Materiale` width=24 ch
	`FartMat_ID` width=6 ch
	`FartType_ID` width=6 ch
	`FartFunk_ID` width=6 ch
	`FartSkrog_ID` width=6 ch
	`FartDrift_ID` width=6 ch
	`FunkDetalj` width=60 ch
	`TeknDetalj` width=60 ch
	`FartKlasse_ID` width=6 ch
	`Kapasitet` width=120 ch
	`Rigg` width=24 ch
	`FartRigg_ID` width=6 ch
	`FartMotor_ID` width=6 ch
	`MotorDetalj` width=60 ch
	`MotorEff` width=12 ch
	`MaxFart` width=6 ch
	`Lengde` width=6 ch
	`Bredde` width=6 ch
	`Dypg` width=6 ch
	`Tonnasje` width=12 ch
	`TonnEnh_ID` width=6 ch
	`Drektigh` width=12 ch
	`DrektEnh_ID` width=6 ch
	`Objekt` checkbox
  
## TABLE `tblfarttid`
	`FartTid_ID` width=6 ch
	`YearTid` width=6 ch
	`MndTid` width=3 ch
	`FartObj_ID` width=6 ch
	`FartSpes_ID` width=6 ch
	`FartNavn` width=48 ch
	`FartType_ID` width=6 ch
	`PennantTiln` width=24 ch
	`Objekt` checkbox
	`Rederi` width=255 ch
	`Nasjon_ID` width=6 ch
	`RegHavn` width=60 ch
	`MMSI` width=12 ch
	`Kallesignal` width=18 ch
	`Fiskerinr` width=18 ch
	`Navning` checkbox
	`Eierskifte` checkbox
	`Annet` checkbox
  
## TABLE `tblverft`
	`Verft_ID` width=6 ch
	`VerftNavn` width=255 ch
	`Sted` width=48 ch
	`Nasjon_ID` width=6 ch
	`TidlID` width=6 ch
	`Etablert` width=6 ch
	`Nedlagt` width=6 ch
	`EtterID` width=6 ch
	`Merknad` width=255 ch
  
## TABLE `tblxfartlink`
	`FartLk_ID` width=6 ch
	`FartTid_ID` 
	`LinkType_ID` width=6 ch
	`LinkType` width=48 ch
	`LinkInnh` width=48 ch
	`Link` width=255 ch
	`SerNo` width=6 ch
  
## TABLE `tblxnmmfoto`
	`ID` width=6 ch
	`FartTid_ID` width=6 ch
	`Bilde_Fil` width=48 ch
	`URL_Bane` width=255 ch
	`PrimusNavn` width=24 ch
	`Motiv` width=255 ch
	`Fotograf` width=60 ch
	`FotoFirma` width=60 ch
	`Samling` width=120 ch
	`FotoTid` width=12 ch
	`FotoSted` width=60 ch
	`SvartFarge` width=24 ch
	`Referanse` width=24 ch
	`TekstFoto` width=255 ch
	`FriKopi` checkbox
  
## TABLE `tblxverftlink` 
	`VerftLk_ID` width=6 ch
	`Verft_ID` width=6 ch
	`LinkType_ID` width=6 ch
	`LinkType` width=48 ch
	`LinkInnh` width=48 ch
	`Link` width=255 ch
  
## TABLE `tblzdrektenh` 
	`DrektEnh_ID` width=6 ch
	`DrektFork` width=6 ch
	`DrektDetalj` width=48 ch
  
## TABLE `tblzfartdrift`
	`FartDrift_ID` width=6 ch
	`DriftMiddel` width=60 ch

## TABLE `tblzfartfunk` 
	`FartFunk_ID` width=6 ch
	`TypeFunksjon` width=60 ch
	`FunkDet` checkbox
  
## TABLE `tblzfartklasse` 
	`FartKlasse_ID` width=6 ch
	`KlasseNavn` width=60 ch
	`TypeKlasse` width=60 ch
	`Klasse` checkbox
  
## TABLE `tblzfartmat`
	`FartMat_ID` width=6 ch
	`MatFork` width=18 ch
	`Materiale` width=60 ch 
  
## TABLE `tblzfartmotor` 
	`FartMotor_ID` width=6 ch
	`MotorFork` width=18 ch
	`MotorDetalj` width=120 ch
  
## TABLE `tblzfartrigg` 
	`FartRigg_ID` width=6 ch
	`RiggFork` width=18 ch
	`RiggDetalj` width=120 ch
  
## TABLE `tblzfartskrog` 
	`FartSkrog_ID` width=6 ch
	`TypeSkrog` width=48 ch
  
## TABLE `tblzfarttype`
	`FartType_ID` width=6 ch
	`TypeFork` width=6 ch
	`FartType` width=48 ch
  
## TABLE `tblzlinktype` 
	`LinkType_ID` width=6 ch
	`LinkType` width=48 ch
  
## TABLE `tblznasjon` 
	`Nasjon_ID` width=6 ch
	`Nasjon` width=60 ch
  
## TABLE `tblzstroket` 
	`Stroket_ID` width=6 ch
	`Strok` width=18 ch
	`StrokDetalj` width=60 ch
  
## TABLE `tblztonnenh` 
	`TonnEnh_ID` width=6 ch
	`TonnFork` width=6 ch
	`TonnDetalj` width=48 ch
  
## TABLE `tblzuser`
	`user_id` width=6 ch
	`email` width=120 ch
	`password` width=24 ch
	`role` enum('admin','user') width=12 ch
	`created_at` width=24 ch
	`IsActive` checkbox
	`LastUsed` width=24 ch
  