-- MySQL dump 10.11
--
-- Host: localhost    Database: topos_4_1
-- ------------------------------------------------------
-- Server version	5.0.77-log

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
-- Table structure for table `EmptyTokens`
--

DROP TABLE IF EXISTS `EmptyTokens`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `EmptyTokens` (
  `tokenId` bigint(20) NOT NULL,
  `setAt` bigint(20) NOT NULL,
  `wasInsert` tinyint(1) NOT NULL default '1',
  `wasNull` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=ascii;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Matches`
--

DROP TABLE IF EXISTS `Matches`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Matches` (
  `regexpId` bigint(20) unsigned NOT NULL,
  `tokenId` bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (`regexpId`,`tokenId`),
  KEY `tokenId` (`tokenId`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Pools`
--

DROP TABLE IF EXISTS `Pools`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Pools` (
  `poolId` bigint(20) unsigned NOT NULL auto_increment,
  `poolName` varchar(255) NOT NULL,
  `minLeases` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`poolId`),
  UNIQUE KEY `poolName` (`poolName`),
  UNIQUE KEY `poolId` (`poolId`,`minLeases`),
  KEY `minLeases` (`minLeases`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=ascii;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `RegExp`
--

DROP TABLE IF EXISTS `RegExp`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `RegExp` (
  `poolId` bigint(20) unsigned NOT NULL,
  `regexpValue` text NOT NULL,
  `regexpHash` char(32) character set ascii NOT NULL,
  `regexpId` bigint(20) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (`regexpId`),
  UNIQUE KEY `poolId` (`poolId`,`regexpHash`),
  KEY `regexpValue` (`regexpValue`(255)),
  KEY `regexpHash` (`regexpHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `TokenValues`
--

DROP TABLE IF EXISTS `TokenValues`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `TokenValues` (
  `tokenId` bigint(20) unsigned NOT NULL auto_increment,
  `tokenValue` longblob NOT NULL,
  PRIMARY KEY  (`tokenId`)
) ENGINE=MyISAM AUTO_INCREMENT=10291067 DEFAULT CHARSET=ascii;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Tokens`
--

DROP TABLE IF EXISTS `Tokens`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Tokens` (
  `tokenId` bigint(20) unsigned NOT NULL,
  `tokenType` varchar(255) character set ascii NOT NULL default 'application/octet-stream',
  `poolId` bigint(20) unsigned NOT NULL,
  `tokenLeases` int(10) unsigned NOT NULL default '0',
  `tokenLockTimeout` bigint(20) NOT NULL default '0',
  `tokenLockUUID` varchar(36) default NULL,
  `tokenCreated` bigint(20) NOT NULL,
  `tokenLength` bigint(20) unsigned NOT NULL,
  `tokenName` text,
  `tokenLockDescription` text,
  PRIMARY KEY  (`tokenId`),
  UNIQUE KEY `tokenLockUUID` (`tokenLockUUID`),
  KEY `tokenLockTimeout` (`tokenLockTimeout`),
  KEY `tokenCreated` (`tokenCreated`),
  KEY `tokenLength` (`tokenLength`),
  KEY `tokenLeases` (`tokenLeases`),
  KEY `poolId_2` (`poolId`,`tokenLeases`),
  FULLTEXT KEY `tokenName` (`tokenName`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Tokens_bak`
--

DROP TABLE IF EXISTS `Tokens_bak`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Tokens_bak` (
  `tokenId` bigint(20) unsigned NOT NULL,
  `tokenType` varchar(255) NOT NULL default 'application/octet-stream',
  `poolId` bigint(20) unsigned NOT NULL,
  `tokenLeases` int(10) unsigned NOT NULL default '0',
  `tokenLockTimeout` bigint(20) NOT NULL default '0',
  `tokenLockUUID` varchar(36) default NULL,
  `tokenCreated` bigint(20) NOT NULL,
  `tokenLength` bigint(20) unsigned NOT NULL,
  `tokenName` text character set utf8 NOT NULL,
  `tokenLockDescription` text character set utf8 NOT NULL,
  PRIMARY KEY  (`tokenId`),
  UNIQUE KEY `tokenLockUUID` (`tokenLockUUID`),
  KEY `tokenLockTimeout` (`tokenLockTimeout`),
  KEY `tokenCreated` (`tokenCreated`),
  KEY `tokenLength` (`tokenLength`),
  KEY `tokenLeases` (`tokenLeases`),
  KEY `poolId_2` (`poolId`,`tokenLeases`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'topos_4_1'
--
DELIMITER ;;
/*!50003 DROP FUNCTION IF EXISTS `getPoolId` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`topos`@`localhost`*/ /*!50003 FUNCTION `getPoolId`(
  inPoolName  VARCHAR(255) CHARACTER SET ASCII
) RETURNS bigint(20) unsigned
    DETERMINISTIC
BEGIN
  DECLARE varPoolId INT UNSIGNED DEFAULT NULL;
  INSERT IGNORE INTO `Pools` (`poolName`) VALUES (inPoolName);
  SELECT LAST_INSERT_ID() INTO varPoolId;
  IF varPoolId = 0 THEN
    SELECT `poolId` INTO varPoolId FROM `Pools` WHERE `poolName` = inPoolName;
  END IF;
  RETURN varPoolId;
END */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
/*!50003 DROP PROCEDURE IF EXISTS `createTokens` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`topos`@`localhost`*/ /*!50003 PROCEDURE `createTokens`(
  IN inPoolName  VARCHAR(255) CHARACTER SET ASCII,
  IN inTokens INT UNSIGNED,
  IN inOffset INT
)
BEGIN
  DECLARE varCounter INT DEFAULT inOffset;
  DECLARE varPoolId INT UNSIGNED DEFAULT NULL;
  SELECT getPoolId(inPoolName) INTO varPoolId;
  
  SET inTokens = inTokens + inOffset;
  SET foreign_key_checks = 0;
  SET unique_checks = 0;
  WHILE varCounter < inTokens DO
    INSERT INTO `TokenValues` (
      `tokenValue`
    ) VALUES (
      varCounter
    );
    INSERT INTO `Tokens` (
      `tokenId`, `poolId`, `tokenType`, `tokenCreated`, `tokenLength`
    ) VALUES (
      LAST_INSERT_ID(), varPoolId, 'text/plain', UNIX_TIMESTAMP(), LENGTH(varCounter)
    );
    SET varCounter = varCounter + 1;
  END WHILE;
  SET unique_checks=1;
  SET foreign_key_checks=1;
END */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
DELIMITER ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-02-28 11:14:25
