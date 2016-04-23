CREATE TABLE `login_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `code` varchar(40) NOT NULL,
  `used` tinyint(1) DEFAULT NULL,
  `expire_at` int(11) DEFAULT NULL,
  `last_used_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_UNIQUE` (`code`),
  KEY `login_tokens_fk_users_idx` (`user_id`),
  CONSTRAINT `login_tokens_fk_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
