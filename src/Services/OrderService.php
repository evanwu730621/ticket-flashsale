<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

class OrderService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function createOrder(string $orderSn, int $ticketId, int $userId): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. 扣減 MySQL 剩餘庫存 (樂觀鎖檢查 remaining_stock > 0)
            $stmtStock = $this->db->prepare(
                "UPDATE `tickets` 
                 SET `remaining_stock` = `remaining_stock` - 1 
                 WHERE `id` = :id AND `remaining_stock` > 0"
            );
            $stmtStock->execute([':id' => $ticketId]);

            if ($stmtStock->rowCount() === 0) {
                $this->db->rollBack();
                return false;
            }

            // 2. 插入訂單記錄
            $stmtOrder = $this->db->prepare(
                "INSERT INTO `orders` (`order_sn`, `ticket_id`, `user_id`, `status`) 
                 VALUES (:sn, :ticket_id, :user_id, 1)"
            );
            $stmtOrder->execute([
                ':sn'        => $orderSn,
                ':ticket_id' => $ticketId,
                ':user_id'   => $userId
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }
}