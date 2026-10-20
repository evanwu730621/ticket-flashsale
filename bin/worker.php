#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\RedisClient;
use App\Services\OrderService;
use App\Services\TicketService;

set_time_limit(0);

$running = true;

// 如果運行在支援信號的環境（如 Linux/Docker）啟用優雅關機
if (extension_loaded('pcntl')) {
    pcntl_async_signals(true);
    $shutdown = function () use (&$running) {
        echo "\n[Worker] 收到退出信號，準備停止工作...\n";
        $running = false;
    };
    pcntl_signal(SIGTERM, $shutdown);
    pcntl_signal(SIGINT, $shutdown);
}

echo "====================================================\n";
echo " [CLI Worker] 啟動中...\n";
echo " 監聽 Redis 佇列: [" . TicketService::QUEUE_NAME . "]\n";
echo " (按 Ctrl+C 可停止 Worker)\n";
echo "====================================================\n";

$redis = RedisClient::getConnection();
$orderService = new OrderService();

while ($running) {
    try {
        // brPop 阻塞 2 秒監聽，避免 busy-loop 燒乾 CPU
        $result = $redis->brPop([TicketService::QUEUE_NAME], 2);

        if (empty($result)) {
            continue;
        }

        // ext-redis 回傳 [隊列名, 內容]；Predis 回傳 [隊列名, 內容]
        $payloadRaw = $result[1] ?? null;
        if (!$payloadRaw) {
            continue;
        }

        $payload = json_decode($payloadRaw, true);
        if (!$payload) {
            continue;
        }

        $orderSn  = (string)$payload['order_sn'];
        $ticketId = (int)$payload['ticket_id'];
        $userId   = (int)$payload['user_id'];

        echo date('[Y-m-d H:i:s]') . " 正在落庫訂單: {$orderSn} (User: {$userId}, Ticket: {$ticketId}) ... ";

        $success = $orderService->createOrder($orderSn, $ticketId, $userId);

        if ($success) {
            echo "[成功寫入 MySQL]\n";
        } else {
            echo "[落庫失敗 - 庫存不足或重複下單]\n";
        }
    } catch (\Throwable $e) {
        echo "\n[Worker Error] " . $e->getMessage() . "\n";
        sleep(1);
    }
}

echo "[Worker] 已安全關閉。\n";