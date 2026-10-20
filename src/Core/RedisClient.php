<?php
declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;

class RedisClient
{
    private static mixed $instance = null;

    public static function getConnection(): mixed
    {
        if (self::$instance === null) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->safeLoad();

            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $auth = $_ENV['REDIS_PASSWORD'] ?? null;

            // 1. 若環境有安裝 ext-redis 原生擴充則優先使用
            if (extension_loaded('redis')) {
                $redis = new \Redis();
                $connected = $redis->pconnect($host, $port, 2.5);
                if (!$connected) {
                    throw new \RuntimeException("Redis 連線失敗: 無法連線至 {$host}:{$port}");
                }
                if ($auth && $auth !== 'null' && $auth !== '') {
                    $redis->auth($auth);
                }
                self::$instance = $redis;
            } elseif (class_exists(\Predis\Client::class)) {
                // 2. 若無 C 擴充，自動切換為 Predis 純 PHP 套件
                $parameters = [
                    'scheme' => 'tcp',
                    'host'   => $host,
                    'port'   => $port,
                ];
                if ($auth && $auth !== 'null' && $auth !== '') {
                    $parameters['password'] = $auth;
                }
                self::$instance = new \Predis\Client($parameters);
            } else {
                throw new \RuntimeException("找不到 Redis 驅動，請確認 composer 已安裝 predis/predis");
            }
        }
        return self::$instance;
    }
}