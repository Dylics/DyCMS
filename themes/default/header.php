<?php 
$theme_data = get_theme_data();
$user = $theme_data['user'];
?>

<header class="header">
    <div class="container d-flex justify-content-between align-items-center">
        <h1><?php echo htmlspecialchars(get_setting('site_name')); ?></h1>
        <div class="auth-buttons">
            <?php if ($user): ?>
                <a href="/profile" class="btn btn-modern me-2"><?php echo htmlspecialchars($user['username']); ?></a>
                <a href="/logout" class="btn btn-modern">Выход</a>
            <?php else: ?>
                <a href="/login" class="btn btn-modern me-2">Войти</a>
                <a href="/register" class="btn btn-modern">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</header>