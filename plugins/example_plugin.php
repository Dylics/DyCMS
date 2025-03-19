<?php
// Проверяем, что плагин загружается через ядро
if (!defined('SITE_NAME')) {
    die("Прямой доступ к плагину запрещён");
}

// Функция плагина: добавляет приветственное сообщение перед контентом
function plugin_hello() {
    $uri = isset($_GET['url']) ? trim($_GET['url'], '/') : 'home';
    if ($uri === 'home') {
        echo '<div class="alert alert-success text-center mb-4" role="alert">
            Привет от плагина! Добро пожаловать на главную страницу!
        </div>';
    }
}

// Регистрируем хук для выполнения перед контентом
add_action('before_content', 'plugin_hello');
?>