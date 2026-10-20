<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\RedisClient;

class TicketService
{
    public const QUEUE_NAME = 'ticket_order_queue';

    /**
     * Lua 腳本：
     * KEYS[1]: 庫存 Key (ticket:stock:{ticketId})
     * KEYS[2]: 使用者已購集合 Key (ticket:users:{ticketId})
     * ARGV[1]: 使用者 ID
     * 
     * 回傳：
     *  1: 搶購成功 (庫存扣 1，加入名單)
     * -1: 該使用者已搶購過
     *  0: 票已售罄
     */
    private string $luaScript = <<<LUA
        if redis.call('sismember', KEYS[2], ARGV[1]) == 1 then
            return -1
        end
        local stock = tonumber(redis.call('get', KEYS[1]))
        if (not stock) or (stock <= 0) then
            return 0
        end
        redis.call('decr', KEYS[1])
        redis.call('sadd', KEYS[2], ARGV[1])
        return 1
    LUA;

    public function purchase(int $ticketId, int $userId): array
    {
        $redis = RedisClient::getConnection();
        $stockKey = "ticket:stock:{$ticketId}";
        $userKey  = "ticket:users:{$ticketId}";

        // 相容 ext-redis 與 predis 不同的 eval 傳參方式
        if ($redis instanceof \Redis) {
            $result = $redis->eval($this->luaScript, [$stockKey, $userKey, (string)$userId], 2);
        } else {
            $result = $redis->eval($this->luaScript, 2, $stockKey, $userKey, (string)$userId);
        }

        return match ((int)$result) {
            1 => $this->enqueueOrder($ticketId, $userId),
            -1 => [
                'status'  => false,
                'code'    => 400,
                'message' => '您已經購買過此票券，每人限購一張！'
            ],
            0 => [
                'status'  => false,
                'code'    => 410,
                'message' => '很遺憾，票券已全數售罄！'
            ],
            default => [
                'status'  => false,
                'code'    => 500,
                'message' => '系統繁忙，請稍後重試'
            ]
        };
    }

    private function enqueueOrder(int $ticketId, int $userId): array
    {
        $redis = RedisClient::getConnection();
        $orderSn = 'ORD' . date('YmdHis') . bin2hex(random_bytes(3));

        $payload = json_encode([
            'order_sn'   => $orderSn,
            'ticket_id'  => $ticketId,
            'user_id'    => $userId,
            'created_at' => microtime(true)
        ]);

        // 推入佇列 (Producer)
        $redis->lPush(self::QUEUE_NAME, $payload);

        return [
            'status'   => true,
            'code'     => 202,
            'message'  => '搶購成功，訂單排隊寫入中',
            'order_sn' => $orderSn
        ];
    }
}