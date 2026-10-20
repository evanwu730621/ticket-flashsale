<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Controllers\TicketController;

// 1. 關鍵修正：若訪問首頁根目錄，直接輸出 index.html 畫面
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === '/' || $uri === '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

// 允許跨域請求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$router = new Router();

// 2. 註冊搶票 API
$router->add('POST', '/api/ticket/buy', [TicketController::class, 'flashSale']);

// 3. 註冊查詢訂單狀態 API (供前端輪詢)
$router->add('GET', '/api/ticket/order', [TicketController::class, 'checkOrder']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);