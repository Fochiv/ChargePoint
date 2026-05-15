-- ============================================================
-- ChargePoint — Schema MySQL complet
-- Hébergeur : Aeronfree / cPanel avec PHPMyAdmin
-- Base de données : mseet_41930214_chargepoint
-- Importez directement ce fichier dans PHPMyAdmin sans modification
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- TABLE : users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`                    INT             NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(255)    NOT NULL,
    `email`                 VARCHAR(255)    NOT NULL,
    `phone`                 VARCHAR(50)     NOT NULL,
    `password`              VARCHAR(255)    NOT NULL,
    `referral_code`         VARCHAR(50)     NOT NULL,
    `referred_by`           INT             DEFAULT NULL,
    `balance`               DECIMAL(15,2)   DEFAULT 0,
    `total_earnings`        DECIMAL(15,2)   DEFAULT 0,
    `referral_earnings`     DECIMAL(15,2)   DEFAULT 0,
    `has_deposit`           TINYINT(1)      DEFAULT 0,
    `wallet_name`           VARCHAR(255)    DEFAULT NULL,
    `wallet_country`        VARCHAR(100)    DEFAULT NULL,
    `wallet_method`         VARCHAR(100)    DEFAULT NULL,
    `wallet_phone`          VARCHAR(50)     DEFAULT NULL,
    `wallet_updated_at`     DATETIME        DEFAULT NULL,
    `status`                VARCHAR(20)     DEFAULT 'active',
    `failed_login_attempts` INT             DEFAULT 0,
    `locked_until`          DATETIME        DEFAULT NULL,
    `last_login`            DATETIME        DEFAULT NULL,
    `created_at`            DATETIME        DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),
    UNIQUE KEY `uq_phone` (`phone`),
    UNIQUE KEY `uq_referral_code` (`referral_code`),
    KEY `idx_referred_by` (`referred_by`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : vip_plans
-- ============================================================
CREATE TABLE IF NOT EXISTS `vip_plans` (
    `id`            INT             NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(100)    NOT NULL,
    `amount`        DECIMAL(15,2)   NOT NULL,
    `daily_gain`    DECIMAL(15,2)   NOT NULL,
    `total_gain`    DECIMAL(15,2)   NOT NULL,
    `duration_days` INT             NOT NULL DEFAULT 125,
    `is_active`     TINYINT(1)      DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : investments
-- ============================================================
CREATE TABLE IF NOT EXISTS `investments` (
    `id`             INT             NOT NULL AUTO_INCREMENT,
    `user_id`        INT             NOT NULL,
    `plan_id`        INT             NOT NULL,
    `amount`         DECIMAL(15,2)   NOT NULL,
    `daily_gain`     DECIMAL(15,2)   NOT NULL,
    `days_total`     INT             NOT NULL,
    `days_remaining` INT             NOT NULL,
    `status`         VARCHAR(20)     DEFAULT 'active',
    `started_at`     DATETIME        DEFAULT CURRENT_TIMESTAMP,
    `completed_at`   DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_inv_user_id` (`user_id`),
    KEY `idx_inv_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : transactions
-- ============================================================
CREATE TABLE IF NOT EXISTS `transactions` (
    `id`                     INT             NOT NULL AUTO_INCREMENT,
    `user_id`                INT             NOT NULL,
    `type`                   VARCHAR(50)     NOT NULL,
    `description`            TEXT            DEFAULT NULL,
    `amount`                 DECIMAL(15,2)   NOT NULL,
    `status`                 VARCHAR(20)     DEFAULT 'pending',
    `reference`              VARCHAR(100)    DEFAULT NULL,
    `ashtech_transaction_id` VARCHAR(100)    DEFAULT NULL,
    `method`                 VARCHAR(100)    DEFAULT NULL,
    `phone`                  VARCHAR(50)     DEFAULT NULL,
    `country_code`           VARCHAR(10)     DEFAULT NULL,
    `operator`               VARCHAR(100)    DEFAULT NULL,
    `plan_id`                INT             DEFAULT NULL,
    `reject_reason`          TEXT            DEFAULT NULL,
    `is_admin_deposit`       TINYINT(1)      DEFAULT 0,
    `created_at`             DATETIME        DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tx_user_id`  (`user_id`),
    KEY `idx_tx_type`     (`type`),
    KEY `idx_tx_status`   (`status`),
    KEY `idx_tx_reference`(`reference`),
    KEY `idx_tx_ashtech`  (`ashtech_transaction_id`),
    KEY `idx_tx_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `user_id`    INT          NOT NULL,
    `message`    TEXT         NOT NULL,
    `is_read`    TINYINT(1)   DEFAULT 0,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user_id` (`user_id`),
    KEY `idx_notif_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
    `key`   VARCHAR(100) NOT NULL,
    `value` TEXT         NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : admin_users
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(100) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNEES INITIALES : Plans VIP
-- ============================================================
INSERT INTO `vip_plans` (`id`, `name`, `amount`, `daily_gain`, `total_gain`, `duration_days`, `is_active`) VALUES
    (1,  'VIP 1',  3000,   315,    39375,    125, 1),
    (2,  'VIP 2',  7000,   770,    96250,    125, 1),
    (3,  'VIP 3',  15000,  1800,   225000,   125, 1),
    (4,  'VIP 4',  25000,  3125,   390625,   125, 1),
    (5,  'VIP 5',  45000,  5850,   731250,   125, 1),
    (6,  'VIP 6',  70000,  9450,   1181250,  125, 1),
    (7,  'VIP 7',  115000, 16100,  2012500,  125, 1),
    (8,  'VIP 8',  170000, 24650,  3081250,  125, 1),
    (9,  'VIP 9',  250000, 48750,  6093750,  125, 1),
    (10, 'VIP 10', 400000, 78000,  9750000,  125, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================================
-- DONNEES INITIALES : Parametres plateforme
-- ============================================================
INSERT INTO `settings` (`key`, `value`) VALUES
    ('referral_level1',  '20'),
    ('referral_level2',  '5'),
    ('referral_level3',  '2'),
    ('min_withdrawal',   '1200'),
    ('max_withdrawal',   '5000000'),
    ('withdrawal_fee',   '15'),
    ('maintenance_mode', '0')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ============================================================
-- DONNEES INITIALES : Administrateur
-- Identifiants : Ben10 / 1214161820@Ben
-- ============================================================
INSERT INTO `admin_users` (`username`, `password`) VALUES
    ('Ben10', '$2y$10$9Afmk2D8HX23gxP3iZrXm.jCMe9MWrN.OVYn/GzztTS1PLUQ/dXUC')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DU SCHEMA
-- ============================================================
