/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `connections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_id` varchar(50) NOT NULL,
  `app_id` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL,
  `last_activity_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events_to_send` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `date` datetime NOT NULL,
  `expiration_date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //
CREATE EVENT `ev_daily_cleaning` ON SCHEDULE EVERY 1 DAY STARTS '2024-06-21 00:00:00' ON COMPLETION PRESERVE ENABLE DO BEGIN
	DELETE FROM connections WHERE last_activity_at<DATE_SUB(NOW(), INTERVAL 6 MONTH);
	DELETE FROM events_to_send WHERE expiration_date<DATE_SUB(NOW(), INTERVAL 6 MONTH);
	DELETE FROM push_subscriptions WHERE date<DATE_SUB(NOW(), INTERVAL 6 MONTH);
	DELETE FROM sec_connection_attempts WHERE date<DATE_SUB(NOW(), INTERVAL 2 MONTH);
	DELETE FROM sec_query_complexity_usage;
	DELETE FROM sec_users_query_complexity_usage;
	DELETE FROM sec_wrong_sids WHERE date<DATE_SUB(NOW(), INTERVAL 7 DAY);
END//
DELIMITER ;

DELIMITER //
CREATE EVENT `ev_hourly_cleaning` ON SCHEDULE EVERY 1 HOUR STARTS '2024-06-21 00:00:00' ON COMPLETION PRESERVE ENABLE DO BEGIN
	DELETE FROM sec_total_requests;
	DELETE FROM sec_users_total_requests;
END//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `oauth_access_tokens` (
  `client_id` varchar(100) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `granted_scope` varchar(1000) NOT NULL,
  `scope` varchar(1000) NOT NULL,
  `token_type` varchar(50) NOT NULL,
  `refresh_token` varchar(100) NOT NULL,
  `access_token` varchar(100) NOT NULL,
  `expiration_date` datetime NOT NULL,
  `associated_code` varchar(200) NOT NULL,
  PRIMARY KEY (`client_id`,`user_id`),
  UNIQUE KEY `uq_access_token` (`access_token`),
  UNIQUE KEY `uq_refresh_token` (`refresh_token`),
  KEY `fk_oauth_access_tokens__userId` (`user_id`),
  CONSTRAINT `fk_oauth_access_tokens__clientId` FOREIGN KEY (`client_id`) REFERENCES `oauth_clients` (`client_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_access_tokens__userId` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oauth_clients` (
  `user_id` bigint(20) unsigned NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_type` enum('confidential','public') NOT NULL,
  `client_secret` varchar(150) NOT NULL,
  `website` varchar(200) DEFAULT NULL,
  `description` varchar(750) DEFAULT NULL,
  `logo` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`client_id`),
  UNIQUE KEY `uq_client_id` (`client_id`) USING BTREE,
  CONSTRAINT `fk_oauth_clients__userId` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oauth_clients_redirect_uris` (
  `user_id` bigint(20) unsigned NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `number` int(11) unsigned NOT NULL,
  `redirect_uri` varchar(2000) NOT NULL,
  PRIMARY KEY (`client_id`,`number`,`user_id`) USING BTREE,
  KEY `fk_oauth_clients_redirect_uris__id` (`user_id`,`client_id`),
  CONSTRAINT `fk_oauth_clients_redirect_uris__id` FOREIGN KEY (`user_id`, `client_id`) REFERENCES `oauth_clients` (`user_id`, `client_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `url_check` CHECK (`redirect_uri` regexp '^https://(?:[a-zA-Z0-9-]+.)+[a-zA-Z]{2,}(?:/[^s]*)?$' = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //
CREATE PROCEDURE `proc_sec_wrong_sids_autobans_fullcheck`()
BEGIN
	DECLARE sReason VARCHAR(100) DEFAULT 'too many wrong sids';
	DECLARE res CURSOR FOR (
		SELECT * FROM sec_wrong_sids AS t GROUP BY remote_address
		HAVING COUNT(*) >= 10 AND (SELECT COUNT(*) FROM sec_ip_bans WHERE remote_address=t.remote_address AND reason=sReason)=0
	);
		
	FOR row IN res DO BEGIN
		INSERT INTO sec_ip_bans (remote_address,date,reason) VALUES(row.remote_address,NOW(),sReason);
	END;
	END FOR;
END//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `user_id` bigint(20) unsigned NOT NULL,
  `remote_public_key` varchar(500) NOT NULL,
  `date` datetime NOT NULL,
  `endpoint` varchar(8000) NOT NULL,
  `auth_token` varchar(200) NOT NULL,
  `expiration_time` double DEFAULT NULL,
  `user_visible_only` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`remote_public_key`,`date`) USING BTREE,
  CONSTRAINT `fk_push_subscriptions__id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sec_connection_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `app_id` varchar(500) NOT NULL,
  `remote_address` varchar(45) NOT NULL,
  `date` datetime NOT NULL,
  `successful` tinyint(1) unsigned NOT NULL,
  `error_type` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_connections__userId` (`user_id`),
  CONSTRAINT `fk_connections__userId` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sec_ip_bans` (
  `remote_address` varchar(75) NOT NULL,
  `date` datetime NOT NULL,
  `reason` varchar(75) NOT NULL,
  KEY `idx_remote_address` (`reason`,`date`,`remote_address`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sec_query_complexity_usage` (
  `remote_address` varchar(75) NOT NULL,
  `complexity_used` int(11) unsigned NOT NULL,
  PRIMARY KEY (`remote_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sec_total_requests` (
  `remote_address` varchar(75) NOT NULL,
  `count` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`remote_address`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `sec_users_bans` (
  `user_id` int(10) unsigned NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `reason` mediumtext NOT NULL,
  PRIMARY KEY (`user_id`,`start_date`,`end_date`),
  KEY `fk_user_bans__id` (`user_id`),
  CONSTRAINT `fk_user_bans__id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sec_users_query_complexity_usage` (
  `user_id` bigint(20) unsigned NOT NULL,
  `complexity_used` int(11) unsigned NOT NULL,
  PRIMARY KEY (`user_id`) USING BTREE,
  CONSTRAINT `fk_sec_users_query_complexity_usage_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `sec_users_total_requests` (
  `user_id` bigint(20) unsigned NOT NULL,
  `count` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`) USING BTREE,
  CONSTRAINT `fk_sec_users_total_requests__id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sec_wrong_sids` (
  `remote_address` varchar(75) NOT NULL,
  `date` datetime NOT NULL,
  `session_id` text NOT NULL,
  UNIQUE KEY `uq_sid` (`remote_address`,`session_id`(100)),
  KEY `idx_remote_address` (`session_id`(100),`date`,`remote_address`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `roles` set('Administrator') NOT NULL DEFAULT '',
  `name` varchar(50) NOT NULL,
  `password` varchar(80) NOT NULL,
  `registration_date` datetime NOT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_object(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_name` (`name`),
  CONSTRAINT `settings` CHECK (json_valid(`settings`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `trig_sec_wrong_sids_autoban_one` AFTER INSERT ON `sec_wrong_sids` FOR EACH ROW BEGIN
	DECLARE sReason VARCHAR(100) DEFAULT 'too many wrong sids';
	DECLARE res INT;
	SET res = (SELECT COUNT(*) FROM sec_wrong_sids WHERE remote_address=NEW.remote_address);
	
	IF (res >= 10 AND (SELECT COUNT(*) FROM sec_ip_bans WHERE remote_address=NEW.remote_address AND reason=sReason)=0) THEN BEGIN
		INSERT INTO sec_ip_bans (remote_address,date,reason) VALUES(NEW.remote_address,NOW(),sReason);
	END;
	END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;