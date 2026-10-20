<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\TicketService;

class TicketController
{
    public function flashSale(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?? $_POST;

        $ticketId = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;
        $userId   = isset($input['user_id']) ? (int)$input['user_id'] : 0;

        if ($ticketId <= 0 || $userId <= 0) {
            http_response_code(422);
            echo json_encode(['status' => false, 'message' => '參數不合法 (缺少 ticket_id 或 user_id)']);
            return;
        }

        $service = new TicketService();
        $result = $service->purchase($ticketId, $userId);

        http_response_code($result['code']);
        echo json_encode($result);
    }

    public function checkOrder(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $orderSn = $_GET['order_sn'] ?? '';

        if (!$orderSn) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => '缺少 order_sn 參數']);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT order_sn, ticket_id, user_id, status, created_at FROM `orders` WHERE `order_sn` = :sn LIMIT 1");
        $stmt->execute([':sn' => $orderSn]);
        $order = $stmt->fetch();

        if ($order) {
            echo json_encode(['status' => 'completed', 'data' => $order]);
        } else {
            echo json_encode(['status' => 'processing', 'message' => '訂單排隊處理中，請稍後查詢']);
        }
    }
}