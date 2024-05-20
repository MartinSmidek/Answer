
USE ezer_db2;
DROP TABLE IF EXISTS `ds_db`;
CREATE TABLE `ds_db` (
  `ds_osoba` int(11) NOT NULL COMMENT 'ds_osoba.id_osoba',
  `id_osoba` int(11) NOT NULL COMMENT 'osoba.id_osoba',
  `prijmeni` tinytext COLLATE utf8_czech_ci NOT NULL,
  `jmeno` tinytext COLLATE utf8_czech_ci NOT NULL,
  `narozeni` date NOT NULL,
  KEY `id_osoba` (`id_osoba`),
  KEY `ds_osoba` (`ds_osoba`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
ALTER TABLE `ds_db` ADD `id_spolu` int(11) NOT NULL COMMENT 'kvůli pokoji' AFTER `id_osoba`;
ALTER TABLE `akce` ADD `ds_info` text COLLATE 'utf8_czech_ci' NOT NULL COMMENT 'json informace k objednávce' AFTER `archiv`;
ALTER TABLE `akce` ADD `id_order` int(11) NOT NULL COMMENT 'ID objednávky Domu setkání' AFTER `id_hlavni`;
ALTER TABLE `akce` ADD INDEX `id_order` (`id_order`);
ALTER TABLE `spolu` ADD `ds_vzorec` text NOT NULL COMMENT 'parametry ceny pro Dům setkání' AFTER `p_kc_strava`;
ALTER TABLE `spolu` ADD `pokoj` tinytext NOT NULL COMMENT 'ubytování: pokoj' AFTER `dite_kat`;
DROP TABLE IF EXISTS `faktura`;
CREATE TABLE `faktura` (
  `id_faktura` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `rok` int(11) NOT NULL COMMENT 'rok objednávky',
  `num` int(11) NOT NULL COMMENT 'pořadové číslo v roce',
  `typ` tinyint(4) NOT NULL COMMENT '0: konečná, 1: zálohová',
  `id_order` int(11) DEFAULT NULL COMMENT 'objednávka',
  `id_pobyt` int(11) DEFAULT NULL COMMENT 'pobyt',
  `castka` int(11) NOT NULL COMMENT 'celková částka s DPH',
  `parm_json` text COLLATE utf8_czech_ci NOT NULL COMMENT 'úplný seznam parametrů faktury',
  `html` text COLLATE utf8_czech_ci NOT NULL COMMENT 'text faktury pro náhled',
  PRIMARY KEY (`id_faktura`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci COMMENT='Vystavené faktury pro Dům setkání';

DROP VIEW IF EXISTS `objednavka`;
CREATE TABLE `objednavka` (`id_order` int(11) unsigned, `id_akce` int(11), `DS2024` text, `kod_akce` smallint(6), `adults` tinyint(4) unsigned, `kids_10_15` tinyint(4) unsigned, `kids_3_9` tinyint(4) unsigned, `kids_3` tinyint(4) unsigned, `board` tinyint(4) unsigned, `note` text, `jmeno` tinytext, `prijmeni` varchar(80), `ulice` tinytext, `psc` varchar(10), `obec` varchar(50), `telefon` varchar(20), `email` varchar(80), `org` tinytext, `ic` int(11), `dic` varchar(20), `od` date, `do` date);
DROP TABLE IF EXISTS `objednavka`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `objednavka` AS select `setkani4`.`tx_gnalberice_order`.`uid` AS `id_order`,`setkani4`.`tx_gnalberice_order`.`id_akce` AS `id_akce`,`setkani4`.`tx_gnalberice_order`.`DS2024` AS `DS2024`,`setkani4`.`tx_gnalberice_order`.`akce` AS `kod_akce`,`setkani4`.`tx_gnalberice_order`.`adults` AS `adults`,`setkani4`.`tx_gnalberice_order`.`kids_10_15` AS `kids_10_15`,`setkani4`.`tx_gnalberice_order`.`kids_3_9` AS `kids_3_9`,`setkani4`.`tx_gnalberice_order`.`kids_3` AS `kids_3`,`setkani4`.`tx_gnalberice_order`.`board` AS `board`,`setkani4`.`tx_gnalberice_order`.`note` AS `note`,`setkani4`.`tx_gnalberice_order`.`firstname` AS `jmeno`,`setkani4`.`tx_gnalberice_order`.`name` AS `prijmeni`,`setkani4`.`tx_gnalberice_order`.`address` AS `ulice`,`setkani4`.`tx_gnalberice_order`.`zip` AS `psc`,`setkani4`.`tx_gnalberice_order`.`city` AS `obec`,`setkani4`.`tx_gnalberice_order`.`telephone` AS `telefon`,`setkani4`.`tx_gnalberice_order`.`email` AS `email`,`setkani4`.`tx_gnalberice_order`.`org` AS `org`,`setkani4`.`tx_gnalberice_order`.`ic` AS `ic`,`setkani4`.`tx_gnalberice_order`.`dic` AS `dic`,cast(from_unixtime(`setkani4`.`tx_gnalberice_order`.`fromday`) as date) AS `od`,cast(from_unixtime(`setkani4`.`tx_gnalberice_order`.`untilday`) as date) AS `do` from `setkani4`.`tx_gnalberice_order`;

-- ----------------------------------

USE setkani4;
UPDATE `ds_cena` SET `od` = '3', `do` = '99' WHERE `id_cena` = '241';
ALTER TABLE `tx_gnalberice_order`  ADD `DS2024` text NOT NULL COMMENT 'JSON stav transformace do akce+pobyt+spolu+osoba';
ALTER TABLE `ds_osoba` ADD INDEX `id_order` (`id_order`), ADD INDEX `rodina` (`rodina`(3));
ALTER TABLE `tx_gnalberice_order` CHANGE `akce` `akce` smallint(6) NOT NULL DEFAULT '0' COMMENT 'kód akce' AFTER `state`,
ADD `id_akce` int NOT NULL COMMENT 'ID akce' AFTER `akce`;
ALTER TABLE `tx_gnalberice_order`
CHANGE `state` `state` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '#ds_stav' AFTER `confirmed`,
CHANGE `board` `board` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '#ds_strava' AFTER `kids_3`,
CHANGE `note` `note` text COLLATE 'cp1250_czech_cs' NOT NULL DEFAULT '' COMMENT 'název akce a poznámky' AFTER `id_akce`;
ALTER TABLE `ds_cena`
CHANGE `id_cena` `id_cena` int(11) NOT NULL COMMENT 'ID' AUTO_INCREMENT FIRST,
CHANGE `rok` `rok` smallint(6) NOT NULL DEFAULT '0' COMMENT 'rok platnosti ceníku' AFTER `id_cena`,
CHANGE `polozka` `polozka` tinytext COLLATE 'utf8_czech_ci' NOT NULL COMMENT 'popis' AFTER `rok`,
CHANGE `druh` `druh` varchar(24) COLLATE 'cp1250_czech_cs' NOT NULL DEFAULT '' COMMENT 'kategorie' AFTER `polozka`,
ADD `druh_abbr` varchar(24) COLLATE 'cp1250_czech_cs' NOT NULL COMMENT 'kategorie pro program' AFTER `druh`,
CHANGE `typ` `typ` varchar(16) COLLATE 'cp1250_czech_cs' NOT NULL DEFAULT '' COMMENT 'keyword pro program' AFTER `druh_abbr`,
CHANGE `od` `od` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'od stáří včetně' AFTER `typ`,
CHANGE `do` `do` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'do stáří nevčetně' AFTER `od`,
CHANGE `cena` `cena` int(11) NOT NULL DEFAULT '0' COMMENT 'cena za položku' AFTER `do`,
ADD `dotovana` int(11) NOT NULL COMMENT 'dotovaná cena' AFTER `cena`,
CHANGE `dph` `dph` smallint(6) NOT NULL DEFAULT '0' COMMENT 'DPH' AFTER `dotovana`,
COMMENT='Ceník Domu setkání';