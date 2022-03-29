# ************************************************************
# Sequel Ace SQL dump
# Version 20016
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# Host: localhost (MySQL 5.5.5-10.5.8-MariaDB)
# Database: itu
# Generation Time: 2021-11-16 16:17:08 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0 */;
/*!40101 SET @OLD_SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO', SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES = @@SQL_NOTES, SQL_NOTES = 0 */;


# Dump of table user_types
# ------------------------------------------------------------

DROP TABLE IF EXISTS `user_types`;

CREATE TABLE `user_types`
(
	`id_user_type` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`name`         varchar(100) DEFAULT NULL,
	PRIMARY KEY (`id_user_type`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

LOCK TABLES `user_types` WRITE;
/*!40000 ALTER TABLE `user_types`
	DISABLE KEYS */;

INSERT INTO `user_types` (`id_user_type`, `name`)
VALUES
	(1, 'Admin');

/*!40000 ALTER TABLE `user_types`
	ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users`
(
	`id_user`      int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`id_user_type` int(11) UNSIGNED NOT NULL,
	`email`        varchar(50)  DEFAULT NULL,
	`password`     varchar(100) DEFAULT NULL,
	PRIMARY KEY (`id_user`),
	KEY `id_user_type` (`id_user_type`),
	CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_user_type`) REFERENCES `user_types` (`id_user_type`) ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;



/*!40111 SET SQL_NOTES = @OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE = @OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;
