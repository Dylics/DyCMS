<?php
require_once __DIR__ . '/settings.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Глобальная переменная для хранения настроек сайта
$site_settings = [];

function db_connect() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        error_log("Ошибка подключения к БД: " . mysqli_connect_error());
        die("Ошибка подключения к базе данных");
    }
    mysqli_set_charset($conn, 'utf8');
    return $conn;
}

// Функция для загрузки всех настроек из базы данных
function load_site_settings() {
    global $site_settings;
    $conn = db_connect();
    $result = mysqli_query($conn, "SELECT name, value FROM settings");
    while ($row = mysqli_fetch_assoc($result)) {
        $site_settings[$row['name']] = $row['value'];
    }
    // Устанавливаем значения по умолчанию, если они отсутствуют в базе
    $defaults = [
        'site_name' => 'My Site',
        'site_desc' => 'Описание сайта',
        'site_keywords' => 'ключевые слова'
    ];
    foreach ($defaults as $key => $value) {
        if (!isset($site_settings[$key])) {
            $site_settings[$key] = $value;
            update_setting($key, $value); // Сохраняем значение по умолчанию в базу
        }
    }
}

// Функция для получения конкретной настройки
function get_setting($name) {
    global $site_settings;
    return $site_settings[$name] ?? null;
}
load_site_settings();


function route($uri) {
    $uri = trim($uri, '/');
    if (empty($uri)) $uri = 'home';

    $conn = db_connect();
    if (!$conn) die("Ошибка подключения к БД");

    if ($uri === 'admin') {
        require_once __DIR__ . '/../admin/index.php';
        exit;
    }
    if ($uri === 'admin.reset-password') {
        handle_reset_password_request(true);
        exit;
    }
    if (strpos($uri, 'admin/reset') === 0) {
        handle_reset_password_form(true);
        exit;
    }
    if ($uri === 'reset-password') {
        handle_reset_password_request(false);
        exit;
    }
    if (strpos($uri, 'reset') === 0 && !strpos($uri, 'admin/reset')) {
        handle_reset_password_form(false);
        exit;
    }
    if ($uri === 'login') {
        handle_login();
        exit;
    }
    if ($uri === 'register') {
        handle_register();
        exit;
    }
    if ($uri === 'logout') {
        auth_logout();
        header('Location: /');
        exit;
    }
    if ($uri === 'profile') {
        handle_profile();
        exit;
    }
    if ($uri === 'search' && isset($_GET['q'])) {
        $query = mysqli_real_escape_string($conn, $_GET['q']);
        $sql = "SELECT * FROM posts WHERE title LIKE '%$query%' OR content LIKE '%$query%'";
        $result = mysqli_query($conn, $sql);
        $posts = mysqli_fetch_all($result, MYSQLI_ASSOC);
        render_theme('search', ['posts' => $posts, 'query' => $query]);
        exit;
    }
    if ($uri === 'comment' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id']) && isset($_POST['content'])) {
        csrf_check();
        $user_id = $_SESSION['user_id'] ?? null;
        if (add_comment($_POST['post_id'], $_POST['content'], $user_id)) {
            header('Location: /post/' . $_POST['post_id'] . '#comments');
        } else {
            die("Ошибка добавления комментария");
        }
        exit;
    }
	if (strpos($uri, 'post/') === 0) {
		$post_id = str_replace('post/', '', $uri);
		$conn = db_connect();
		$post_id = mysqli_real_escape_string($conn, $post_id);
		$result = mysqli_query($conn, "SELECT p.*, c.name as category_name 
									FROM posts p 
									LEFT JOIN categories c ON p.category_id = c.id 
									WHERE p.id='$post_id'");
		$post = mysqli_fetch_assoc($result);
		if ($post) {
			render_theme('post', ['post' => $post]);
		} else {
			render_theme('404');
		}
		exit;
	}


	if (strpos($uri, 'category/') === 0) {
		$category_slug = str_replace('category/', '', $uri);
		$conn = db_connect();
		$category_slug = mysqli_real_escape_string($conn, $category_slug);
		$result = mysqli_query($conn, "SELECT * FROM categories WHERE slug='$category_slug'");
		$category = mysqli_fetch_assoc($result);
	
		if ($category) {
			$posts = get_posts($category['id']);
			error_log("Категория: " . json_encode($category));
			error_log("Посты перед рендерингом: " . count($posts));
			render_theme('category', ['category' => $category, 'posts' => $posts]);
		} else {
			render_theme('404');
		}
		exit;
	}

    $slug = mysqli_real_escape_string($conn, $uri);
    $result = mysqli_query($conn, "SELECT * FROM pages WHERE slug='$slug'");
    if (!$result) {
        error_log("Ошибка запроса страниц: " . mysqli_error($conn));
        render_theme($uri);
        exit;
    }
    $page = mysqli_fetch_assoc($result);

    if ($page) {
        if (!$page['use_template']) {
            ob_start();
            eval('?>' . $page['content']);
            $content = ob_get_clean();
            echo $content;
        } else {
            render_theme($uri, $page);
        }
    } else {
        render_theme($uri);
    }
}
function get_theme_data() {
    return [
        'meta' => get_meta(),
        'user' => $_SESSION['user_id'] ? get_user($_SESSION['user_id']) : null,
        'post' => null // Добавьте данные поста, если нужно
    ];
}

if (isset($_POST['preview']) && $_POST['preview'] == 1) {
    $theme = $_GET['edit'];
    $file = $_POST['file'] ?? $_POST['asset'];
    $is_asset = isset($_POST['asset']);
    $temp_file = __DIR__ . "/../themes/$theme/temp_preview.php";
    file_put_contents($temp_file, '<?php require_once __DIR__ . "/../../core/app.php"; $theme_data = get_theme_data(); ?>' . $_POST['content']);
    ob_start();
    include $temp_file;
    $output = ob_get_clean();
    unlink($temp_file);
    echo $output;
    exit;
}

// В начало файла app.php после существующих функций
function upload_file($file, $post_id = null) {
    if (!auth_check('admin')) {
        error_log("Ошибка: пользователь не имеет прав editor");
        return false;
    }

    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Ошибка: не удалось создать директорию $upload_dir");
            return false;
        }
    }

    if (!is_writable($upload_dir)) {
        error_log("Ошибка: директория $upload_dir не доступна для записи");
        return false;
    }

    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'application/pdf',
        'text/plain', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    // Проверка ошибок загрузки
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Ошибка загрузки файла {$file['name']}: код ошибки " . $file['error']);
        return false;
    }

    if (!in_array($file['type'], $allowed_types) || $file['size'] > 10485760) { // 10MB
        error_log("Файл {$file['name']} отклонён: тип {$file['type']} не разрешён или размер {$file['size']} превышает 10MB");
        return false;
    }

    $filename = uniqid() . '_' . basename($file['name']);
    $target_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $conn = db_connect();
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            error_log("Ошибка: user_id не определён в сессии");
            unlink($target_path); // Удаляем файл, если запись в БД невозможна
            return false;
        }

        $original_name = mysqli_real_escape_string($conn, $file['name']);
        $mime_type = mysqli_real_escape_string($conn, $file['type']);
        $size = $file['size'];
        $post_id = $post_id ? "'".mysqli_real_escape_string($conn, $post_id)."'" : 'NULL';

        $sql = "INSERT INTO uploads (post_id, filename, original_name, mime_type, size, user_id, upload_date) 
                VALUES ($post_id, '$filename', '$original_name', '$mime_type', '$size', '$user_id', NOW())";
        
        if (mysqli_query($conn, $sql)) {
            error_log("Файл {$file['name']} успешно загружен как $filename");
            return $filename;
        } else {
            error_log("Ошибка SQL: " . mysqli_error($conn));
            unlink($target_path); // Удаляем файл при ошибке БД
            return false;
        }
    } else {
        error_log("Ошибка перемещения файла {$file['name']} в $target_path");
        return false;
    }
}

function get_post_files($post_id) {
    $conn = db_connect();
    $post_id = mysqli_real_escape_string($conn, $post_id);
    $result = mysqli_query($conn, "SELECT * FROM uploads WHERE post_id='$post_id'");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function get_all_files() {
    $conn = db_connect();
    $result = mysqli_query($conn, "SELECT u.*, p.title as post_title, us.username 
                                 FROM uploads u 
                                 LEFT JOIN posts p ON u.post_id = p.id 
                                 LEFT JOIN users us ON u.user_id = us.id 
                                 ORDER BY u.upload_date DESC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function delete_file($file_id) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $file_id = mysqli_real_escape_string($conn, $file_id);
    $result = mysqli_query($conn, "SELECT filename FROM uploads WHERE id='$file_id'");
    $file = mysqli_fetch_assoc($result);
    
    if ($file) {
        $file_path = __DIR__ . '/../uploads/' . $file['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        mysqli_query($conn, "DELETE FROM uploads WHERE id='$file_id'");
        return true;
    }
    return false;
}

function attach_file_to_post($file_id, $post_id) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $file_id = mysqli_real_escape_string($conn, $file_id);
    $post_id = mysqli_real_escape_string($conn, $post_id);
    $sql = "UPDATE uploads SET post_id='$post_id' WHERE id='$file_id'";
    return mysqli_query($conn, $sql);
}

function rmdir_recursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? rmdir_recursive($path) : unlink($path);
    }
    rmdir($dir);
}

if (isset($_POST['preview']) && $_POST['preview'] == 1) {
    $theme = $_GET['edit'];
    $file = $_POST['file'] ?? $_POST['asset'];
    $is_asset = isset($_POST['asset']);
    $temp_file = __DIR__ . "/../themes/$theme/temp_preview.php";
    file_put_contents($temp_file, '<?php require_once __DIR__ . "/../../core/app.php"; $theme_data = get_theme_data(); ?>' . $_POST['content']);
    ob_start();
    include $temp_file;
    $output = ob_get_clean();
    unlink($temp_file);
    echo $output;
    exit;
}


function auth_login($username, $password) {
    $conn = db_connect();
    $username = mysqli_real_escape_string($conn, $username);
    $result = mysqli_query($conn, "SELECT * FROM users WHERE username='$username'");
    if (!$result) {
        error_log("Ошибка запроса в БД: " . mysqli_error($conn));
        return false;
    }
    $user = mysqli_fetch_assoc($result);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    } else {
        error_log("Ошибка авторизации: пользователь '$username' не найден или неверный пароль");
        return false;
    }
}

function auth_check($role = null) {
    if (!isset($_SESSION['user_id'])) return false;
    if ($role && $_SESSION['role'] !== $role) return false;
    return true;
}

function auth_logout() {
    session_destroy();
}

function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("Ошибка CSRF: недействительный токен");
        }
    }
}

function get_posts($category_id = null) {
    $conn = db_connect();
    $sql = "SELECT p.id, p.title, p.content, p.user_id, p.category_id, p.created_at, c.name as category_name 
            FROM posts p 
            LEFT JOIN categories c ON p.category_id = c.id";
    if ($category_id) {
        $category_id = mysqli_real_escape_string($conn, $category_id);
        $sql .= " WHERE p.category_id='$category_id'";
    }
    $sql .= " ORDER BY p.created_at DESC";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log("Ошибка SQL: " . mysqli_error($conn));
        return [];
    }
    $posts = mysqli_fetch_all($result, MYSQLI_ASSOC);
    error_log("Посты в get_posts: " . json_encode($posts));
    return $posts;
}

function add_post($title, $content, $category_id = null) {
    if (!auth_check() || !in_array($_SESSION['role'], ['editor', 'admin'])) return false;
    $conn = db_connect();
    $title = mysqli_real_escape_string($conn, $title);
    $content = mysqli_real_escape_string($conn, $content);
    $user_id = $_SESSION['user_id'] ?? null;
    $category_id = $category_id ? "'".mysqli_real_escape_string($conn, $category_id)."'" : 'NULL';
    $sql = "INSERT INTO posts (title, content, user_id, category_id, created_at) VALUES ('$title', '$content', '$user_id', $category_id, NOW())";
    if (mysqli_query($conn, $sql)) {
        return mysqli_insert_id($conn); // Возвращаем ID нового поста
    }
    return false;
}

function delete_post($id) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $sql = "DELETE FROM posts WHERE id='$id'";
    return mysqli_query($conn, $sql);
}

function get_comments($post_id, $status = 'approved') {
    $conn = db_connect();
    $post_id = mysqli_real_escape_string($conn, $post_id);
    $status = mysqli_real_escape_string($conn, $status);
    $sql = "SELECT c.*, u.username FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = '$post_id' AND c.status = '$status' 
            ORDER BY c.created_at ASC";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function add_comment($post_id, $content, $user_id = null) {
    $conn = db_connect();
    $post_id = mysqli_real_escape_string($conn, $post_id);
    $content = mysqli_real_escape_string($conn, $content);
    $user_id = $user_id ? "'".mysqli_real_escape_string($conn, $user_id)."'" : 'NULL';
    
    // Получаем настройку автоодобрения
    $auto_approve = get_setting('auto_approve_comments');
    $status = ($auto_approve == '1') ? 'approved' : 'pending';
    
    $sql = "INSERT INTO comments (post_id, user_id, content, status) 
            VALUES ('$post_id', $user_id, '$content', '$status')";
    return mysqli_query($conn, $sql);
}

function update_comment_status($comment_id, $status) {
    if (!auth_check() || (!in_array($_SESSION['role'], ['editor', 'admin']))) return false;
    $conn = db_connect();
    $comment_id = mysqli_real_escape_string($conn, $comment_id);
    $status = mysqli_real_escape_string($conn, $status);
    $sql = "UPDATE comments SET status = '$status' WHERE id = '$comment_id'";
    return mysqli_query($conn, $sql);
}

function delete_comment($comment_id) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $comment_id = mysqli_real_escape_string($conn, $comment_id);
    $sql = "DELETE FROM comments WHERE id = '$comment_id'";
    return mysqli_query($conn, $sql);
}

function get_pages() {
    $conn = db_connect();
    $result = mysqli_query($conn, "SELECT * FROM pages");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function add_page($title, $content, $slug, $use_template = 1, $is_home = 0) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $title = mysqli_real_escape_string($conn, $title);
    $content = $use_template ? $content : mysqli_real_escape_string($conn, $content);
    $slug = mysqli_real_escape_string($conn, $slug);
    $sql = "INSERT INTO pages (title, content, slug, use_template, is_home) VALUES ('$title', '$content', '$slug', '$use_template', '$is_home')";
    return mysqli_query($conn, $sql);
}

function get_page($id) {
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $result = mysqli_query($conn, "SELECT * FROM pages WHERE id='$id'");
    return mysqli_fetch_assoc($result);
}

function update_page($id, $title, $content, $slug, $use_template, $is_home) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $title = mysqli_real_escape_string($conn, $title);
    $content = $use_template ? $content : mysqli_real_escape_string($conn, $content);
    $slug = mysqli_real_escape_string($conn, $slug);
    $sql = "UPDATE pages SET title='$title', content='$content', slug='$slug', use_template='$use_template', is_home='$is_home' WHERE id='$id'";
    return mysqli_query($conn, $sql);
}

function delete_page($id) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $sql = "DELETE FROM pages WHERE id='$id'";
    return mysqli_query($conn, $sql);
}

function get_users() {
    if (!auth_check('admin')) return [];
    $conn = db_connect();
    $result = mysqli_query($conn, "SELECT id, username, email, role FROM users");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function add_user($username, $password, $email, $role = 'user') {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $username = mysqli_real_escape_string($conn, $username);
    $email = mysqli_real_escape_string($conn, $email);
    $password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, password, email, role) VALUES ('$username', '$password', '$email', '$role')";
    return mysqli_query($conn, $sql);
}

function get_user($id) {
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $result = mysqli_query($conn, "SELECT * FROM users WHERE id='$id'");
    return mysqli_fetch_assoc($result);
}

function update_user($id, $username, $email, $role, $password = null) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $username = mysqli_real_escape_string($conn, $username);
    $email = mysqli_real_escape_string($conn, $email);
    $role = mysqli_real_escape_string($conn, $role);
    $sql = "UPDATE users SET username='$username', email='$email', role='$role'";
    if ($password) {
        $password = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password='$password'";
    }
    $sql .= " WHERE id='$id'";
    return mysqli_query($conn, $sql);
}

function delete_user($id) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $sql = "DELETE FROM users WHERE id='$id'";
    return mysqli_query($conn, $sql);
}

function get_categories() {
    $conn = db_connect();
    $result = mysqli_query($conn, "SELECT * FROM categories");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function get_category($id) {
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $result = mysqli_query($conn, "SELECT * FROM categories WHERE id='$id'");
    return mysqli_fetch_assoc($result);
}

function add_category($name, $slug, $description = '') {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $name = mysqli_real_escape_string($conn, $name);
    $slug = mysqli_real_escape_string($conn, $slug);
    $description = mysqli_real_escape_string($conn, $description);
    $sql = "INSERT INTO categories (name, slug, description) VALUES ('$name', '$slug', '$description')";
    return mysqli_query($conn, $sql);
}

function update_category($id, $name, $slug, $description) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $name = mysqli_real_escape_string($conn, $name);
    $slug = mysqli_real_escape_string($conn, $slug);
    $description = mysqli_real_escape_string($conn, $description);
    $sql = "UPDATE categories SET name='$name', slug='$slug', description='$description' WHERE id='$id'";
    return mysqli_query($conn, $sql);
}

function delete_category($id) {
    if (!auth_check('admin')) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $sql = "DELETE FROM categories WHERE id='$id'";
    return mysqli_query($conn, $sql);
}

function get_notes($user_id = null) {
    $conn = db_connect();
    if (!$user_id) $user_id = $_SESSION['user_id'];
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $result = mysqli_query($conn, "SELECT * FROM notes WHERE user_id='$user_id' ORDER BY updated_at DESC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function add_note($content) {
    if (!auth_check()) return false;
    $conn = db_connect();
    $content = mysqli_real_escape_string($conn, $content);
    $user_id = $_SESSION['user_id'];
    $sql = "INSERT INTO notes (user_id, content, created_at) VALUES ('$user_id', '$content', NOW())";
    return mysqli_query($conn, $sql);
}

function update_note($id, $content) {
    if (!auth_check()) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $content = mysqli_real_escape_string($conn, $content);
    $user_id = $_SESSION['user_id'];
    $sql = "UPDATE notes SET content='$content', updated_at=NOW() WHERE id='$id' AND user_id='$user_id'";
    return mysqli_query($conn, $sql);
}

function delete_note($id) {
    if (!auth_check()) return false;
    $conn = db_connect();
    $id = mysqli_real_escape_string($conn, $id);
    $user_id = $_SESSION['user_id'];
    $sql = "DELETE FROM notes WHERE id='$id' AND user_id='$user_id'";
    return mysqli_query($conn, $sql);
}

function load_plugins() {
    $conn = db_connect();
    $result = mysqli_query($conn, "SELECT name FROM plugins WHERE active=1");
    if (!$result) return;
    $active_plugins = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $active_plugins[] = $row['name'];
    }
    $plugins = glob(__DIR__ . '/../plugins/*.php');
    foreach ($plugins as $plugin) {
        $plugin_name = basename($plugin);
        if (in_array($plugin_name, $active_plugins)) {
            require_once $plugin;
        }
    }
}

// Обновляем функцию render_theme для использования новых настроек
function render_theme($uri, $page = null) {
    global $page_param, $meta, $site_settings;
    $page_param = $uri;
    $meta = get_meta($page);
    $theme = get_active_theme();
    if ($page_param === 'category' && isset($page['category'])) {
        $category_data = $page['category'];
        $posts_data = $page['posts'];
        if (file_exists(__DIR__ . "/../themes/$theme/category.php")) {
            error_log("Рендеринг category.php с постами: " . count($posts_data));
            require_once __DIR__ . "/../themes/$theme/category.php";
        } else {
            require_once __DIR__ . "/../themes/$theme/template.php";
        }
    } elseif ($page_param === 'post' && file_exists(__DIR__ . "/../themes/$theme/post.php")) {
        require_once __DIR__ . "/../themes/$theme/post.php";
    } elseif ($page_param === 'home' && file_exists(__DIR__ . "/../themes/$theme/home.php") && get_setting('use_home_template')) {
        require_once __DIR__ . "/../themes/$theme/home.php";
    } else {
        require_once __DIR__ . "/../themes/$theme/template.php";
    }
}

function get_meta($page = null) {
    global $site_settings;
    if ($page) {
        return [
            'title' => $page['title'],
            'description' => substr(strip_tags($page['content']), 0, 150),
            'keywords' => get_setting('site_keywords')
        ];
    }
    return [
        'title' => get_setting('site_name'),
        'description' => get_setting('site_desc'),
        'keywords' => get_setting('site_keywords')
    ];
}

function update_setting($name, $value) {
    $conn = db_connect();
    $name = mysqli_real_escape_string($conn, $name);
    $value = mysqli_real_escape_string($conn, $value);
    $sql = "INSERT INTO settings (name, value) VALUES ('$name', '$value') ON DUPLICATE KEY UPDATE value='$value'";
    return mysqli_query($conn, $sql);
}

function get_active_theme() {
    $conn = db_connect();
    $result = mysqli_query($conn, "SELECT value FROM settings WHERE name='active_theme'");
    $theme = mysqli_fetch_assoc($result)['value'] ?? 'default';
    return $theme;
}

function set_active_theme($theme) {
    update_setting('active_theme', $theme);
}

function get_theme_parts($theme) {
    $file = __DIR__ . "/../themes/$theme/template.php";
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    $parts = [
        'header' => extract_part($content, '<header', '</header>'),
        'nav' => extract_part($content, '<nav', '</nav>'),
        'main' => extract_part($content, '<main', '</main>'),
        'footer' => extract_part($content, '<footer', '</footer>')
    ];
    return $parts;
}

function extract_part($content, $start_tag, $end_tag) {
    $start_pos = strpos($content, $start_tag);
    if ($start_pos === false) return '';
    $end_pos = strpos($content, $end_tag, $start_pos);
    if ($end_pos === false) return '';
    $start = $start_pos;
    $length = $end_pos + strlen($end_tag) - $start;
    return trim(substr($content, $start, $length));
}

function update_theme_part($theme, $part, $content) {
    $file = __DIR__ . "/../themes/$theme/template.php";
    if (!file_exists($file)) return false;
    $template = file_get_contents($file);
    $old_part = extract_part($template, "<$part", "</$part>");
    if ($old_part === '') return false;
    $new_template = str_replace("<$part" . $old_part . "</$part>", "<$part" . $content . "</$part>", $template);
    return file_put_contents($file, $new_template);
}

function get_theme_css($theme) {
    $file = __DIR__ . "/../themes/$theme/assets/style.css";
    return file_exists($file) ? file_get_contents($file) : '';
}

function update_theme_css($theme, $css) {
    $file = __DIR__ . "/../themes/$theme/assets/style.css";
    return file_put_contents($file, $css);
}



function delete_theme($theme) {
    if ($theme === get_active_theme()) return false;
    $dir = __DIR__ . "/../themes/$theme";
    if (!is_dir($dir)) return false;
    rmdir_recursive($dir);
    return !is_dir($dir); // Проверяем, что директория действительно удалена
}

function create_theme($name) {
    global $site_settings;
    $dir = __DIR__ . "/../themes/$name";
    if (is_dir($dir)) return false;
    mkdir($dir);
    mkdir("$dir/assets");
    file_put_contents("$dir/template.php", '<?php require_once __DIR__ . "/../../core/app.php"; ?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title><?php echo $meta["title"]; ?></title><link href="/themes/' . $name . '/assets/style.css" rel="stylesheet"><script src="/themes/' . $name . '/assets/script.js"></script></head><body><?php include "header.php"; include "nav.php"; include "main.php"; include "footer.php"; ?></body></html>');
    file_put_contents("$dir/home.php", '<?php require_once __DIR__ . "/../../core/app.php"; ?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title><?php echo $meta["title"]; ?></title><link href="/themes/' . $name . '/assets/style.css" rel="stylesheet"><script src="/themes/' . $name . '/assets/script.js"></script></head><body><h1>Главная</h1><?php $posts = get_posts(); foreach ($posts as $post) { echo "<h2>" . htmlspecialchars($post["title"]) . "</h2><p>" . htmlspecialchars(substr($post["content"], 0, 200)) . "...</p>"; } ?></body></html>');
    file_put_contents("$dir/login.php", '<?php require_once __DIR__ . "/../../core/app.php"; handle_login(); ?>');
    file_put_contents("$dir/register.php", '<?php require_once __DIR__ . "/../../core/app.php"; handle_register(); ?>');
    file_put_contents("$dir/reset.php", '<?php require_once __DIR__ . "/../../core/app.php"; handle_reset_password_form(); ?>');
    file_put_contents("$dir/assets/style.css", "body { font-family: 'Arial', sans-serif; }");
    file_put_contents("$dir/assets/script.js", "console.log('Theme loaded');");
    file_put_contents("$dir/header.php", '<header><h1><?php echo htmlspecialchars(get_setting("site_name")); ?></h1></header>');
    file_put_contents("$dir/nav.php", '<nav><?php foreach (get_categories() as $c) { echo "<a href=\"/category/{$c[\'slug\']}\">" . htmlspecialchars($c[\'name\']) . "</a> "; } ?></nav>');
    file_put_contents("$dir/main.php", '<main><?php if ($page_param === "home") { $posts = get_posts(); foreach ($posts as $p) { echo "<h2>" . htmlspecialchars($p["title"]) . "</h2>"; } } elseif ($page) { echo $page["content"]; } ?></main>');
    file_put_contents("$dir/footer.php", '<footer>© <?php echo date("Y"); ?> <?php echo htmlspecialchars(get_setting("site_name")); ?></footer>');
    return true;
}

function handle_login() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        if (auth_login($_POST['username'], $_POST['password'])) {
            header('Location: /');
            exit;
        } else {
            $error = "Неверный логин или пароль";
        }
    }
    $theme = get_active_theme();
    if (file_exists(__DIR__ . "/../themes/$theme/login.php")) {
        require_once __DIR__ . "/../themes/$theme/login.php";
    } else {

    }
}

function handle_register() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password']) && isset($_POST['email'])) {
        $conn = db_connect();
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, email, role) VALUES ('$username', '$password', '$email', 'user')";
        if (mysqli_query($conn, $sql)) {
            auth_login($_POST['username'], $_POST['password']);
            header('Location: /');
            exit;
        } else {
            $error = "Ошибка регистрации: " . mysqli_error($conn);
        }
    }
    $theme = get_active_theme();
    if (file_exists(__DIR__ . "/../themes/$theme/register.php")) {
        require_once __DIR__ . "/../themes/$theme/register.php";
    } else {

    }
}

function handle_reset_password_request($is_admin = false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
        $conn = db_connect();
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
        $user = mysqli_fetch_assoc($result);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            mysqli_query($conn, "INSERT INTO password_resets (user_id, token, expires_at) VALUES ({$user['id']}, '$token', '$expires_at')");
            $reset_url = $is_admin ? "/admin/reset?token=$token" : "/reset?token=$token";
            if (send_reset_email($email, $token, $reset_url)) {
                echo "<div class='alert alert-success'>Письмо для сброса пароля отправлено.</div>";
            } else {
                echo "<div class='alert alert-danger'>Ошибка отправки письма.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Пользователь не найден.</div>";
        }
    }

}

function handle_reset_password_form($is_admin = false) {
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    $conn = db_connect();
    $token = mysqli_real_escape_string($conn, $token);
    $result = mysqli_query($conn, "SELECT * FROM password_resets WHERE token='$token' AND expires_at > NOW()");
    $reset = mysqli_fetch_assoc($result);

    if (!$reset) {
        die("Недействительный или истёкший токен.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['password_confirm'])) {
        if ($_POST['password'] === $_POST['password_confirm']) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password='$password' WHERE id={$reset['user_id']}");
            mysqli_query($conn, "DELETE FROM password_resets WHERE token='$token'");
            echo "<div class='alert alert-success'>Пароль изменён. <a href='" . ($is_admin ? '/admin' : '/login') . "'>Войти</a></div>";
        } else {
            echo "<div class='alert alert-danger'>Пароли не совпадают.</div>";
        }
    }
    $theme = get_active_theme();
    if (file_exists(__DIR__ . "/../themes/$theme/reset.php")) {
        require_once __DIR__ . "/../themes/$theme/reset.php";
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <title>Новый пароль</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #f5f7fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
                .reset-card { max-width: 400px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            </style>
        </head>
        <body>
            <div class="card reset-card">
                <div class="card-body">
                    <h3 class="text-center mb-4 text-primary">Новый пароль</h3>
                    <form method="post">
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="Новый пароль" required>
                        </div>
                        <div class="mb-3">
                            <input type="password" name="password_confirm" class="form-control" placeholder="Подтверждение" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Сохранить</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

// Обновляем функции, зависящие от SITE_NAME и других констант
function send_reset_email($email, $token, $reset_url = '/reset?token=') {
    $conn = db_connect();
    $result = mysqli_query($conn, "SELECT name, value FROM settings WHERE name LIKE 'smtp_%'");
    $settings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['name']] = $row['value'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        $mail->SMTPSecure = $settings['smtp_secure'];
        $mail->Port = $settings['smtp_port'];
        $mail->setFrom($settings['smtp_from'], get_setting('site_name')); // Используем get_setting
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Сброс пароля для ' . get_setting('site_name');
        $mail->Body = "Здравствуйте,<br>Вы запросили сброс пароля. Перейдите по ссылке: <a href='http://yourdomain.com$reset_url'>Сбросить пароль</a><br>Ссылка действительна 1 час.";
        $mail->AltBody = "Перейдите по ссылке: http://yourdomain.com$reset_url";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Ошибка отправки письма: {$mail->ErrorInfo}");
        return false;
    }
}

function handle_profile() {
    if (!auth_check()) {
        header('Location: /login');
        exit;
    }
    $conn = db_connect();
    $user = get_user($_SESSION['user_id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
        update_user($user['id'], $username, $email, $user['role'], $password);
        echo "<div class='alert alert-success'>Профиль обновлён</div>";
        $user = get_user($_SESSION['user_id']);
    }
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Профиль</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #f5f7fa; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
            .profile-card { max-width: 500px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class="card profile-card">
            <div class="card-body">
                <h3 class="text-center mb-4">Профиль</h3>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Логин</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Новый пароль (оставьте пустым, если не меняете)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Сохранить</button>
                    <div class="text-center mt-3">
                        <a href="/" class="text-muted">На главную</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
}

$actions = [];
function add_action($hook, $function) {
    global $actions;
    $actions[$hook][] = $function;
}

function do_action($hook) {
    global $actions;
    if (isset($actions[$hook])) {
        foreach ($actions[$hook] as $function) {
            call_user_func($function);
        }
    }
}
?>