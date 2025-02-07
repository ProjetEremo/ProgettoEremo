# Host: localhost  (Version 5.5.5-10.4.32-MariaDB)
# Date: 2025-02-07 12:16:31
# Generator: MySQL-Front 6.1  (Build 1.26)


#
# Structure for table "attori"
#

DROP TABLE IF EXISTS `attori`;
CREATE TABLE `attori` (
  `IDAttore` int(11) NOT NULL,
  `Nome` varchar(50) NOT NULL,
  `Cognome` varchar(50) NOT NULL,
  `NumeroDiTelefono` varchar(50) DEFAULT NULL,
  `Mail` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`IDAttore`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "attori"
#


#
# Structure for table "categorieeventi"
#

DROP TABLE IF EXISTS `categorieeventi`;
CREATE TABLE `categorieeventi` (
  `IDCategoria` int(11) NOT NULL,
  `Tipologia` varchar(50) NOT NULL,
  PRIMARY KEY (`IDCategoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "categorieeventi"
#


#
# Structure for table "eventi"
#

DROP TABLE IF EXISTS `eventi`;
CREATE TABLE `eventi` (
  `IDEvento` int(11) NOT NULL,
  `Durata` varchar(40) DEFAULT NULL,
  `Data` date DEFAULT NULL,
  `Titolo` varchar(50) NOT NULL,
  `Descrizione` text DEFAULT NULL,
  `Associazione` varchar(50) DEFAULT NULL,
  `FlagPrenotabile` tinyint(1) NOT NULL DEFAULT 1,
  `PostiDisponibili` int(11) DEFAULT NULL,
  `Costo` int(11) DEFAULT NULL,
  `IDCategoria` int(11) DEFAULT NULL,
  PRIMARY KEY (`IDEvento`),
  KEY `IDCategoria` (`IDCategoria`),
  CONSTRAINT `eventi_ibfk_1` FOREIGN KEY (`IDCategoria`) REFERENCES `categorieeventi` (`IDCategoria`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "eventi"
#


#
# Structure for table "attorieventi"
#

DROP TABLE IF EXISTS `attorieventi`;
CREATE TABLE `attorieventi` (
  `IDEvento` int(11) NOT NULL,
  `IDAttore` int(11) NOT NULL,
  PRIMARY KEY (`IDEvento`,`IDAttore`),
  KEY `IDAttore` (`IDAttore`),
  CONSTRAINT `attorieventi_ibfk_1` FOREIGN KEY (`IDEvento`) REFERENCES `eventi` (`IDEvento`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `attorieventi_ibfk_2` FOREIGN KEY (`IDAttore`) REFERENCES `attori` (`IDAttore`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "attorieventi"
#


#
# Structure for table "media"
#

DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
  `Progressivo` int(11) NOT NULL,
  `Percorso` varchar(200) NOT NULL,
  `IDEvento` int(11) NOT NULL,
  PRIMARY KEY (`Progressivo`),
  KEY `IDEvento` (`IDEvento`),
  CONSTRAINT `media_ibfk_1` FOREIGN KEY (`IDEvento`) REFERENCES `eventi` (`IDEvento`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "media"
#


#
# Structure for table "utentiregistrati"
#

DROP TABLE IF EXISTS `utentiregistrati`;
CREATE TABLE `utentiregistrati` (
  `Contatto` varchar(50) NOT NULL,
  `Nome` varchar(50) NOT NULL,
  `Cognome` varchar(50) NOT NULL,
  `Password` varchar(50) NOT NULL,
  `IsAdmin` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Contatto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "utentiregistrati"
#


#
# Structure for table "utentiincoda"
#

DROP TABLE IF EXISTS `utentiincoda`;
CREATE TABLE `utentiincoda` (
  `Contatto` varchar(50) NOT NULL,
  `IDEvento` int(11) NOT NULL,
  `NumeroInCoda` int(11) NOT NULL,
  PRIMARY KEY (`Contatto`,`IDEvento`),
  KEY `IDEvento` (`IDEvento`),
  CONSTRAINT `utentiincoda_ibfk_1` FOREIGN KEY (`Contatto`) REFERENCES `utentiregistrati` (`Contatto`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `utentiincoda_ibfk_2` FOREIGN KEY (`IDEvento`) REFERENCES `eventi` (`IDEvento`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "utentiincoda"
#


#
# Structure for table "prenotazioni"
#

DROP TABLE IF EXISTS `prenotazioni`;
CREATE TABLE `prenotazioni` (
  `Progressivo` int(11) NOT NULL,
  `NumeroPosti` int(11) NOT NULL,
  `Contatto` varchar(50) NOT NULL,
  `IDEvento` int(11) NOT NULL,
  PRIMARY KEY (`Progressivo`),
  KEY `Contatto` (`Contatto`),
  KEY `IDEvento` (`IDEvento`),
  CONSTRAINT `prenotazioni_ibfk_1` FOREIGN KEY (`Contatto`) REFERENCES `utentiregistrati` (`Contatto`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `prenotazioni_ibfk_2` FOREIGN KEY (`IDEvento`) REFERENCES `eventi` (`IDEvento`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "prenotazioni"
#


#
# Structure for table "commenti"
#

DROP TABLE IF EXISTS `commenti`;
CREATE TABLE `commenti` (
  `Progressivo` int(11) NOT NULL,
  `Descrizione` text NOT NULL,
  `Data` date NOT NULL,
  `CodRisposta` int(11) DEFAULT NULL,
  `Contatto` varchar(50) NOT NULL,
  `IDEvento` int(11) NOT NULL,
  PRIMARY KEY (`Progressivo`),
  KEY `Contatto` (`Contatto`),
  KEY `IDEvento` (`IDEvento`),
  CONSTRAINT `commenti_ibfk_1` FOREIGN KEY (`Contatto`) REFERENCES `utentiregistrati` (`Contatto`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `commenti_ibfk_2` FOREIGN KEY (`IDEvento`) REFERENCES `eventi` (`IDEvento`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

#
# Data for table "commenti"
#

