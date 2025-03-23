<?php require_once __DIR__ . "/../core/app.php"; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Новый пароль (Админ) - <?php echo htmlspecialchars(get_setting('site_name')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/admin/assets/css/style.css" rel="stylesheet"> <!-- Подключите стили админ-панели, если есть -->
</head>
<body>
    <div class="container mt-5">
        <h3 class="text-center mb-4">Введите новый пароль (Админ)</h3>
        <?php handle_reset_password_form(true); // Вызов функции для обработки POST и вывода сообщений ?>
        <form method="post">
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Новый пароль" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password_confirm" class="form-control" placeholder="Подтвердите пароль" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Сохранить</button>
        </form>
    </div>
</body>
</html>