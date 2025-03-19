<?php
session_start();

if (file_exists(__DIR__ . '/core/settings.php')) {
    die("Сайт уже установлен. Удалите файл core/settings.php для повторной установки.");
}

$error = '';
$success = '';
$step = $_SESSION['install_step'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1 && isset($_POST['next_step'])) {
        $db_host = $_POST['db_host'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        $db_name = $_POST['db_name'] ?? '';

        $conn = @mysqli_connect($db_host, $db_user, $db_pass);
        if (!$conn) {
            $error = "Ошибка подключения к MySQL: " . mysqli_connect_error();
        } else {
            if (!mysqli_select_db($conn, $db_name)) {
                $create_db = "CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                if (mysqli_query($conn, $create_db)) {
                    mysqli_select_db($conn, $db_name);
                } else {
                    $error = "Не удалось создать базу: " . mysqli_error($conn);
                }
            }
            if (!$error) {
                $_SESSION['db_host'] = $db_host;
                $_SESSION['db_user'] = $db_user;
                $_SESSION['db_pass'] = $db_pass;
                $_SESSION['db_name'] = $db_name;
                $_SESSION['install_step'] = 2;
                $step = 2;
            }
            mysqli_close($conn);
        }
    } elseif ($step == 2 && isset($_POST['install'])) {
        $admin_username = $_POST['admin_username'] ?? '';
        $admin_password = $_POST['admin_password'] ?? '';
        $admin_email = $_POST['admin_email'] ?? '';
        $site_name = $_POST['site_name'] ?? 'My Site';
        $site_desc = $_POST['site_desc'] ?? 'Описание сайта';
        $site_keywords = $_POST['site_keywords'] ?? 'ключевые слова';

        $conn = @mysqli_connect($_SESSION['db_host'], $_SESSION['db_user'], $_SESSION['db_pass'], $_SESSION['db_name']);
        if (!$conn) {
            $error = "Ошибка подключения к базе данных.";
        } else {
			$tables = [
				"CREATE TABLE users (
					id INT AUTO_INCREMENT PRIMARY KEY,
					username VARCHAR(50) NOT NULL UNIQUE,
					password VARCHAR(255) NOT NULL,
					email VARCHAR(100) NOT NULL UNIQUE,
					role ENUM('user', 'editor', 'admin') DEFAULT 'user',
					created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
				)",
				"CREATE TABLE categories (
					id INT AUTO_INCREMENT PRIMARY KEY,
					name VARCHAR(100) NOT NULL,
					slug VARCHAR(100) NOT NULL UNIQUE,
					description TEXT
				)",
				"CREATE TABLE settings (
					name VARCHAR(100) NOT NULL PRIMARY KEY,
					value TEXT NOT NULL
				)",
				"CREATE TABLE posts (
					id INT AUTO_INCREMENT PRIMARY KEY,
					title VARCHAR(255) NOT NULL,
					content TEXT NOT NULL,
					user_id INT,
					category_id INT,
					created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
					FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
				)",
				"CREATE TABLE comments (
					id INT AUTO_INCREMENT PRIMARY KEY,
					post_id INT NOT NULL,
					user_id INT DEFAULT NULL,
					content TEXT NOT NULL,
					created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
					FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
					FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
				)",
				"CREATE TABLE password_resets (
					id INT AUTO_INCREMENT PRIMARY KEY,
					user_id INT NOT NULL,
					token VARCHAR(64) NOT NULL,
					expires_at DATETIME NOT NULL,
					FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
				)",
				"CREATE TABLE pages (
					id INT AUTO_INCREMENT PRIMARY KEY,
					title VARCHAR(255) NOT NULL,
					content TEXT NOT NULL,
					slug VARCHAR(100) NOT NULL UNIQUE,
					use_template TINYINT(1) DEFAULT 1,
					is_home TINYINT(1) DEFAULT 0
				)",
				"CREATE TABLE plugins (
					name VARCHAR(100) NOT NULL PRIMARY KEY,
					active TINYINT(1) DEFAULT 0
				)",
				"CREATE TABLE uploads (
					id INT AUTO_INCREMENT PRIMARY KEY,
					post_id INT NULL,
					filename VARCHAR(255) NOT NULL,
					original_name VARCHAR(255) NOT NULL,
					mime_type VARCHAR(100) NOT NULL,
					size INT NOT NULL,
					upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
					user_id INT NOT NULL,
					FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL,
					FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
				)",
				"CREATE TABLE notes (
					id INT AUTO_INCREMENT PRIMARY KEY,
					user_id INT NOT NULL,
					content TEXT NOT NULL,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					FOREIGN KEY (user_id) REFERENCES users(id)
				)"
			];

            $all_tables_created = true;
            foreach ($tables as $sql) {
                if (!mysqli_query($conn, $sql)) {
                    $error = "Ошибка создания таблицы: " . mysqli_error($conn);
                    $all_tables_created = false;
                    break;
                }
            }

            if ($all_tables_created) {
                $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                $admin_username = mysqli_real_escape_string($conn, $admin_username);
                $admin_email = mysqli_real_escape_string($conn, $admin_email);
                $admin_sql = "INSERT INTO users (username, password, email, role) 
                              VALUES ('$admin_username', '$admin_password_hash', '$admin_email', 'admin')";
                if (!mysqli_query($conn, $admin_sql)) {
                    $error = "Ошибка создания администратора: " . mysqli_error($conn);
                } else {
                    $settings_sql = "INSERT INTO settings (name, value) VALUES 
                                    ('site_name', '" . mysqli_real_escape_string($conn, $site_name) . "'),
                                    ('site_desc', '" . mysqli_real_escape_string($conn, $site_desc) . "'),
                                    ('site_keywords', '" . mysqli_real_escape_string($conn, $site_keywords) . "')";
                    if (!mysqli_query($conn, $settings_sql)) {
                        $error = "Ошибка сохранения настроек: " . mysqli_error($conn);
                    } else {
                        $config_content = "<?php\n";
                        $config_content .= "define('DB_HOST', '" . $_SESSION['db_host'] . "');\n";
                        $config_content .= "define('DB_USER', '" . $_SESSION['db_user'] . "');\n";
                        $config_content .= "define('DB_PASS', '" . $_SESSION['db_pass'] . "');\n";
                        $config_content .= "define('DB_NAME', '" . $_SESSION['db_name'] . "');\n";
                        $config_content .= "?>\n";

                        if (file_put_contents(__DIR__ . '/core/settings.php', $config_content)) {
                            $success = "Установка завершена! <a href='/admin'>Войти в админ-панель</a> (логин: $admin_username).";
                            $_SESSION['install_step'] = 3;
                            $step = 3;
                            session_destroy(); // Очищаем сессию после установки
                        } else {
                            $error = "Не удалось записать settings.php. Проверьте права.";
                        }
                    }
                }
            }
            mysqli_close($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Установка</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; font-size: 14px; }
        .install-card { width: 100%; max-width: 360px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .form-label { margin-bottom: 2px; font-size: 12px; }
        .form-control { padding: 6px; font-size: 14px; margin-bottom: 8px; }
        .btn { padding: 6px; font-size: 14px; }
        .alert { margin-bottom: 10px; padding: 8px; font-size: 12px; }
        h3 { font-size: 18px; margin-bottom: 10px; text-align: center; }
        .license-text { max-height: 150px; overflow-y: auto; font-size: 12px; padding: 10px; background: #fff; border: 1px solid #ddd; margin-bottom: 10px; }
        .form-check { margin-bottom: 10px; }
        #next-btn { display: none; }
    </style>
</head>
<body>
    <div class="card install-card">
        <div class="card-body">
            <h3>Установка</h3>
            <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <?php if ($step == 1): ?>
                <div class="license-text">
                    <strong>Лицензия MIT</strong><br>
                    Copyright (c) 2025 xAI<br><br>
                    Данная программа распространяется под лицензией MIT. Разрешается использовать, копировать, модифицировать, публиковать, распространять и сублицензировать это программное обеспечение при условии сохранения уведомления об авторских правах и следующего текста лицензии:<br><br>
                    Программное обеспечение предоставляется "как есть", без каких-либо гарантий, явных или подразумеваемых, включая гарантии коммерческой ценности или пригодности для конкретных целей. Авторы или правообладатели не несут ответственности за любые претензии, убытки или иные обязательства, возникающие в связи с использованием программы.
                </div>
                <form method="post">
                    <label class="form-label">Хост</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                    <label class="form-label">Пользователь</label>
                    <input type="text" name="db_user" class="form-control" required>
                    <label class="form-label">Пароль</label>
                    <input type="password" name="db_pass" class="form-control">
                    <label class="form-label">База данных</label>
                    <input type="text" name="db_name" class="form-control" required>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="license-agree" onchange="document.getElementById('next-btn').style.display = this.checked ? 'block' : 'none';">
                        <label class="form-check-label" for="license-agree">Я прочитал и принимаю лицензию</label>
                    </div>
                    <button type="submit" name="next_step" id="next-btn" class="btn btn-primary w-100">Далее</button>
                </form>
            <?php elseif ($step == 2): ?>
                <form method="post">
                    <label class="form-label">Логин админа</label>
                    <input type="text" name="admin_username" class="form-control" required>
                    <label class="form-label">Пароль админа</label>
                    <input type="password" name="admin_password" class="form-control" required>
                    <label class="form-label">Email админа</label>
                    <input type="email" name="admin_email" class="form-control" required>
                    <label class="form-label">Название сайта</label>
                    <input type="text" name="site_name" class="form-control" value="My Site" required>
                    <label class="form-label">Описание</label>
                    <input type="text" name="site_desc" class="form-control" value="Описание сайта" required>
                    <label class="form-label">Ключевые слова</label>
                    <input type="text" name="site_keywords" class="form-control" value="ключевые слова" required>
                    <button type="submit" name="install" class="btn btn-primary w-100">Установить</button>
                </form>
            <?php elseif ($step == 3): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Убедимся, что кнопка "Далее" скрыта по умолчанию при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('next-btn').style.display = 'none';
        });
    </script>
</body>
</html>