-- ============================================================
-- OasisResearchCommunity - Production Database Schema
-- Accurate schema dumped directly from local development DB.
--
-- Run in phpMyAdmin → SQL tab, or via CLI:
--   mysql -u <user> -p <dbname> < database/schema.sql
--
-- Then run database/seeds.sql to populate initial data.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 0. settings  (queried by bootstrap.php on every request)
-- ----------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`        bigint NOT NULL AUTO_INCREMENT,
  `type`      varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value`     varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- 1. users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                int NOT NULL AUTO_INCREMENT,
  `user_ref`          varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email`             varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password`          varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified`          tinyint(1) NOT NULL DEFAULT '0',
  `verified_at`       datetime DEFAULT NULL,
  `login_attempts`    int NOT NULL DEFAULT '0',
  `locked_until`      datetime DEFAULT NULL,
  `role`              enum('admin','member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `created_at`        timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `SECONDARY` (`user_ref`),
  UNIQUE KEY `EMAIL` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 2. profiles
-- ----------------------------
DROP TABLE IF EXISTS `profiles`;
CREATE TABLE `profiles` (
  `id`         int NOT NULL AUTO_INCREMENT,
  `user_ref`   varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name`  varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone`      varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dob`        date DEFAULT NULL,
  `address`    text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_ref` (`user_ref`),
  CONSTRAINT `fk_profiles_user_ref` FOREIGN KEY (`user_ref`) REFERENCES `users` (`user_ref`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 3. blog_categories
-- ----------------------------
DROP TABLE IF EXISTS `blog_categories`;
CREATE TABLE `blog_categories` (
  `id`          int NOT NULL AUTO_INCREMENT,
  `categoryRef` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name`        varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug`        varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at`  timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `categoryRef` (`categoryRef`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 4. blog_posts
-- ----------------------------
DROP TABLE IF EXISTS `blog_posts`;
CREATE TABLE `blog_posts` (
  `id`                int NOT NULL AUTO_INCREMENT,
  `postRef`           varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title`             varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug`              varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt`           text COLLATE utf8mb4_unicode_ci,
  `content`           longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_title`        varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_url`     varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description`  text COLLATE utf8mb4_unicode_ci,
  `og_title`          varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_description`    text COLLATE utf8mb4_unicode_ci,
  `reading_time`      int DEFAULT '0',
  `status`            varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `author`            varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category`          varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tags`              text COLLATE utf8mb4_unicode_ci,
  `visibility`        varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'public',
  `allow_comments`    tinyint(1) DEFAULT '1',
  `is_featured`       tinyint(1) DEFAULT '0',
  `is_editors_choice` tinyint(1) DEFAULT '0',
  `is_popular`        tinyint(1) DEFAULT '0',
  `content_type`      enum('text','video') COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `publish_date`      datetime DEFAULT NULL,
  `featured_image`    varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_image`          varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at`        datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `postRef` (`postRef`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_author` (`author`),
  KEY `idx_category` (`category`),
  KEY `idx_is_featured` (`is_featured`),
  KEY `idx_publish_date` (`publish_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 5. blog_comments
-- ----------------------------
DROP TABLE IF EXISTS `blog_comments`;
CREATE TABLE `blog_comments` (
  `id`           int NOT NULL AUTO_INCREMENT,
  `post_id`      int NOT NULL,
  `parent_id`    int DEFAULT NULL,
  `author_name`  varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content`      text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status`       enum('Pending','Approved','Rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `created_at`   datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `blog_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 6. featured_members
-- ----------------------------
DROP TABLE IF EXISTS `featured_members`;
CREATE TABLE `featured_members` (
  `id`              int NOT NULL AUTO_INCREMENT,
  `memberRef`       varchar(36) NOT NULL,
  `avatar`          varchar(100) NOT NULL,
  `title`           varchar(36) NOT NULL,
  `first_name`      varchar(100) NOT NULL,
  `last_name`       varchar(100) NOT NULL,
  `middle_name`     varchar(100) NOT NULL,
  `position`        varchar(100) NOT NULL,
  `speciality`      varchar(100) NOT NULL,
  `job_description` text NOT NULL,
  `created_at`      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `SECONDARY` (`memberRef`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
