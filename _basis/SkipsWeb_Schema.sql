-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: 07. Mar, 2026 08:13 AM
-- Tjener-versjon: 8.0.45-cll-lve
-- PHP Version: 8.4.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `skipsweb_skipsdb`
--

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblfartobj`
--

CREATE TABLE `tblfartobj` (
  `FartObj_ID` int NOT NULL,
  `NavnObj` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartType_ID` int DEFAULT '1',
  `IMO` int DEFAULT NULL,
  `Kontrahert` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Kjolstrukket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Sjosatt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Levert` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Bygget` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LeverID` int DEFAULT '1',
  `ByggeNr` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SkrogID` int DEFAULT '1',
  `BnrSkrog` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `StroketYear` smallint DEFAULT NULL,
  `StroketID` int DEFAULT '1',
  `Historikk` text COLLATE utf8mb4_unicode_ci,
  `ObjNotater` mediumtext COLLATE utf8mb4_unicode_ci,
  `IngenData` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblfartspes`
--

CREATE TABLE `tblfartspes` (
  `FartSpes_ID` int NOT NULL,
  `FartObj_ID` int NOT NULL DEFAULT '1',
  `YearSpes` smallint DEFAULT NULL,
  `MndSpes` tinyint DEFAULT NULL,
  `Verft_ID` int DEFAULT '1',
  `Byggenr` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartMat_ID` int DEFAULT '1',
  `FartType_ID` int DEFAULT '1',
  `FartFunk_ID` int DEFAULT '1',
  `FartSkrog_ID` int DEFAULT '1',
  `FartDrift_ID` int DEFAULT '1',
  `FunkDetalj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TeknDetalj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartKlasse_ID` int DEFAULT '1',
  `Kapasitet` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartRigg_ID` int DEFAULT '1',
  `FartMotor_ID` int DEFAULT '1',
  `MotorDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MotorEff` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MaxFart` smallint DEFAULT NULL,
  `Lengde` smallint DEFAULT NULL,
  `Bredde` smallint DEFAULT NULL,
  `Dypg` smallint DEFAULT NULL,
  `Tonnasje` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TonnEnh_ID` int DEFAULT '1',
  `Drektigh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DrektEnh_ID` int DEFAULT '1',
  `Objekt` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblfarttid`
--

CREATE TABLE `tblfarttid` (
  `FartTid_ID` int NOT NULL,
  `YearTid` smallint DEFAULT NULL,
  `MndTid` tinyint DEFAULT NULL,
  `FartObj_ID` int DEFAULT '1',
  `FartSpes_ID` int DEFAULT '1',
  `FartNavn` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FartType_ID` int NOT NULL DEFAULT '1',
  `PennantTiln` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Objekt` tinyint(1) DEFAULT NULL,
  `Rederi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Nasjon_ID` int DEFAULT '1',
  `RegHavn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MMSI` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Kallesignal` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Fiskerinr` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Navning` tinyint(1) DEFAULT NULL,
  `Eierskifte` tinyint(1) DEFAULT NULL,
  `Annet` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblverft`
--

CREATE TABLE `tblverft` (
  `Verft_ID` int NOT NULL,
  `VerftNavn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Sted` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Nasjon_ID` int DEFAULT '1',
  `TidlID` int DEFAULT NULL,
  `Etablert` smallint DEFAULT NULL,
  `Nedlagt` smallint DEFAULT NULL,
  `EtterID` int DEFAULT NULL,
  `Merknad` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblxdigmuseum`
--

CREATE TABLE `tblxdigmuseum` (
  `SerID` int NOT NULL,
  `FartTid_ID` int NOT NULL,
  `DIMUkode` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Motiv` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblxfartlink`
--

CREATE TABLE `tblxfartlink` (
  `FartLk_ID` int NOT NULL,
  `FartTid_ID` int NOT NULL DEFAULT '1',
  `LinkType_ID` int DEFAULT '1',
  `LinkType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LinkInnh` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Link` mediumtext COLLATE utf8mb4_unicode_ci,
  `SerNo` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblxnmmfoto`
--

CREATE TABLE `tblxnmmfoto` (
  `ID` int NOT NULL,
  `FartTid_ID` int NOT NULL,
  `Bilde_Fil` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `URL_Bane` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/assets/img/skip/',
  `PrimusNavn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Motiv` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Fotograf` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FotoFirma` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Samling` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FotoTid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FotoSted` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SvartFarge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Referanse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TekstFoto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FriKopi` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblxverftlink`
--

CREATE TABLE `tblxverftlink` (
  `VerftLk_ID` int NOT NULL,
  `Verft_ID` int NOT NULL DEFAULT '1',
  `LinkType_ID` int DEFAULT '1',
  `LinkType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LinkInnh` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Link` mediumtext COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzdrektenh`
--

CREATE TABLE `tblzdrektenh` (
  `DrektEnh_ID` int NOT NULL,
  `DrektFork` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DrektDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartdrift`
--

CREATE TABLE `tblzfartdrift` (
  `FartDrift_ID` int NOT NULL,
  `DriftMiddel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartfunk`
--

CREATE TABLE `tblzfartfunk` (
  `FartFunk_ID` int NOT NULL,
  `TypeFunksjon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FunkDet` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartklasse`
--

CREATE TABLE `tblzfartklasse` (
  `FartKlasse_ID` int NOT NULL,
  `KlasseNavn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TypeKlasse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Klasse` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartmat`
--

CREATE TABLE `tblzfartmat` (
  `FartMat_ID` int NOT NULL,
  `MatFork` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartmotor`
--

CREATE TABLE `tblzfartmotor` (
  `FartMotor_ID` int NOT NULL,
  `MotorFork` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MotorDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartrigg`
--

CREATE TABLE `tblzfartrigg` (
  `FartRigg_ID` int NOT NULL,
  `RiggFork` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RiggDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfartskrog`
--

CREATE TABLE `tblzfartskrog` (
  `FartSkrog_ID` int NOT NULL,
  `TypeSkrog` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzfarttype`
--

CREATE TABLE `tblzfarttype` (
  `FartType_ID` int NOT NULL,
  `TypeFork` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartType` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzlinktype`
--

CREATE TABLE `tblzlinktype` (
  `LinkType_ID` int NOT NULL,
  `LinkType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblznasjon`
--

CREATE TABLE `tblznasjon` (
  `Nasjon_ID` int NOT NULL,
  `Nasjon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzstroket`
--

CREATE TABLE `tblzstroket` (
  `Stroket_ID` int NOT NULL,
  `Strok` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `StrokDetalj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblztonnenh`
--

CREATE TABLE `tblztonnenh` (
  `TonnEnh_ID` int NOT NULL,
  `TonnFork` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TonnDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `tblzuser`
--

CREATE TABLE `tblzuser` (
  `user_id` int NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `IsActive` tinyint(1) NOT NULL DEFAULT '1',
  `LastUsed` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblfartobj`
--
ALTER TABLE `tblfartobj`
  ADD PRIMARY KEY (`FartObj_ID`),
  ADD KEY `LeverID` (`LeverID`),
  ADD KEY `SkrogID` (`SkrogID`),
  ADD KEY `FartTypeObj` (`FartType_ID`),
  ADD KEY `ObjStrok` (`StroketID`);

--
-- Indexes for table `tblfartspes`
--
ALTER TABLE `tblfartspes`
  ADD PRIMARY KEY (`FartSpes_ID`),
  ADD KEY `FartObj_ID` (`FartObj_ID`),
  ADD KEY `Verft_ID` (`Verft_ID`),
  ADD KEY `FartTypeSpes` (`FartType_ID`),
  ADD KEY `VerftObj` (`Verft_ID`,`FartObj_ID`),
  ADD KEY `TonnEnh_ID` (`TonnEnh_ID`),
  ADD KEY `DrektEnh_ID` (`DrektEnh_ID`),
  ADD KEY `FartMat_ID` (`FartMat_ID`),
  ADD KEY `FartFunk_ID` (`FartFunk_ID`),
  ADD KEY `FartSkrog_ID` (`FartSkrog_ID`),
  ADD KEY `FartDrift_ID` (`FartDrift_ID`),
  ADD KEY `FartRigg_ID` (`FartRigg_ID`),
  ADD KEY `FartMotor_ID` (`FartMotor_ID`),
  ADD KEY `FartKlasse_ID` (`FartKlasse_ID`);

--
-- Indexes for table `tblfarttid`
--
ALTER TABLE `tblfarttid`
  ADD PRIMARY KEY (`FartTid_ID`),
  ADD UNIQUE KEY `ObjYearMnd` (`FartObj_ID`,`YearTid`,`MndTid`),
  ADD KEY `FartObj_ID` (`FartObj_ID`),
  ADD KEY `FartSpes_ID` (`FartSpes_ID`),
  ADD KEY `Nasjon_ID` (`Nasjon_ID`),
  ADD KEY `RederiObj` (`Rederi`,`FartObj_ID`),
  ADD KEY `ObjTidID` (`FartObj_ID`,`FartTid_ID`),
  ADD KEY `ix_FartTid_Navn_Obj` (`Objekt`,`FartObj_ID`) USING BTREE,
  ADD KEY `TidType` (`FartType_ID`);

--
-- Indexes for table `tblverft`
--
ALTER TABLE `tblverft`
  ADD PRIMARY KEY (`Verft_ID`) USING BTREE,
  ADD KEY `Nasjon_ID` (`Nasjon_ID`),
  ADD KEY `VerftSted` (`VerftNavn`,`Sted`);

--
-- Indexes for table `tblxdigmuseum`
--
ALTER TABLE `tblxdigmuseum`
  ADD PRIMARY KEY (`SerID`),
  ADD KEY `FartTid_ID` (`FartTid_ID`);

--
-- Indexes for table `tblxfartlink`
--
ALTER TABLE `tblxfartlink`
  ADD PRIMARY KEY (`FartLk_ID`) USING BTREE,
  ADD KEY `FartTid` (`FartTid_ID`),
  ADD KEY `FLenkeLenkTyp` (`LinkType_ID`);

--
-- Indexes for table `tblxnmmfoto`
--
ALTER TABLE `tblxnmmfoto`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `xNMMTid` (`FartTid_ID`);

--
-- Indexes for table `tblxverftlink`
--
ALTER TABLE `tblxverftlink`
  ADD PRIMARY KEY (`VerftLk_ID`),
  ADD KEY `Verft_ID` (`Verft_ID`),
  ADD KEY `VLenkLenkTyp` (`LinkType_ID`);

--
-- Indexes for table `tblzdrektenh`
--
ALTER TABLE `tblzdrektenh`
  ADD PRIMARY KEY (`DrektEnh_ID`),
  ADD UNIQUE KEY `TonnFork` (`DrektFork`);

--
-- Indexes for table `tblzfartdrift`
--
ALTER TABLE `tblzfartdrift`
  ADD PRIMARY KEY (`FartDrift_ID`);

--
-- Indexes for table `tblzfartfunk`
--
ALTER TABLE `tblzfartfunk`
  ADD PRIMARY KEY (`FartFunk_ID`);

--
-- Indexes for table `tblzfartklasse`
--
ALTER TABLE `tblzfartklasse`
  ADD PRIMARY KEY (`FartKlasse_ID`);

--
-- Indexes for table `tblzfartmat`
--
ALTER TABLE `tblzfartmat`
  ADD PRIMARY KEY (`FartMat_ID`);

--
-- Indexes for table `tblzfartmotor`
--
ALTER TABLE `tblzfartmotor`
  ADD PRIMARY KEY (`FartMotor_ID`);

--
-- Indexes for table `tblzfartrigg`
--
ALTER TABLE `tblzfartrigg`
  ADD PRIMARY KEY (`FartRigg_ID`);

--
-- Indexes for table `tblzfartskrog`
--
ALTER TABLE `tblzfartskrog`
  ADD PRIMARY KEY (`FartSkrog_ID`);

--
-- Indexes for table `tblzfarttype`
--
ALTER TABLE `tblzfarttype`
  ADD PRIMARY KEY (`FartType_ID`);

--
-- Indexes for table `tblzlinktype`
--
ALTER TABLE `tblzlinktype`
  ADD PRIMARY KEY (`LinkType_ID`);

--
-- Indexes for table `tblznasjon`
--
ALTER TABLE `tblznasjon`
  ADD PRIMARY KEY (`Nasjon_ID`);

--
-- Indexes for table `tblzstroket`
--
ALTER TABLE `tblzstroket`
  ADD PRIMARY KEY (`Stroket_ID`);

--
-- Indexes for table `tblztonnenh`
--
ALTER TABLE `tblztonnenh`
  ADD PRIMARY KEY (`TonnEnh_ID`),
  ADD UNIQUE KEY `TonnFork` (`TonnFork`);

--
-- Indexes for table `tblzuser`
--
ALTER TABLE `tblzuser`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblfartobj`
--
ALTER TABLE `tblfartobj`
  MODIFY `FartObj_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblfartspes`
--
ALTER TABLE `tblfartspes`
  MODIFY `FartSpes_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblfarttid`
--
ALTER TABLE `tblfarttid`
  MODIFY `FartTid_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblverft`
--
ALTER TABLE `tblverft`
  MODIFY `Verft_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblxdigmuseum`
--
ALTER TABLE `tblxdigmuseum`
  MODIFY `SerID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblxfartlink`
--
ALTER TABLE `tblxfartlink`
  MODIFY `FartLk_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblxnmmfoto`
--
ALTER TABLE `tblxnmmfoto`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblxverftlink`
--
ALTER TABLE `tblxverftlink`
  MODIFY `VerftLk_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzdrektenh`
--
ALTER TABLE `tblzdrektenh`
  MODIFY `DrektEnh_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartdrift`
--
ALTER TABLE `tblzfartdrift`
  MODIFY `FartDrift_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartfunk`
--
ALTER TABLE `tblzfartfunk`
  MODIFY `FartFunk_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartklasse`
--
ALTER TABLE `tblzfartklasse`
  MODIFY `FartKlasse_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartmat`
--
ALTER TABLE `tblzfartmat`
  MODIFY `FartMat_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartmotor`
--
ALTER TABLE `tblzfartmotor`
  MODIFY `FartMotor_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartrigg`
--
ALTER TABLE `tblzfartrigg`
  MODIFY `FartRigg_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfartskrog`
--
ALTER TABLE `tblzfartskrog`
  MODIFY `FartSkrog_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzfarttype`
--
ALTER TABLE `tblzfarttype`
  MODIFY `FartType_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzlinktype`
--
ALTER TABLE `tblzlinktype`
  MODIFY `LinkType_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblznasjon`
--
ALTER TABLE `tblznasjon`
  MODIFY `Nasjon_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzstroket`
--
ALTER TABLE `tblzstroket`
  MODIFY `Stroket_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblztonnenh`
--
ALTER TABLE `tblztonnenh`
  MODIFY `TonnEnh_ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblzuser`
--
ALTER TABLE `tblzuser`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT;

--
-- Begrensninger for dumpede tabeller
--

--
-- Begrensninger for tabell `tblfartobj`
--
ALTER TABLE `tblfartobj`
  ADD CONSTRAINT `ObjFartType` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`),
  ADD CONSTRAINT `ObjLeverID` FOREIGN KEY (`LeverID`) REFERENCES `tblverft` (`Verft_ID`),
  ADD CONSTRAINT `ObjSkrogID` FOREIGN KEY (`SkrogID`) REFERENCES `tblverft` (`Verft_ID`),
  ADD CONSTRAINT `ObjStrok` FOREIGN KEY (`StroketID`) REFERENCES `tblzstroket` (`Stroket_ID`);

--
-- Begrensninger for tabell `tblfartspes`
--
ALTER TABLE `tblfartspes`
  ADD CONSTRAINT `SpesDrektLink` FOREIGN KEY (`DrektEnh_ID`) REFERENCES `tblzdrektenh` (`DrektEnh_ID`),
  ADD CONSTRAINT `SpesDrift` FOREIGN KEY (`FartDrift_ID`) REFERENCES `tblzfartdrift` (`FartDrift_ID`),
  ADD CONSTRAINT `SpesFartType` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`),
  ADD CONSTRAINT `SpesFunk` FOREIGN KEY (`FartFunk_ID`) REFERENCES `tblzfartfunk` (`FartFunk_ID`),
  ADD CONSTRAINT `SpesKlasse` FOREIGN KEY (`FartKlasse_ID`) REFERENCES `tblzfartklasse` (`FartKlasse_ID`),
  ADD CONSTRAINT `SpesMat` FOREIGN KEY (`FartMat_ID`) REFERENCES `tblzfartmat` (`FartMat_ID`),
  ADD CONSTRAINT `SpesMotor` FOREIGN KEY (`FartMotor_ID`) REFERENCES `tblzfartmotor` (`FartMotor_ID`),
  ADD CONSTRAINT `SpesObj` FOREIGN KEY (`FartObj_ID`) REFERENCES `tblfartobj` (`FartObj_ID`),
  ADD CONSTRAINT `SpesRigg` FOREIGN KEY (`FartRigg_ID`) REFERENCES `tblzfartrigg` (`FartRigg_ID`),
  ADD CONSTRAINT `SpesSkrog` FOREIGN KEY (`FartSkrog_ID`) REFERENCES `tblzfartskrog` (`FartSkrog_ID`),
  ADD CONSTRAINT `SpesTonnLink` FOREIGN KEY (`TonnEnh_ID`) REFERENCES `tblztonnenh` (`TonnEnh_ID`),
  ADD CONSTRAINT `SpesVerft` FOREIGN KEY (`Verft_ID`) REFERENCES `tblverft` (`Verft_ID`);

--
-- Begrensninger for tabell `tblfarttid`
--
ALTER TABLE `tblfarttid`
  ADD CONSTRAINT `TidNasjon` FOREIGN KEY (`Nasjon_ID`) REFERENCES `tblznasjon` (`Nasjon_ID`),
  ADD CONSTRAINT `TidObj` FOREIGN KEY (`FartObj_ID`) REFERENCES `tblfartobj` (`FartObj_ID`),
  ADD CONSTRAINT `TidSpes` FOREIGN KEY (`FartSpes_ID`) REFERENCES `tblfartspes` (`FartSpes_ID`),
  ADD CONSTRAINT `TidType` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`);

--
-- Begrensninger for tabell `tblverft`
--
ALTER TABLE `tblverft`
  ADD CONSTRAINT `VerftNasjon` FOREIGN KEY (`Nasjon_ID`) REFERENCES `tblznasjon` (`Nasjon_ID`);

--
-- Begrensninger for tabell `tblxdigmuseum`
--
ALTER TABLE `tblxdigmuseum`
  ADD CONSTRAINT `tblxdigmuseum_ibfk_1` FOREIGN KEY (`FartTid_ID`) REFERENCES `tblfarttid` (`FartTid_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Begrensninger for tabell `tblxfartlink`
--
ALTER TABLE `tblxfartlink`
  ADD CONSTRAINT `FLenkeLenkTyp` FOREIGN KEY (`LinkType_ID`) REFERENCES `tblzlinktype` (`LinkType_ID`),
  ADD CONSTRAINT `FLenkeTid` FOREIGN KEY (`FartTid_ID`) REFERENCES `tblfarttid` (`FartTid_ID`);

--
-- Begrensninger for tabell `tblxnmmfoto`
--
ALTER TABLE `tblxnmmfoto`
  ADD CONSTRAINT `xNMMTid` FOREIGN KEY (`FartTid_ID`) REFERENCES `tblfarttid` (`FartTid_ID`);

--
-- Begrensninger for tabell `tblxverftlink`
--
ALTER TABLE `tblxverftlink`
  ADD CONSTRAINT `VLenkLenkTyp` FOREIGN KEY (`LinkType_ID`) REFERENCES `tblzlinktype` (`LinkType_ID`),
  ADD CONSTRAINT `VLenkVerft` FOREIGN KEY (`Verft_ID`) REFERENCES `tblverft` (`Verft_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
