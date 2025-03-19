<?php
session_start([
    'cookie_path' => '/', // Сессия доступна на всём сайте
    'cookie_lifetime' => 86400, // 24 часа
]);

require_once __DIR__ . '/core/app.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
route($uri);
?>