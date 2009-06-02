-- MySQL dump 10.11
--
-- Host: localhost    Database: topos_4
-- ------------------------------------------------------
-- Server version	5.0.45-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Logs`
--

DROP TABLE IF EXISTS `Logs`;
CREATE TABLE `Logs` (
  `transactionId` bigint(20) unsigned NOT NULL,
  `logEntry` longtext NOT NULL,
  KEY `transactionId` (`transactionId`),
  FULLTEXT KEY `logEntry` (`logEntry`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii;

--
-- Dumping data for table `Logs`
--

LOCK TABLES `Logs` WRITE;
/*!40000 ALTER TABLE `Logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `Logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Pools`
--

DROP TABLE IF EXISTS `Pools`;
CREATE TABLE `Pools` (
  `poolId` bigint(20) unsigned NOT NULL auto_increment,
  `poolName` varchar(255) NOT NULL,
  PRIMARY KEY  (`poolId`),
  UNIQUE KEY `poolName` (`poolName`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=ascii;

--
-- Dumping data for table `Pools`
--

LOCK TABLES `Pools` WRITE;
/*!40000 ALTER TABLE `Pools` DISABLE KEYS */;
INSERT INTO `Pools` VALUES (1,'pieterb'),(2,'pieterc');
/*!40000 ALTER TABLE `Pools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Tokens`
--

DROP TABLE IF EXISTS `Tokens`;
CREATE TABLE `Tokens` (
  `tokenId` bigint(20) unsigned NOT NULL auto_increment,
  `tokenValue` longblob NOT NULL,
  `tokenType` varchar(255) NOT NULL default 'application/octet-stream',
  `poolId` bigint(20) unsigned NOT NULL,
  `tokenLeases` int(10) unsigned NOT NULL default '0',
  `tokenLockTimeout` bigint(20) NOT NULL default '0',
  `tokenLockUUID` varchar(36) default NULL,
  `tokenCreated` bigint(20) NOT NULL,
  `tokenLength` bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (`tokenId`),
  UNIQUE KEY `tokenLockUUID` (`tokenLockUUID`),
  KEY `poolId` (`poolId`),
  KEY `tokenLeases` (`tokenLeases`),
  KEY `tokenLockTimeout` (`tokenLockTimeout`),
  KEY `tokenCreated` (`tokenCreated`),
  KEY `tokenLength` (`tokenLength`),
  CONSTRAINT `pool_constraint` FOREIGN KEY (`poolId`) REFERENCES `Pools` (`poolId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

--
-- Dumping data for table `Tokens`
--

LOCK TABLES `Tokens` WRITE;
/*!40000 ALTER TABLE `Tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `Tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Transactions`
--

DROP TABLE IF EXISTS `Transactions`;
CREATE TABLE `Transactions` (
  `transactionId` bigint(20) unsigned NOT NULL auto_increment,
  `transactionAddress` varchar(15) character set ascii NOT NULL,
  `transactionTimestamp` bigint(20) NOT NULL,
  `transactionMethod` varchar(20) character set ascii NOT NULL,
  `transactionURL` text character set ascii NOT NULL,
  PRIMARY KEY  (`transactionId`),
  KEY `transactionTimestamp` USING BTREE (`transactionTimestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=388118 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `Transactions`
--

LOCK TABLES `Transactions` WRITE;
/*!40000 ALTER TABLE `Transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `Transactions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2009-06-02 14:46:55
