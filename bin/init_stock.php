#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\RedisClient;
use App\Services\TicketService;

echo "========================================\n";
echo "   系統初始化：建立資料表與預熱快取   \n";
echo "========================================\n";

try {
    $db = Database::getConnection();
    $redis = RedisClient::getConnection();

    // 1. 自動建立 tickets 與 orders 資料表（若不存在）
    echo "[1/3] 正在檢查並建立 MySQL 資料表...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `tickets` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(100) NOT NULL COMMENT '活動名稱',
            `total_stock` INT NOT NULL DEFAULT 0 COMMENT '總庫存量',
            `remaining_stock` INT NOT NULL DEFAULT 0 COMMENT '剩餘庫存量',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS `orders` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `order_sn` VARCHAR(64) NOT NULL UNIQUE COMMENT '訂單編號',
            `ticket_id` INT UNSIGNED NOT NULL COMMENT '票券ID',
            `user_id` INT UNSIGNED NOT NULL COMMENT '購買者ID',
            `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1: 搶購成功, 2: 取消',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_ticket_user` (`ticket_id`, `user_id`),
            INDEX `idx_ticket_id` (`ticket_id`)
        ) ENGINE=InnoDB;
    ");

    // 2. 自動寫入/重設測試票券（100 張票）
    echo "[2/3] 正在寫入預設票券 (100 張)...\n";
    $db->exec("
        INSERT INTO `tickets` (`id`, `title`, `total_stock`, `remaining_stock`) 
        VALUES (1, '2026 搶先體驗演唱會門票', 100, 100)
        ON DUPLICATE KEY UPDATE `total_stock` = 100, `remaining_stock` = 100;
    ");

    // 3. 同步至 Redis 快取並清空舊佇列
    echo "[3/3] 正在同步資料至 Redis 快取...\n";
    $redis->del(TicketService::QUEUE_NAME);

    $stmt = $db->query("SELECT id, title, remaining_stock FROM `tickets`");
    $tickets = $stmt->fetchAll();

    foreach ($tickets as $ticket) {
        $stockKey = "ticket:stock:{$ticket['id']}";
        $userKey  = "ticket:users:{$ticket['id']}";

        // 寫入庫存並清除已購名單
        $redis->set($stockKey, (string)$ticket['remaining_stock']);
        $redis->del($userKey);

        echo " -> 活動 [{$ticket['id']}] {$ticket['title']}: {$ticket['remaining_stock']} 張已載入 Redis。\n";
    }

    echo "========================================\n";
    echo "  初始化與預熱全部完成！\n";
    echo "========================================\n";
} catch (\Throwable $e) {
    echo "\n[錯誤] 執行失敗: " . $e->getMessage() . "\n";
}