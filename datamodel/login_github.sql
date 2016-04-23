CREATE TABLE `login_github` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `github_username` varchar(255) NOT NULL,
  `oauth_token` varchar(255) NOT NULL,
  `scope` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_UNIQUE` (`user_id`),
  UNIQUE KEY `github_username_UNIQUE` (`github_username`),
  UNIQUE KEY `oauth_token_UNIQUE` (`oauth_token`),
  CONSTRAINT `login_github_fk_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
