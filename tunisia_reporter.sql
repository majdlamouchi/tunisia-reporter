SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `tunisia_reporter`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `tunisia_reporter`;

DROP TABLE IF EXISTS `report_images`;
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `report_votes`;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `problem_types`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `full_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('user','admin','moderator') NOT NULL DEFAULT 'user',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `problem_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `title` VARCHAR(100) DEFAULT NULL,
  `emoji` VARCHAR(10) DEFAULT NULL,
  `color` VARCHAR(20) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_problem_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `problem_type_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `description` TEXT NOT NULL,
  `severity` ENUM('critical','high','medium','low') NOT NULL,
  `status` ENUM('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  `votes_up` INT UNSIGNED NOT NULL DEFAULT 0,
  `votes_down` INT UNSIGNED NOT NULL DEFAULT 0,
  `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_problem_type_id` (`problem_type_id`),
  KEY `idx_reports_user_id` (`user_id`),
  CONSTRAINT `fk_reports_problem_type` FOREIGN KEY (`problem_type_id`) REFERENCES `problem_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `comment` TEXT NOT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comments_report_id` (`report_id`),
  CONSTRAINT `fk_comments_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `report_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  `alt_text` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_images_report_id` (`report_id`),
  CONSTRAINT `fk_report_images_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `report_votes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `vote_type` ENUM('up','down') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_votes_report_id` (`report_id`),
  CONSTRAINT `fk_report_votes_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_report_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `problem_types` (`id`, `name`, `title`, `emoji`, `color`) VALUES
(1, 'fire', 'Fire', '🔥', '#ff4444'),
(2, 'radar', 'Speed Camera', '📡', '#ff8800'),
(3, 'construction', 'Construction', '🚧', '#ff8800'),
(4, 'pollution', 'Pollution', '🏭', '#4444ff'),
(5, 'accident', 'Accident', '🚗', '#44ff44'),
(6, 'flooding', 'Flooding', '🌊', '#4444ff'),
(7, 'pothole', 'Pothole', '🕳️', '#44ff44'),
(8, 'other', 'Other', '❓', '#888888');

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `password_hash`, `role`) VALUES
(1, 'admin', 'System Administrator', 'admin@tunisiareporter.com', '$2y$10$fzc2PAY0ypae.6c.PiHUZuJTMa9Ois5Y79G9BCeTBzVJi53FseU5q', 'admin'),
(2, 'aymen', 'Aymen Ben Salah', 'aymen@example.com', '$2y$10$fzc2PAY0ypae.6c.PiHUZuJTMa9Ois5Y79G9BCeTBzVJi53FseU5q', 'user'),
(3, 'sarra', 'Sarra Trabelsi', 'sarra@example.com', '$2y$10$fzc2PAY0ypae.6c.PiHUZuJTMa9Ois5Y79G9BCeTBzVJi53FseU5q', 'user');

INSERT INTO `reports` (`id`, `problem_type_id`, `user_id`, `latitude`, `longitude`, `address`, `description`, `severity`, `status`, `votes_up`, `votes_down`, `view_count`, `created_at`, `updated_at`) VALUES
(1, 7, 2, 36.8065000, 10.1815000, 'Avenue Habib Bourguiba, Tunis, Tunisia', 'Large pothole in the road causing danger for drivers.', 'high', 'pending', 12, 1, 25, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY),
(2, 4, 3, 34.7406000, 10.7603000, 'Industrial Zone, Sfax, Tunisia', 'Heavy smoke and pollution near factories.', 'critical', 'in_progress', 20, 2, 54, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 12 HOUR),
(3, 6, 2, 35.6781000, 10.0963000, 'Kairouan, Tunisia', 'Flooding on the road after rain.', 'medium', 'resolved', 8, 0, 21, NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 3 DAY);

INSERT INTO `comments` (`report_id`, `user_id`, `comment`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, 3, 'Still not fixed.', 0, NOW() - INTERVAL 20 HOUR, NOW() - INTERVAL 20 HOUR),
(2, 2, 'Needs urgent inspection.', 0, NOW() - INTERVAL 10 HOUR, NOW() - INTERVAL 10 HOUR);

INSERT INTO `report_images` (`report_id`, `image_url`, `alt_text`) VALUES
(1, 'uploads/reports/pothole1.jpg', 'Pothole image'),
(2, 'uploads/reports/pollution1.jpg', 'Pollution image');

COMMIT;
