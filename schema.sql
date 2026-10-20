CREATE DATABASE IF NOT EXISTS `flash_sale` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `flash_sale`;

-- 1. 票券庫存表
CREATE TABLE IF NOT EXISTS `tickets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL COMMENT '活動名稱',
    `total_stock` INT NOT NULL DEFAULT 0 COMMENT '總庫存量',
    `remaining_stock` INT NOT NULL DEFAULT 0 COMMENT '剩餘庫存量',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. 訂單紀錄表
CREATE TABLE IF NOT EXISTS `orders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_sn` VARCHAR(64) NOT NULL UNIQUE COMMENT '訂單編號',
    `ticket_id` INT UNSIGNED NOT NULL COMMENT '票券ID',
    `user_id` INT UNSIGNED NOT NULL COMMENT '購買者ID',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1: 搶購成功, 2: 取消',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- 關鍵：保證同使用者對同活動只能產生一張訂單
    UNIQUE KEY `uk_ticket_user` (`ticket_id`, `user_id`),
    INDEX `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB;

-- 3. 初始測試資料 (100 張票)
INSERT INTO `tickets` (`id`, `title`, `total_stock`, `remaining_stock`) 
VALUES (1, '2026 搶先體驗演唱會門票', 100, 100)
ON DUPLICATE KEY UPDATE `remaining_stock` = 100;