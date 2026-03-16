-- ============================================================
-- OasisResearchCommunity - Production Database Schema
-- Run this SQL on your cPanel MySQL database via phpMyAdmin
-- or cPanel's MySQL Database Wizard before first deployment.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_ref`          VARCHAR(64)  NOT NULL UNIQUE,
    `email`             VARCHAR(255) NOT NULL UNIQUE,
    `password`          VARCHAR(255) NOT NULL,
    `role`              ENUM('admin','editor','member') NOT NULL DEFAULT 'member',
    `verified`          TINYINT(1)  NOT NULL DEFAULT 0,
    `verification_code` VARCHAR(16)  DEFAULT NULL,
    `verified_at`       DATETIME    DEFAULT NULL,
    `login_attempts`    INT          NOT NULL DEFAULT 0,
    `locked_until`      DATETIME    DEFAULT NULL,
    `created_at`        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 2. profiles
-- ----------------------------
CREATE TABLE IF NOT EXISTS `profiles` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_ref`      VARCHAR(64)  NOT NULL UNIQUE,
    `first_name`    VARCHAR(100) DEFAULT NULL,
    `last_name`     VARCHAR(100) DEFAULT NULL,
    `avatar`        VARCHAR(500) DEFAULT NULL,
    `bio`           TEXT         DEFAULT NULL,
    `created_at`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_profiles_user_ref` FOREIGN KEY (`user_ref`) REFERENCES `users`(`user_ref`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 3. blog_categories
-- ----------------------------
CREATE TABLE IF NOT EXISTS `blog_categories` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `categoryRef`   VARCHAR(32)  NOT NULL UNIQUE,
    `name`          VARCHAR(100) NOT NULL UNIQUE,
    `slug`          VARCHAR(120) NOT NULL UNIQUE,
    `description`   TEXT         DEFAULT NULL,
    `created_at`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 4. blog_posts
-- ----------------------------
CREATE TABLE IF NOT EXISTS `blog_posts` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `postRef`           VARCHAR(64)   NOT NULL UNIQUE,
    `title`             VARCHAR(500)  NOT NULL,
    `slug`              VARCHAR(520)  NOT NULL UNIQUE,
    `excerpt`           TEXT          DEFAULT NULL,
    `content`           LONGTEXT      DEFAULT NULL,
    `meta_title`        VARCHAR(255)  DEFAULT NULL,
    `canonical_url`     VARCHAR(500)  DEFAULT NULL,
    `meta_description`  TEXT          DEFAULT NULL,
    `og_title`          VARCHAR(255)  DEFAULT NULL,
    `og_description`    TEXT          DEFAULT NULL,
    `og_image`          VARCHAR(500)  DEFAULT NULL,
    `reading_time`      INT           DEFAULT 0,
    `status`            ENUM('Draft','Published','Scheduled','Archived') NOT NULL DEFAULT 'Draft',
    `author`            VARCHAR(100)  NOT NULL,
    `category`          VARCHAR(100)  NOT NULL,
    `tags`              TEXT          DEFAULT NULL,
    `visibility`        ENUM('public','private') NOT NULL DEFAULT 'public',
    `allow_comments`    TINYINT(1)    NOT NULL DEFAULT 1,
    `is_featured`       TINYINT(1)    NOT NULL DEFAULT 0,
    `is_editors_choice` TINYINT(1)    NOT NULL DEFAULT 0,
    `is_popular`        TINYINT(1)    NOT NULL DEFAULT 0,
    `content_type`      ENUM('text','video','audio') NOT NULL DEFAULT 'text',
    `publish_date`      DATETIME      DEFAULT NULL,
    `featured_image`    VARCHAR(500)  DEFAULT NULL,
    `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status`            (`status`),
    INDEX `idx_category`          (`category`),
    INDEX `idx_is_featured`       (`is_featured`),
    INDEX `idx_is_editors_choice` (`is_editors_choice`),
    INDEX `idx_is_popular`        (`is_popular`),
    INDEX `idx_publish_date`      (`publish_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 5. blog_comments
-- ----------------------------
CREATE TABLE IF NOT EXISTS `blog_comments` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `post_id`       INT UNSIGNED NOT NULL,
    `parent_id`     INT UNSIGNED DEFAULT NULL,
    `author_name`   VARCHAR(100) NOT NULL,
    `author_email`  VARCHAR(255) NOT NULL,
    `content`       TEXT         NOT NULL,
    `status`        ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `created_at`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_comments_post`   FOREIGN KEY (`post_id`)   REFERENCES `blog_posts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `blog_comments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 6. featured_members
-- ----------------------------
CREATE TABLE IF NOT EXISTS `featured_members` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `memberRef`       VARCHAR(64)  NOT NULL UNIQUE,
    `avatar`          VARCHAR(500) DEFAULT NULL,
    `title`           VARCHAR(50)  DEFAULT NULL,
    `first_name`      VARCHAR(100) NOT NULL,
    `middle_name`     VARCHAR(100) DEFAULT NULL,
    `last_name`       VARCHAR(100) NOT NULL,
    `position`        VARCHAR(200) NOT NULL,
    `speciality`      VARCHAR(200) DEFAULT NULL,
    `job_description` TEXT         NOT NULL,
    `created_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
