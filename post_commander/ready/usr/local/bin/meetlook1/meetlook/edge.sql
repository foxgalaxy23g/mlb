CREATE TABLE `applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `icon` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('1', 'Terminal', 'xterm', 'icons/terminal.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('2', 'Files', 'nautilus', 'icons/files.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('3', 'Firefox', 'firefox', 'icons/browser.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('4', 'SQL Edit', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url http://127.0.0.1:7777/apps/base.php', 'icons/sql.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('5', 'Time', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url http://127.0.0.1:7777/apps/time.php', 'icons/time.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('6', 'Calculator', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url http://127.0.0.1:7777/apps/calc.php', 'icons/calc.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('7', 'Settings', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url http://127.0.0.1:7777/apps/settings/settings.php', 'icons/settings.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('8', 'YouTube', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url https://www.youtube.com/', 'icons/yt.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('9', 'Google Docs', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url https://www.docs.google.com/', 'icons/docs.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('10', 'Gmail', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url https://www.mail.google.com/', 'icons/gmail.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('11', 'X', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url https://www.x.com/', 'icons/x.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('12', 'Discord', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url https://www.discord.com/', 'icons/ds.png');
INSERT INTO `applications` (`id`, `name`, `path`, `icon`) VALUES ('13', 'Telegram', 'python3 /usr/local/bin/meetlook1/shellweb.pyw --url https://web.telegram.org/', 'icons/tg.png');

CREATE TABLE `pinned_apps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `app_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `app_id` (`app_id`),
  CONSTRAINT `pinned_apps_ibfk_1` FOREIGN KEY (`app_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pinned_apps` (`id`, `app_id`) VALUES ('1', '3');
INSERT INTO `pinned_apps` (`id`, `app_id`) VALUES ('2', '1');
INSERT INTO `pinned_apps` (`id`, `app_id`) VALUES ('3', '2');
INSERT INTO `pinned_apps` (`id`, `app_id`) VALUES ('4', '7');


CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `wallpaper` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'assets/icons/user.png',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `wallpaper`, `avatar`) VALUES ('1', 'User', '', 'assets/wallpaper/2.jpg', 'assets/icons/user.png');


