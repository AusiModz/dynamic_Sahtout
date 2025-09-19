
CREATE TABLE IF NOT EXISTS `failed_logins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `attempts` int DEFAULT '0',
  `last_attempt` int NOT NULL,
  `block_until` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_last_attempt` (`last_attempt`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf16;

CREATE TABLE IF NOT EXISTS `reset_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `last_attempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `blocked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf16;

