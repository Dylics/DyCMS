<?php
session_start();
require_once __DIR__ . '/../core/app.php';

$theme = get_setting('active_theme'); // Дефолтная тема из настроек
if (isset($_SESSION['preview_theme'])) {
    $theme = $_SESSION['preview_theme'];
    unset($_SESSION['preview_theme']); // Сбрасываем сразу после использования
}

// Обработка предпросмотра темы
if (isset($_GET['action']) && $_GET['action'] === 'themes' && isset($_GET['preview_theme'])) {
    if (!auth_check('admin')) die("Доступ запрещён");
    $_SESSION['preview_theme'] = $_GET['preview_theme'];
    header('Location: /');
    exit;
}

 // Экспорт темы
if (isset($_GET['action']) && $_GET['action'] === 'themes' && isset($_GET['edit']) && isset($_GET['export'])) {
    if (!auth_check('admin')) die("Доступ запрещён");

    $themes = array_filter(
        array_map('basename', glob(__DIR__ . '/../themes/*', GLOB_ONLYDIR)),
        fn($theme) => $theme !== 'backups' && is_dir(__DIR__ . "/../themes/$theme")
    );

    if (in_array($_GET['edit'], $themes)) {
        $theme = $_GET['edit'];
        $theme_path = realpath(__DIR__ . "/../themes/$theme"); // Используем realpath для корректных путей
        $zip_file = __DIR__ . "/../themes/{$theme}.zip";
        $zip = new ZipArchive();

        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($theme_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                $file_path = $file->getRealPath();
                $relative_path = str_replace($theme_path . DIRECTORY_SEPARATOR, '', $file_path); // Корректное формирование относительного пути

                // Пропускаем нежелательные файлы и директории
                if ($file->isFile() && !preg_match('/(\.DS_Store|\.git|\.gitignore)$/i', $file->getBasename())) {
                    $zip->addFile($file_path, $relative_path);
                }
            }

            $zip->close();

            if (file_exists($zip_file)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $theme . '.zip"');
                header('Content-Length: ' . filesize($zip_file));
                readfile($zip_file);
                unlink($zip_file);
                exit;
            } else {
                die('Ошибка: архив не был создан');
            }
        } else {
            die('Ошибка экспорта темы: не удалось создать архив');
        }
    }
}

if (!auth_check()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        if (auth_login($_POST['username'], $_POST['password'])) {
            header('Location: /admin');
            exit;
        } else {
            $error = "Неверный логин или пароль";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход в админ-панель - <?php echo htmlspecialchars(get_setting('site_name')); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #f4f7fa; color: #333; font-family: 'Poppins', sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; }
            .login-card { max-width: 400px; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn-modern { background: #6c63ff; color: #fff; border: none; padding: 8px 16px; border-radius: 5px; transition: all 0.3s; }
            .btn-modern:hover { background: #554bff; }
            .text-muted { color: #888; }
            .text-muted:hover { color: #6c63ff; }
			:root { --primary-color: #6c63ff; }
			.editor-area { transition: background 0.3s; }
			.code-editor-theme { display: none; }
			.CodeMirror { width: 60%; height: 100%; border: none; font-size: 14px; }
			.file-item i { color: #6c63ff; }
			.theme-item i { color: #6c63ff; }
        </style>
    </head>
    <body>
        <div class="card login-card">
            <div class="card-body">
                <h3 class="text-center mb-4">Вход в админ-панель</h3>
                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                <form method="post">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control" placeholder="Логин" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Пароль" required>
                    </div>
                    <button type="submit" class="btn btn-modern w-100">Войти</button>
                    <div class="text-center mt-3">
                        <a href="/admin/reset-password" class="text-muted">Забыли пароль?</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$action = $_GET['action'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель - <?php echo htmlspecialchars(get_setting('site_name')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/codemirror.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fa; color: #333; font-family: 'Poppins', sans-serif; margin: 0; min-height: 100vh; overflow-x: hidden; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; width: 250px; background: #fff; padding: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar a { color: #666; text-decoration: none; display: block; padding: 12px; border-radius: 8px; transition: all 0.3s; }
        .sidebar a:hover, .sidebar a.active { color: #fff; background: #6c63ff; }
        .content { margin-left: 250px; padding: 20px; min-height: 100vh; }
        .btn-modern { background: #6c63ff; color: #fff; border: none; padding: 8px 16px; border-radius: 5px; transition: all 0.3s; }
        .btn-modern:hover { background: #554bff; }
        .btn-secondary { background: #ddd; color: #333; border: none; padding: 8px 16px; border-radius: 5px; transition: all 0.3s; }
        .btn-secondary:hover { background: #ccc; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: none; }
        .table { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .hint { color: #888; font-size: 0.9em; }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.ql-container {
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.ql-toolbar {
    border-radius: 8px 8px 0 0;
    background: #fff;
    box-shadow: 0 1px 5px rgba(0,0,0,0.05);
}
.file-item {
    padding: 5px 10px;
    background: #f8f9fa;
    border-radius: 5px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.file-list .file-item {
    display: flex;
    align-items: center;
}
.file-list .file-item input {
    margin-right: 10px;
}
.sortable { cursor: pointer; }
.sortable.asc i::before { content: '\f0de'; } /* fa-sort-up */
.sortable.desc i::before { content: '\f0dd'; } /* fa-sort-down */
        /* Стили для редактора тем */
        <?php if ($action === 'themes'): ?>
        .content { display: flex; height: 100vh; }
        .theme-explorer { width: 300px; background: #fff; padding: 20px; border-right: 1px solid #eee; overflow-y: auto; }
        .editor-area { flex: 1; display: flex; flex-direction: column; padding: 20px; }
        .theme-item { padding: 15px; border-radius: 8px; background: #f9f9f9; margin-bottom: 10px; cursor: pointer; transition: all 0.3s; position: relative; }
        .theme-item:hover, .theme-item.active { background: #e8e6ff; }
        .theme-item.active { border-left: 4px solid #6c63ff; }
        .theme-preview { width: 50px; height: 50px; background: #ddd; border-radius: 5px; float: right; }
        .file-list { margin-top: 20px; }
        .file-item { padding: 10px; border-radius: 5px; background: #f0f0f0; margin-bottom: 5px; cursor: pointer; transition: all 0.3s; }
        .file-item:hover { background: #e0e0e0; }
        .file-item.active { background: #d9d6ff; }
        .editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .editor { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); flex: 1; padding: 15px; display: flex; position: relative; }
        .code-editor-theme { width: 100%; padding: 15px; font-family: 'JetBrains Mono', monospace; border: none; outline: none; resize: none; height: 100%; }
        .preview { width: 40%; background: #f9f9f9; padding: 15px; border-left: 1px solid #eee; overflow-y: auto; }
        .autocomplete { position: absolute; background: #fff; border: 1px solid #ddd; border-radius: 5px; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; }
        .autocomplete-item { padding: 5px 10px; cursor: pointer; }
        .autocomplete-item:hover { background: #e8e6ff; }
        .dropdown-menu { border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		.file-actions { display: inline-block; opacity: 0; transition: opacity 0.3s; }
		.file-item:hover .file-actions { opacity: 1; }
		.file-actions i { margin-left: 8px; cursor: pointer; color: #6c63ff; }
		.file-actions i:hover { color: #554bff; }
		.preview {
			width: 300px;
			max-height: 400px;
			overflow-y: auto;
			border-left: 1px solid #ddd;
			padding: 10px;
			background: #f9f9f9;
		}
		.preview h6 {
			margin: 0 0 10px 0;
		}
		.theme-explorer {
    width: 300px;
    flex-shrink: 0;
}
.editor-area {
    flex-grow: 1;
    min-width: 0;
}
.CodeMirror {
    width: 100%;
    height: 100%;
    border: none;
    font-size: 14px;
}
.editor {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 150px); /* Ограничиваем высоту редактора */
}
#previewModalBody {
    max-height: 500px;
    overflow-y: auto;
    padding: 300px;
    background: #f9f9f9;
}
.alert {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1050; /* Чтобы быть выше модальных окон и других элементов */
    padding: 15px 25px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
}
.alert.show {
    opacity: 1;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.sortable:hover { cursor: pointer; color: #6c63ff; }
.sort-icon { margin-left: 5px; }
.editor-header .d-flex.gap-2 button, 
.editor-header .d-flex.gap-2 a {
    margin-right: 5px; /* Отступ между кнопками */
}
.editor-header .d-flex.gap-2 button:last-child, 
.editor-header .d-flex.gap-2 a:last-child {
    margin-right: 0; /* Убираем отступ у последней кнопки */
}

        <?php endif; ?>
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="mb-4">DyCMS</h4>
        <a href="?action=dashboard" class="<?php echo $action === 'dashboard' ? 'active' : ''; ?>">Главная</a>
        <?php if (auth_check('admin')): ?>
            <a href="?action=posts" class="<?php echo $action === 'posts' ? 'active' : ''; ?>">Посты</a>
            <a href="?action=comments" class="<?php echo $action === 'comments' ? 'active' : ''; ?>">
				Комментарии
				<?php
				$conn = db_connect();
				$pending = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM comments WHERE status='pending'"))[0];
				if ($pending > 0) {
					echo "<span class='badge bg-danger ms-2'>$pending</span>";
				}
				?>
			</a>
            <a href="?action=pages" class="<?php echo $action === 'pages' ? 'active' : ''; ?>">Страницы</a>
            <a href="?action=users" class="<?php echo $action === 'users' ? 'active' : ''; ?>">Пользователи</a>
            <a href="?action=categories" class="<?php echo $action === 'categories' ? 'active' : ''; ?>">Категории</a>
            <a href="?action=themes" class="<?php echo $action === 'themes' ? 'active' : ''; ?>">Темы</a>
            <a href="?action=plugins" class="<?php echo $action === 'plugins' ? 'active' : ''; ?>">Плагины</a>
            <a href="?action=filemanager" class="<?php echo $action === 'filemanager' ? 'active' : ''; ?>">Файлы</a>
            <a href="?action=settings" class="<?php echo $action === 'settings' ? 'active' : ''; ?>">Настройки</a>
        <?php endif; ?>
        <a href="/logout">Выход</a>
    </div>
    <div class="content">
	<div class="mb-3">
    <input type="text" class="form-control" id="contentSearch" placeholder="Поиск по <?php echo $action; ?>...">
	</div>
        <?php
        switch ($action) {
case 'dashboard':
    // Обработка действий с заметками
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_content'])) {
        csrf_check();
        if (isset($_POST['note_id']) && !empty($_POST['note_id'])) {
            update_note($_POST['note_id'], $_POST['note_content']);
            echo "<div class='alert alert-success'>Заметка обновлена</div>";
        } else {
            add_note($_POST['note_content']);
            echo "<div class='alert alert-success'>Заметка добавлена</div>";
        }
    }
    if (isset($_GET['delete_note']) && is_numeric($_GET['delete_note'])) {
        csrf_check();
        delete_note($_GET['delete_note']);
        echo "<div class='alert alert-success'>Заметка удалена</div>";
    }
    ?>
    <h1 class="mb-4">Панель управления</h1>
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Посты</h5>
                    <p class="card-text"><?php echo count(get_posts()); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Комментарии</h5>
                    <p class="card-text">
                        <?php 
                        $conn = db_connect(); 
                        $total = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM comments"))[0];
                        $pending = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM comments WHERE status='pending'"))[0];
                        echo "Всего: $total (Ожидают: $pending)";
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Пользователи</h5>
                    <p class="card-text"><?php echo count(get_users()); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Страницы</h5>
                    <p class="card-text"><?php echo count(get_pages()); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Заметки -->
    <div class="mt-5">
        <h2 class="mb-3">Заметки</h2>
        <button class="btn btn-modern mb-3" data-bs-toggle="modal" data-bs-target="#addNoteModal">Добавить заметку</button>
        <div class="row">
            <?php foreach (get_notes() as $note): ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="card-text"><?php echo htmlspecialchars($note['content']); ?></p>
                            <p class="text-muted small">Обновлено: <?php echo $note['updated_at']; ?></p>
                            <div class="d-flex justify-content-between">
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editNoteModal" data-note-id="<?php echo $note['id']; ?>" data-note-content="<?php echo htmlspecialchars($note['content']); ?>">Редактировать</button>
                                <a href="?action=dashboard&delete_note=<?php echo $note['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить заметку?')">Удалить</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Модальное окно для добавления заметки -->
    <div class="modal fade" id="addNoteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить заметку</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Текст заметки</label>
                            <textarea name="note_content" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-modern">Добавить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно для редактирования заметки -->
    <div class="modal fade" id="editNoteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактировать заметку</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="note_id" id="editNoteId">
                        <div class="mb-3">
                            <label class="form-label">Текст заметки</label>
                            <textarea name="note_content" id="editNoteContent" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-modern">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editModal = document.getElementById('editNoteModal');
            editModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const noteId = button.getAttribute('data-note-id');
                const noteContent = button.getAttribute('data-note-content');
                document.getElementById('editNoteId').value = noteId;
                document.getElementById('editNoteContent').value = noteContent;
            });
        });
    </script>
    <?php
    break;

case 'posts':
    if (!auth_check('admin')) die("Доступ запрещён");
    $posts = get_posts();
	
	if (isset($_GET['bulk_delete'])) {
    csrf_check();
    $ids = explode(',', $_GET['bulk_delete']);
    foreach ($ids as $id) {
        if (is_numeric($id)) delete_post($id);
    }
    $posts = get_posts();
    echo "<div class='alert alert-success'>Выбранные посты удалены</div>";
}
	
    if (isset($_GET['preview']) && is_numeric($_GET['preview'])) {
        $post = array_filter($posts, fn($p) => $p['id'] == $_GET['preview']);
        $post = reset($post);
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Предпросмотр</title></head><body>";
        echo "<h1>" . htmlspecialchars($post['title']) . "</h1>";
        echo $post['content'];
        echo "</body></html>";
        exit;
    }
    // Обработка загрузки файлов при создании/редактировании поста
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && isset($_POST['content'])) {
        csrf_check();
        $post_id = null;
        
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $conn = db_connect();
            $id = mysqli_real_escape_string($conn, $_GET['edit']);
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $content = mysqli_real_escape_string($conn, $_POST['content']);
            $category_id = $_POST['category_id'] ? "'".mysqli_real_escape_string($conn, $_POST['category_id'])."'" : 'NULL';
            mysqli_query($conn, "UPDATE posts SET title='$title', content='$content', category_id=$category_id WHERE id='$id'");
            $post_id = $_GET['edit'];
            echo "<div class='alert alert-success'>Пост обновлён</div>";
        } else {
$post_id = add_post($_POST['title'], $_POST['content'], $_POST['category_id'] ?? null);
    if (!$post_id) {
        echo "<div class='alert alert-danger'>Ошибка создания поста</div>";
        exit;
        }
        }
        if ($post_id) {
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                echo "<div class='alert alert-info'>Файлы получены: " . count($_FILES['attachments']['name']) . "</div>";
                $files = $_FILES['attachments'];
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK && $files['size'][$i] > 0) {
                        $file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        upload_file($file, $post_id);
                    } elseif ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                        echo "<div class='alert alert-danger'>Ошибка загрузки файла " . htmlspecialchars($files['name'][$i]) . ": " . $files['error'][$i] . "</div>";
                    }
                }
            } else {
                echo "<div class='alert alert-warning'>Файлы не были выбраны</div>";
            }
            // Прикрепление существующих файлов
            if (isset($_POST['existing_files']) && is_array($_POST['existing_files'])) {
                foreach ($_POST['existing_files'] as $file_id) {
                    if (is_numeric($file_id)) {
                        attach_file_to_post($file_id, $post_id);
                    }
                }
            }
        }
        $posts = get_posts();
    }

    // Удаление файла из поста
    if (isset($_GET['remove_file']) && is_numeric($_GET['remove_file']) && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        csrf_check();
        $file_id = $_GET['remove_file'];
        $post_id = $_GET['edit'];
        $conn = db_connect();
        $file_id = mysqli_real_escape_string($conn, $file_id);
        $post_id = mysqli_real_escape_string($conn, $post_id);
        $result = mysqli_query($conn, "UPDATE uploads SET post_id = NULL WHERE id = '$file_id' AND post_id = '$post_id'");
        if ($result && mysqli_affected_rows($conn) > 0) {
            echo "<div class='alert alert-success'>Файл откреплён от поста</div>";
        } else {
            echo "<div class='alert alert-danger'>Ошибка: файл не был откреплён. " . mysqli_error($conn) . "</div>";
        }
        $posts = get_posts();
    }
    
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        csrf_check();
        delete_post($_GET['delete']);
        $posts = get_posts();
        echo "<div class='alert alert-success'>Пост удалён</div>";
    }
    ?>
	
	<?php 
	function slugify($text) {
    return preg_replace('/^-+|-+$/', '', preg_replace('/[^a-z0-9]+/', '-', strtolower($text)));
}
?>
    <!-- HTML-код для отображения постов и модальных окон -->
<div class="mb-3">
	<button class="btn btn-modern mb-3" data-bs-toggle="modal" data-bs-target="#addPostModal">Добавить пост</button>
    <button class="btn btn-danger mb-3" id="bulkDeletePosts" disabled>Удалить выбранное</button>
</div>
<table class="table table-striped">
    <thead>
        <tr><th><input type="checkbox" id="selectAllPosts"></th><th>ID</th><th>Заголовок</th><th>Категория</th><th>Дата</th><th>Файлы</th><th>Действия</th></tr>
    </thead>
    <tbody>
        <?php foreach ($posts as $post): ?>
            <tr>
                <td><input type="checkbox" name="bulk[]" value="<?php echo $post['id']; ?>"></td>
                <td><?php echo $post['id']; ?></td>
                <td><?php echo htmlspecialchars($post['title']); ?></td>
                <td><?php echo htmlspecialchars($post['category_name'] ?? 'Без категории'); ?></td>
                <td><?php echo $post['created_at']; ?></td>
                <td>
                    <?php 
                    $files = get_post_files($post['id']);
                    echo count($files) . ' файл(ов)';
                    ?>
                </td>
                <td>
                    <a href="?action=posts&edit=<?php echo $post['id']; ?>" class="btn btn-sm btn-warning">Редактировать</a>
                    <a href="?action=posts&preview=<?php echo $post['id']; ?>" class="btn btn-sm btn-info" target="_blank">Предпросмотр</a>
                    <a href="?action=posts&delete=<?php echo $post['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить пост?')">Удалить</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<!-- Модальное окно для добавления поста -->
<div class="modal fade" id="addPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавить пост</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data" id="addPostForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Заголовок</label>
					<div class="mb-3">
						<input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($page['title'] ?? ''); ?>" required>
					</div>
					<div class="mb-3">
						<label for="slug" class="form-label">Slug</label>
						<input type="text" class="form-control" id="slug" name="slug" value="<?php echo htmlspecialchars($page['slug'] ?? ''); ?>">
					</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Категория</label>
                        <select name="category_id" class="form-select">
                            <option value="">Без категории</option>
                            <?php foreach (get_categories() as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Содержимое</label>
                        <div id="editor" style="height: 300px;"></div>
                        <input type="hidden" name="content" id="editorContent">
                    </div>
<div class="mb-3">
    <label class="form-label">Прикрепить новые файлы</label>
    <input type="file" name="attachments[]" class="form-control" id="newAttachmentsAdd" multiple>
    <div id="new-attached-files-add" class="mt-2"></div> <!-- Для новых файлов -->
</div>
                    <div class="mb-3">
                        <label class="form-label">Прикрепить существующие файлы</label>
                        <button type="button" class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#attachExistingModalAdd">Выбрать файлы</button>
                        <div id="attached-files-add" class="mt-2"></div>
                        <input type="hidden" name="existing_files[]" id="existingFilesAdd" multiple>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-modern">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>



<div class="modal fade" id="attachExistingModalAdd" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Выбрать существующие файлы</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
<div class="modal-body">
    <input type="text" class="form-control mb-3" id="fileSearchAttachAdd" placeholder="Поиск файлов...">
    <div class="file-list" id="fileListAdd">
        <?php 
        $all_files = get_all_files();
        if (empty($all_files)) {
            echo "<p>Файлов не найдено</p>";
        } else {
            foreach ($all_files as $file) {
                if (!$file['post_id']) {
                    echo "<div class='file-item' data-file-id='{$file['id']}'>
                            <input type='checkbox' class='form-check-input me-2' value='{$file['id']}'>
                            <a href='/uploads/{$file['filename']}' target='_blank'>" . htmlspecialchars($file['original_name']) . "</a>
                            (" . round($file['size']/1024, 2) . " KB)
                          </div>";
                }
            }
        }
        ?>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
    <button type="button" class="btn btn-modern" id="attachSelectedAdd">Прикрепить</button>
</div>
        </div>
    </div>
</div>

<div class="modal fade" id="attachExistingModalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Выбрать существующие файлы</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
<div class="modal-body">
    <input type="text" class="form-control mb-3" id="fileSearchAttachEdit" placeholder="Поиск файлов...">
    <div class="file-list" id="fileListEdit">
        <?php 
        $all_files = get_all_files();
        if (empty($all_files)) {
            echo "<p>Файлов не найдено</p>";
        } else {
            foreach ($all_files as $file) {
                if (!$file['post_id']) {
                    echo "<div class='file-item' data-file-id='{$file['id']}'>
                            <input type='checkbox' class='form-check-input me-2' value='{$file['id']}'>
                            <a href='/uploads/{$file['filename']}' target='_blank'>" . htmlspecialchars($file['original_name']) . "</a>
                            (" . round($file['size']/1024, 2) . " KB)
                          </div>";
                }
            }
        }
        ?>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
    <button type="button" class="btn btn-modern" id="attachSelectedEdit">Прикрепить</button>
</div>
        </div>
    </div>
</div>



<!-- Модальное окно для редактирования поста -->
<?php if (isset($_GET['edit']) && is_numeric($_GET['edit'])): 
    $post = array_filter($posts, fn($p) => $p['id'] == $_GET['edit']);
    $post = reset($post);
?>

    <div class="modal fade" id="editPostModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактировать пост</h5>
                    <a href="?action=posts" class="btn-close"></a>
                </div>
                <form method="post" enctype="multipart/form-data" id="editPostForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Заголовок</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Категория</label>
                            <select name="category_id" class="form-select">
                                <option value="">Без категории</option>
                                <?php foreach (get_categories() as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $post['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Содержимое</label>
                            <div id="editorEdit" style="height: 300px;"><?php echo $post['content']; ?></div>
                            <input type="hidden" name="content" id="editorEditContent">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Прикреплённые файлы</label>
                            <div id="attached-files-edit">
                                <?php foreach (get_post_files($post['id']) as $file): ?>
                                    <div class="file-item" data-file-id="<?php echo $file['id']; ?>">
                                        <a href="/uploads/<?php echo $file['filename']; ?>" target="_blank"><?php echo htmlspecialchars($file['original_name']); ?></a>
                                        (<?php echo round($file['size']/1024, 2); ?> KB)
                                        <button type="button" class="btn btn-sm btn-danger remove-file" data-file-id="<?php echo $file['id']; ?>">Удалить</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Прикрепить новые файлы</label>
                            <input type="file" name="attachments[]" class="form-control" multiple>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Прикрепить существующие файлы</label>
                            <button type="button" class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#attachExistingModalEdit">Выбрать файлы</button>
                            <div id="new-attached-files-edit" class="mt-2"></div>
                            <input type="hidden" name="existing_files[]" id="existingFilesEdit" multiple>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?action=posts" class="btn btn-secondary">Отмена</a>
                        <button type="submit" class="btn btn-modern">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
    <?php
    break;

            case 'comments':
                if (!auth_check('admin')) die("Доступ запрещён");
                $conn = db_connect();
                $result = mysqli_query($conn, "SELECT c.*, p.title, u.username FROM comments c LEFT JOIN posts p ON c.post_id = p.id LEFT JOIN users u ON c.user_id = u.id");
                $comments = mysqli_fetch_all($result, MYSQLI_ASSOC);
                if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
                    csrf_check();
                    update_comment_status($_GET['approve'], 'approved');
                    $comments = mysqli_fetch_all(mysqli_query($conn, "SELECT c.*, p.title, u.username FROM comments c LEFT JOIN posts p ON c.post_id = p.id LEFT JOIN users u ON c.user_id = u.id"), MYSQLI_ASSOC);
                    echo "<div class='alert alert-success'>Комментарий одобрен</div>";
                }
                if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
                    csrf_check();
                    delete_comment($_GET['delete']);
                    $comments = mysqli_fetch_all(mysqli_query($conn, "SELECT c.*, p.title, u.username FROM comments c LEFT JOIN posts p ON c.post_id = p.id LEFT JOIN users u ON c.user_id = u.id"), MYSQLI_ASSOC);
                    echo "<div class='alert alert-success'>Комментарий удалён</div>";
                }
                ?>
                <h1 class="mb-4">Комментарии</h1>
                <table class="table table-striped">
                    <thead>
                        <tr><th>ID</th><th>Пост</th><th>Автор</th><th>Текст</th><th>Статус</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $comment): ?>
                            <tr>
                                <td><?php echo $comment['id']; ?></td>
                                <td><?php echo htmlspecialchars($comment['title']); ?></td>
                                <td><?php echo htmlspecialchars($comment['username'] ?? 'Гость'); ?></td>
                                <td><?php echo htmlspecialchars($comment['content']); ?></td>
                                <td><?php echo $comment['status'] === 'approved' ? 'Одобрен' : 'Ожидает'; ?></td>
                                <td>
                                    <?php if ($comment['status'] !== 'approved'): ?>
                                        <a href="?action=comments&approve=<?php echo $comment['id']; ?>" class="btn btn-sm btn-success">Одобрить</a>
                                    <?php endif; ?>
                                    <a href="?action=comments&delete=<?php echo $comment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить комментарий?')">Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                break;

case 'pages':
    if (!auth_check('admin')) die("Доступ запрещён");
    $pages = get_pages();
	if (isset($_GET['preview']) && is_numeric($_GET['preview'])) {
        $page = get_page($_GET['preview']);
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Предпросмотр</title></head><body>";
        echo "<h1>" . htmlspecialchars($page['title']) . "</h1>";
        echo $page['content'];
        echo "</body></html>";
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && isset($_POST['content']) && isset($_POST['slug'])) {
        csrf_check();
        add_page($_POST['title'], $_POST['content'], $_POST['slug'], $_POST['use_template'] ?? 0, $_POST['is_home'] ?? 0);
        $pages = get_pages();
        echo "<div class='alert alert-success'>Страница добавлена</div>";
    }
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $page = get_page($_GET['edit']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();
            update_page($page['id'], $_POST['title'], $_POST['content'], $_POST['slug'], $_POST['use_template'] ?? 0, $_POST['is_home'] ?? 0);
            $pages = get_pages();
            echo "<div class='alert alert-success'>Страница обновлена</div>";
        }
    }
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        csrf_check();
        delete_page($_GET['delete']);
        $pages = get_pages();
        echo "<div class='alert alert-success'>Страница удалена</div>";
    }
    ?>
    <h1 class="mb-4">Страницы</h1>
    <button class="btn btn-modern mb-3" data-bs-toggle="modal" data-bs-target="#addPageModal">Добавить страницу</button>
    <table class="table table-striped">
        <thead>
            <tr><th>ID</th><th>Название</th><th>Слаг</th><th>Шаблон</th><th>Главная</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $page): ?>
                <tr>
                    <td><?php echo $page['id']; ?></td>
                    <td><?php echo htmlspecialchars($page['title']); ?></td>
                    <td><?php echo htmlspecialchars($page['slug']); ?></td>
                    <td><?php echo $page['use_template'] ? 'Да' : 'Нет'; ?></td>
                    <td><?php echo $page['is_home'] ? 'Да' : 'Нет'; ?></td>
                    <td>
						<a href="?action=pages&edit=<?php echo $page['id']; ?>" class="btn btn-sm btn-warning">Редактировать</a>
						<a href="?action=pages&preview=<?php echo $page['id']; ?>" class="btn btn-sm btn-info" target="_blank">Предпросмотр</a>
						<a href="?action=pages&delete=<?php echo $page['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить страницу?')">Удалить</a>
					</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Модальное окно для добавления страницы -->
    <div class="modal fade" id="addPageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить страницу</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="addPageForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Название</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Слаг</label>
                            <input type="text" name="slug" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Содержимое</label>
                            <div id="pageEditor" style="height: 300px;"></div>
                            <input type="hidden" name="content" id="pageEditorContent">
                        </div>
                        <div class="mb-3">
                            <label class="form-check-label">
                                <input type="checkbox" name="use_template" value="1" checked> Использовать шаблон
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-check-label">
                                <input type="checkbox" name="is_home" value="1"> Главная страница
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-modern">Добавить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['edit'])): ?>
    <div class="modal fade show" id="editPageModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактировать страницу</h5>
                    <a href="?action=pages" class="btn-close"></a>
                </div>
                <form method="post" id="editPageForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Название</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($page['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Слаг</label>
                            <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($page['slug']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Содержимое</label>
                            <div id="editPageEditor" style="height: 300px;"><?php echo $page['content']; ?></div>
                            <input type="hidden" name="content" id="editPageEditorContent">
                        </div>
                        <div class="mb-3">
                            <label class="form-check-label">
                                <input type="checkbox" name="use_template" value="1" <?php echo $page['use_template'] ? 'checked' : ''; ?>> Использовать шаблон
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-check-label">
                                <input type="checkbox" name="is_home" value="1" <?php echo $page['is_home'] ? 'checked' : ''; ?>> Главная страница
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?action=pages" class="btn btn-secondary">Отмена</a>
                        <button type="submit" class="btn btn-modern">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php
    break;
            case 'users':
                if (!auth_check('admin')) die("Доступ запрещён");
                $users = get_users();
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['email']) && isset($_POST['password'])) {
                    csrf_check();
                    add_user($_POST['username'], $_POST['password'], $_POST['email'], $_POST['role']);
                    $users = get_users();
                    echo "<div class='alert alert-success'>Пользователь добавлен</div>";
                }
                if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
                    $user = get_user($_GET['edit']);
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        csrf_check();
                        update_user($user['id'], $_POST['username'], $_POST['email'], $_POST['role'], $_POST['password'] ?? null);
                        $users = get_users();
                        echo "<div class='alert alert-success'>Пользователь обновлён</div>";
                    }
                }
                if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
                    csrf_check();
                    delete_user($_GET['delete']);
                    $users = get_users();
                    echo "<div class='alert alert-success'>Пользователь удалён</div>";
                }
                ?>
                <h1 class="mb-4">Пользователи</h1>
                <button class="btn btn-modern mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">Добавить пользователя</button>
                <table class="table table-striped">
                    <thead>
                        <tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['role']; ?></td>
                                <td>
                                    <a href="?action=users&edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                    <a href="?action=users&delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить пользователя?')">Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="modal fade" id="addUserModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Добавить пользователя</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Логин</label>
                                        <input type="text" name="username" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Пароль</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Роль</label>
                                        <select name="role" class="form-select">
                                            <option value="user">Пользователь</option>
                                            <option value="editor">Редактор</option>
                                            <option value="admin">Администратор</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="submit" class="btn btn-modern">Добавить</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php if (isset($_GET['edit'])): ?>
                    <div class="modal fade show" id="editUserModal" tabindex="-1" style="display: block;">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Редактировать пользователя</h5>
                                    <a href="?action=users" class="btn-close"></a>
                                </div>
                                <form method="post">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Логин</label>
                                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Пароль (оставьте пустым, если не меняете)</label>
                                            <input type="password" name="password" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Роль</label>
                                            <select name="role" class="form-select">
                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Пользователь</option>
                                                <option value="editor" <?php echo $user['role'] === 'editor' ? 'selected' : ''; ?>>Редактор</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Администратор</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="?action=users" class="btn btn-secondary">Отмена</a>
                                        <button type="submit" class="btn btn-modern">Сохранить</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
                break;

            case 'categories':
                if (!auth_check('admin')) die("Доступ запрещён");
                $categories = get_categories();
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name']) && isset($_POST['slug'])) {
                    csrf_check();
                    add_category($_POST['name'], $_POST['slug'], $_POST['description'] ?? '');
                    $categories = get_categories();
                    echo "<div class='alert alert-success'>Категория добавлена</div>";
                }
                if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
                    $category = get_category($_GET['edit']);
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        csrf_check();
                        update_category($category['id'], $_POST['name'], $_POST['slug'], $_POST['description'] ?? '');
                        $categories = get_categories();
                        echo "<div class='alert alert-success'>Категория обновлена</div>";
                    }
                }
                if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
                    csrf_check();
                    delete_category($_GET['delete']);
                    $categories = get_categories();
                    echo "<div class='alert alert-success'>Категория удалена</div>";
                }
                ?>
                <h1 class="mb-4">Категории</h1>
                <button class="btn btn-modern mb-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal">Добавить категорию</button>
                <table class="table table-striped">
                    <thead>
                        <tr><th>ID</th><th>Название</th><th>Слаг</th><th>Описание</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                <td><?php echo htmlspecialchars($category['description']); ?></td>
                                <td>
                                    <a href="?action=categories&edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                    <a href="?action=categories&delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить категорию?')">Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="modal fade" id="addCategoryModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Добавить категорию</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Название</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Слаг</label>
                                        <input type="text" name="slug" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Описание</label>
                                        <textarea name="description" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="submit" class="btn btn-modern">Добавить</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php if (isset($_GET['edit'])): ?>
                    <div class="modal fade show" id="editCategoryModal" tabindex="-1" style="display: block;">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Редактировать категорию</h5>
                                    <a href="?action=categories" class="btn-close"></a>
                                </div>
                                <form method="post">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Название</label>
                                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Слаг</label>
                                            <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($category['slug']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Описание</label>
                                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($category['description']); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="?action=categories" class="btn btn-secondary">Отмена</a>
                                        <button type="submit" class="btn btn-modern">Сохранить</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
                break;
case 'themes':
    if (!auth_check('admin')) die("Доступ запрещён");
    $active_theme = get_active_theme();
    // Получаем список тем, исключая папку backups более строго
    $themes = array_filter(
        array_map('basename', glob(__DIR__ . '/../themes/*', GLOB_ONLYDIR)),
        fn($theme) => $theme !== 'backups' && is_dir(__DIR__ . "/../themes/$theme")
    );
    $domain = "http://" . $_SERVER['HTTP_HOST'];

    // Функция для вывода уведомлений через JS
    function showNotification($message, $type = 'success') {
        ?>
        <script>
            const alert = document.createElement('div');
            alert.className = 'alert alert-<?php echo $type; ?>';
            alert.textContent = '<?php echo addslashes($message); ?>';
            document.body.appendChild(alert);
            setTimeout(() => alert.classList.add('show'), 10);
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 500);
            }, 3000);
        </script>
        <?php
    }


    // Предпросмотр (без изменений)
    if (isset($_POST['preview']) && $_POST['preview'] == 1 && isset($_GET['edit']) && in_array($_GET['edit'], $themes)) {
        $theme = $_GET['edit'];
        $file = $_POST['file'] ?? $_POST['asset'] ?? '';
        $is_asset = isset($_POST['asset']);
        $content = $_POST['content'] ?? '';

        if (empty($file) || empty($content)) {
            showNotification('Файл или содержимое отсутствует', 'danger');
            exit;
        }

        $temp_file = __DIR__ . "/../themes/$theme/temp_preview.php";
        if (!is_writable(__DIR__ . "/../themes/$theme")) {
            showNotification('Нет прав на запись в директорию', 'danger');
            exit;
        }

        if ($is_asset) {
            if (strpos($file, '.css') !== false) {
                $output = "<style>$content</style><p>Пример текста для CSS предпросмотра</p>";
            } elseif (strpos($file, '.js') !== false) {
                $output = "<script>$content</script><p>Проверьте консоль для JS предпросмотра</p>";
            } else {
                $output = "<pre>" . htmlspecialchars($content) . "</pre>";
            }
        } else {
            $content = '<?php require_once __DIR__ . "/../../core/app.php"; $theme_data = get_theme_data(); echo "Предпросмотр PHP работает"; ?>' . $content;
            file_put_contents($temp_file, $content);
            ob_start();
            include $temp_file;
            $output = ob_get_clean();
            unlink($temp_file);
        }

        echo $output ?: "<div class='alert alert-warning'>Предпросмотр пуст (возможно, файл не возвращает вывод)</div>";
        exit;
    }

    // Активация темы
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
        csrf_check();
        set_active_theme($_POST['theme']);
        $active_theme = $_POST['theme'];
        showNotification('Тема активирована');
    }

    // Удаление темы
	if (isset($_GET['delete']) && in_array($_GET['delete'], $themes) && $_GET['delete'] !== $active_theme) {
		csrf_check();
		if (delete_theme($_GET['delete'])) {
			showNotification('Тема удалена');
			$themes = array_filter(
				array_map('basename', glob(__DIR__ . '/../themes/*', GLOB_ONLYDIR)),
				fn($theme) => $theme !== 'backups' && is_dir(__DIR__ . "/../themes/$theme")
			);
		} else {
			showNotification('Ошибка удаления темы', 'danger');
		}
	}

    // Создание темы
    if (isset($_GET['create']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme_name'])) {
        csrf_check();
        if (create_theme($_POST['theme_name'])) {
            showNotification('Тема создана');
            $themes = array_filter(
                array_map('basename', glob(__DIR__ . '/../themes/*', GLOB_ONLYDIR)),
                fn($theme) => $theme !== 'backups' && is_dir(__DIR__ . "/../themes/$theme")
            );
        } else {
            showNotification('Тема уже существует', 'danger');
        }
    }

    // Конвертация темы из ZIP
    if (isset($_GET['convert']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_theme'])) {
        csrf_check();
        $zip_file = $_FILES['zip_theme'];
        $allowed_mime_types = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
        if (in_array($zip_file['type'], $allowed_mime_types) && $zip_file['size'] <= 10485760 && $zip_file['size'] > 0) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file['tmp_name']) === true) {
                $theme_name = pathinfo($zip_file['name'], PATHINFO_FILENAME);
                $theme_dir = __DIR__ . "/../themes/$theme_name";
                if (!is_dir($theme_dir)) {
                    mkdir($theme_dir);
                    mkdir("$theme_dir/assets");
                    $zip->extractTo($theme_dir);
                    $zip->close();
                    $index_content = file_exists("$theme_dir/index.html") ? file_get_contents("$theme_dir/index.html") : '';
                    if ($index_content) {
                        file_put_contents("$theme_dir/template.php", '<?php require_once __DIR__ . "/../../core/app.php"; ?>' . $index_content);
                        unlink("$theme_dir/index.html");
                    }
                    $css_files = glob("$theme_dir/*.css");
                    foreach ($css_files as $css) {
                        rename($css, "$theme_dir/assets/" . basename($css));
                    }
                    $js_files = glob("$theme_dir/*.js");
                    foreach ($js_files as $js) {
                        rename($js, "$theme_dir/assets/" . basename($js));
                    }
                    showNotification('Тема конвертирована');
                    $themes = array_filter(
                        array_map('basename', glob(__DIR__ . '/../themes/*', GLOB_ONLYDIR)),
                        fn($theme) => $theme !== 'backups' && is_dir(__DIR__ . "/../themes/$theme")
                    );
                } else {
                    showNotification('Тема с таким именем уже существует', 'danger');
                }
            } else {
                showNotification('Ошибка открытия ZIP-архива', 'danger');
            }
        } else {
            showNotification('Недопустимый файл или размер превышает 10MB', 'danger');
        }
    }

    // Импорт темы
    if (isset($_GET['import']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_theme'])) {
        csrf_check();
        $zip_file = $_FILES['import_theme'];
        if (in_array($zip_file['type'], ['application/zip', 'application/x-zip-compressed', 'application/octet-stream']) && $zip_file['size'] <= 10485760) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file['tmp_name']) === true) {
                $theme_name = pathinfo($zip_file['name'], PATHINFO_FILENAME);
                $theme_dir = __DIR__ . "/../themes/$theme_name";
                if (!is_dir($theme_dir)) {
                    $zip->extractTo($theme_dir);
                    $zip->close();
                    showNotification('Тема импортирована');
                    $themes = array_filter(
                        array_map('basename', glob(__DIR__ . '/../themes/*', GLOB_ONLYDIR)),
                        fn($theme) => $theme !== 'backups' && is_dir(__DIR__ . "/../themes/$theme")
                    );
                } else {
                    showNotification('Тема с таким именем уже существует', 'danger');
                }
            } else {
                showNotification('Ошибка открытия ZIP-архива', 'danger');
            }
        } else {
            showNotification('Недопустимый файл или размер превышает 10MB', 'danger');
        }
    }

    // Бэкап темы
// Бэкап темы
if (isset($_GET['backup']) && in_array($_GET['backup'], $themes)) {
    csrf_check();
    $theme = $_GET['backup'];
    $backup_dir = __DIR__ . '/../themes/backups/';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
    $backup_file = $backup_dir . $theme . '_' . date('Ymd_His') . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($backup_file, ZipArchive::CREATE) === true) {
        $theme_path = realpath(__DIR__ . "/../themes/$theme"); // Абсолютный путь к теме
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file_path = $file->getRealPath();
            // Пропускаем нежелательные файлы и папки
            if (strpos($file_path, '.git') !== false || $file->getBasename() === '.DS_Store') {
                continue;
            }

            // Формируем относительный путь относительно корня темы
            $relative_path = substr($file_path, strlen($theme_path) + 1);
            
            if ($file->isDir()) {
                // Добавляем пустую папку в архив
                $zip->addEmptyDir($relative_path);
            } else {
                // Добавляем файл с правильным относительным путём
                $zip->addFile($file_path, $relative_path);
            }
        }

        $zip->close();
        if (file_exists($backup_file)) {
            showNotification('Бэкап создан');
        } else {
            showNotification('Ошибка: бэкап не был создан', 'danger');
        }
    } else {
        showNotification('Ошибка создания бэкапа', 'danger');
    }
}

    // Восстановление из бэкапа
    if (isset($_GET['restore']) && file_exists(__DIR__ . '/../themes/backups/' . $_GET['restore'])) {
        csrf_check();
        $backup_file = __DIR__ . '/../themes/backups/' . $_GET['restore'];
        $theme_name = explode('_', basename($_GET['restore'], '.zip'))[0];
        $theme_dir = __DIR__ . "/../themes/$theme_name";
        if (is_dir($theme_dir)) rmdir_recursive($theme_dir);
        $zip = new ZipArchive();
        if ($zip->open($backup_file) === true) {
            $zip->extractTo($theme_dir);
            $zip->close();
            showNotification('Тема восстановлена');
            $themes = array_filter(
                array_map('basename', glob(__DIR__ . '/../themes/*', GLOB_ONLYDIR)),
                fn($theme) => $theme !== 'backups' && is_dir(__DIR__ . "/../themes/$theme")
            );
        } else {
            showNotification('Ошибка восстановления', 'danger');
        }
    }

    // Удаление бэкапа
    if (isset($_GET['delete_backup']) && file_exists(__DIR__ . '/../themes/backups/' . $_GET['delete_backup'])) {
        csrf_check();
        $backup_file = __DIR__ . '/../themes/backups/' . $_GET['delete_backup'];
        unlink($backup_file);
        showNotification('Бэкап удалён');
    }

    if (isset($_GET['edit']) && in_array($_GET['edit'], $themes)) {
        $theme = $_GET['edit'];
        $files = scandir(__DIR__ . "/../themes/$theme");
        $files = array_filter($files, fn($f) => !in_array($f, ['.', '..', 'assets']));
        $assets = scandir(__DIR__ . "/../themes/$theme/assets");
        $assets = array_filter($assets, fn($f) => !in_array($f, ['.', '..']));

        // Создание файла
        if (isset($_POST['create_file']) && isset($_POST['file_name']) && isset($_POST['file_type'])) {
            csrf_check();
            $file_name = $_POST['file_name'] . $_POST['file_type'];
            $target_dir = $_POST['file_type'] === '.php' ? __DIR__ . "/../themes/$theme/" : __DIR__ . "/../themes/$theme/assets/";
            $target_file = $target_dir . $file_name;
            if (!file_exists($target_file)) {
                file_put_contents($target_file, '');
                showNotification('Файл создан');
            } else {
                showNotification('Файл уже существует', 'danger');
            }
        }

        // Переименование файла
        if (isset($_POST['rename_file']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
            csrf_check();
            $old_name = $_POST['old_name'];
            $new_name = $_POST['new_name'];
            $is_asset = strpos($old_name, '.') !== false && substr($old_name, -4) !== '.php';
            $old_path = __DIR__ . "/../themes/$theme/" . ($is_asset ? 'assets/' : '') . $old_name;
            $new_path = __DIR__ . "/../themes/$theme/" . ($is_asset ? 'assets/' : '') . $new_name;
            if (file_exists($old_path) && !file_exists($new_path)) {
                rename($old_path, $new_path);
                showNotification('Файл переименован');
            } else {
                showNotification('Ошибка переименования', 'danger');
            }
        }

        // Удаление файла
        if (isset($_POST['delete_file']) && isset($_POST['file_name'])) {
            csrf_check();
            $file_name = $_POST['file_name'];
            $is_asset = strpos($file_name, '.') !== false && substr($file_name, -4) !== '.php';
            $file_path = __DIR__ . "/../themes/$theme/" . ($is_asset ? 'assets/' : '') . $file_name;
            if (file_exists($file_path)) {
                unlink($file_path);
                showNotification('Файл удалён');
            } else {
                showNotification('Файл не найден', 'danger');
            }
        }

        // Перемещение файла внутри темы
        if (isset($_POST['move_file']) && isset($_POST['file_name']) && isset($_POST['destination'])) {
            csrf_check();
            $file_name = $_POST['file_name'];
            $dest = $_POST['destination'];
            $is_asset = strpos($file_name, '.') !== false && substr($file_name, -4) !== '.php';
            $old_path = __DIR__ . "/../themes/$theme/" . ($is_asset ? 'assets/' : '') . $file_name;
            $new_path = __DIR__ . "/../themes/$theme/$dest/" . $file_name;
            if (file_exists($old_path) && is_dir(__DIR__ . "/../themes/$theme/$dest") && !file_exists($new_path)) {
                rename($old_path, $new_path);
                showNotification('Файл перемещён');
            } else {
                showNotification('Ошибка перемещения', 'danger');
            }
        }

        // Сохранение файла
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file']) && isset($_POST['content'])) {
            csrf_check();
            file_put_contents(__DIR__ . "/../themes/$theme/{$_POST['file']}", $_POST['content']);
            showNotification('Файл сохранён');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asset']) && isset($_POST['content'])) {
            csrf_check();
            file_put_contents(__DIR__ . "/../themes/$theme/assets/{$_POST['asset']}", $_POST['content']);
            showNotification('Файл сохранён');
        }

        ?>
        <div class="theme-explorer">
            <div class="file-list">
                <h6 class="mt-3">Файлы темы</h6>
                <input type="text" class="form-control mb-2" placeholder="Поиск..." id="fileSearch">
                <?php foreach ($files as $file): ?>
                    <div class="file-item <?php echo isset($_GET['file']) && $_GET['file'] === $file ? 'active' : ''; ?>">
                        <i class="fas fa-file-code me-2"></i>
                        <a href="?action=themes&edit=<?php echo urlencode($theme); ?>&file=<?php echo urlencode($file); ?>"><?php echo htmlspecialchars($file); ?></a>
                        <div class="file-actions ms-2">
                            <i class="fas fa-edit" data-bs-toggle="modal" data-bs-target="#renameFileModal" data-file="<?php echo htmlspecialchars($file); ?>" title="Переименовать"></i>
                            <i class="fas fa-trash" data-bs-toggle="modal" data-bs-target="#deleteFileModal" data-file="<?php echo htmlspecialchars($file); ?>" title="Удалить"></i>
                            <i class="fas fa-arrow-right" data-bs-toggle="modal" data-bs-target="#moveFileModal" data-file="<?php echo htmlspecialchars($file); ?>" title="Переместить"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($assets as $asset): ?>
                    <div class="file-item <?php echo isset($_GET['asset']) && $_GET['asset'] === $asset ? 'active' : ''; ?>">
                        <i class="fas <?php echo strpos($asset, '.css') !== false ? 'fa-css3' : (strpos($asset, '.js') !== false ? 'fa-js' : 'fa-image'); ?> me-2"></i>
                        <a href="?action=themes&edit=<?php echo urlencode($theme); ?>&asset=<?php echo urlencode($asset); ?>"><?php echo htmlspecialchars($asset); ?></a>
                        <div class="file-actions ms-2">
                            <i class="fas fa-edit" data-bs-toggle="modal" data-bs-target="#renameFileModal" data-file="<?php echo htmlspecialchars($asset); ?>" title="Переименовать"></i>
                            <i class="fas fa-trash" data-bs-toggle="modal" data-bs-target="#deleteFileModal" data-file="<?php echo htmlspecialchars($asset); ?>" title="Удалить"></i>
                            <i class="fas fa-arrow-right" data-bs-toggle="modal" data-bs-target="#moveFileModal" data-file="<?php echo htmlspecialchars($asset); ?>" title="Переместить"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn-modern mt-3 w-100" data-bs-toggle="modal" data-bs-target="#createFileModal" title="Создать новый файл">
                <i class="fas fa-file-plus me-2"></i> Создать файл
            </button>
        </div>
        <div class="editor-area">
<div class="editor-header">
    <div>
        <h3><?php echo htmlspecialchars($_GET['file'] ?? $_GET['asset'] ?? 'Выберите файл'); ?></h3>
        <p class="hint"><?php echo isset($_GET['file']) && $_GET['file'] === 'header.php' ? 'Шапка сайта' : 'Файл темы'; ?></p>
    </div>
    <div class="d-flex gap-2 align-items-center">
<button class="btn btn-modern" title="Сохранить (Ctrl+S)">
    <i class="fas fa-save me-1"></i> Сохранить
</button>
        <button class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#previewModal" title="Предпросмотр">
            <i class="fas fa-eye me-1"></i> Предпросмотр
        </button>
        <a href="?action=themes&edit=<?php echo urlencode($theme); ?>&export" class="btn btn-modern" title="Экспорт темы">
            <i class="fas fa-download me-1"></i> Экспорт
        </a>
        <a href="?action=themes&backup=<?php echo urlencode($theme); ?>" class="btn btn-secondary" title="Создать бэкап">
            <i class="fas fa-archive me-1"></i> Бэкап
        </a>
        <?php if ($theme !== $active_theme): ?>
            <form method="post" class="d-inline" id="activateForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="theme" value="<?php echo htmlspecialchars($theme); ?>">
                <button type="submit" class="btn btn-success" title="Активировать">
                    <i class="fas fa-check me-1"></i> Активировать
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
            <?php if (isset($_GET['file']) || isset($_GET['asset'])): 
                $edit_file = $_GET['file'] ?? $_GET['asset'];
                $is_asset = isset($_GET['asset']);
                $file_path = __DIR__ . "/../themes/$theme/" . ($is_asset ? "assets/" : "") . $edit_file;
                $content = file_get_contents($file_path);
                $mode = strpos($edit_file, '.php') !== false ? 'php' : (strpos($edit_file, '.css') !== false ? 'css' : (strpos($edit_file, '.js') !== false ? 'javascript' : 'text/plain'));
            ?>
                <div class="editor fade-in">
                    <textarea class="code-editor-theme" id="codeEditor" data-mode="<?php echo $mode; ?>" data-file="<?php echo htmlspecialchars($edit_file); ?>" data-is-asset="<?php echo $is_asset ? '1' : '0'; ?>"><?php echo htmlspecialchars($content); ?></textarea>
                </div>
            <?php endif; ?>
        </div>
        <!-- Модальные окна для файлов -->
        <div class="modal fade" id="createFileModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Создать файл</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Имя файла</label>
                                <input type="text" name="file_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Тип файла</label>
                                <select name="file_type" class="form-select">
                                    <option value=".php">PHP</option>
                                    <option value=".css">CSS</option>
                                    <option value=".js">JavaScript</option>
                                    <option value=".png">PNG</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" name="create_file" class="btn btn-modern">Создать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal fade" id="renameFileModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Переименовать файл</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="old_name" id="renameOldName">
                            <div class="mb-3">
                                <label class="form-label">Новое имя</label>
                                <input type="text" name="new_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" name="rename_file" class="btn btn-modern">Переименовать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal fade" id="deleteFileModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Удалить файл</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="file_name" id="deleteFileName">
                            <p>Вы уверены, что хотите удалить файл <strong id="deleteFileDisplay"></strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" name="delete_file" class="btn btn-danger">Удалить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal fade" id="moveFileModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Переместить файл</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="file_name" id="moveFileName">
                            <div class="mb-3">
                                <label class="form-label">Переместить в папку</label>
                                <select name="destination" class="form-select">
                                    <option value="">Корень темы</option>
                                    <option value="assets">assets</option>
                                    <?php if (is_dir(__DIR__ . "/../themes/$theme/js")): ?>
                                        <option value="js">js</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" name="move_file" class="btn btn-modern">Переместить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal fade" id="previewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Предпросмотр</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="previewModalBody">
                        <!-- Контент предпросмотра будет загружен через JS -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
} else {
    $backups_dir = __DIR__ . '/../themes/backups/';
    $backups = is_dir($backups_dir) ? glob($backups_dir . '*.zip') : [];
    
    // Определяем порядок сортировки через GET-параметр (по умолчанию desc — от новых к старым)
    $sort_order = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'asc' : 'desc';
    
    // Сортируем бэкапы по времени изменения
    usort($backups, function($a, $b) use ($sort_order) {
        if ($sort_order === 'asc') {
            return filemtime($a) - filemtime($b); // От старых к новым
        } else {
            return filemtime($b) - filemtime($a); // От новых к старым
        }
    });

    // Определяем активную вкладку
    $active_tab = $_GET['tab'] ?? 'themesList'; // По умолчанию "Списки тем"
    
    ?>
    <style>
        .tab-content .table { width: 100% !important; margin: 0 !important; }
        .tab-content .table th, .tab-content .table td { padding: 0.75rem; }
        .tab-content { padding: 0 !important; }
        .d-flex.flex-column.align-items-start { width: 100%; padding-right: 0; }
        .sortable:hover { cursor: pointer; color: #6c63ff; }
        .sort-icon { margin-left: 5px; }
    </style>
    <div class="d-flex flex-column align-items-start">
        <h1 class="mb-3">Темы</h1>
        <div class="mb-3">
            <button class="btn btn-modern me-2 mb-2" data-bs-toggle="modal" data-bs-target="#createThemeModal">Создать тему</button>
            <button class="btn btn-modern me-2 mb-2" data-bs-toggle="modal" data-bs-target="#convertThemeModal">Конвертировать</button>
            <button class="btn btn-modern mb-2" data-bs-toggle="modal" data-bs-target="#importThemeModal">Импортировать</button>
        </div>
        <ul class="nav nav-tabs mb-0" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab === 'themesList' ? 'active' : ''; ?>" id="themesList-tab" data-bs-toggle="tab" href="#themesList" role="tab" aria-controls="themesList" aria-selected="<?php echo $active_tab === 'themesList' ? 'true' : 'false'; ?>">Списки тем</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab === 'backupsList' ? 'active' : ''; ?>" id="backupsList-tab" data-bs-toggle="tab" href="#backupsList" role="tab" aria-controls="backupsList" aria-selected="<?php echo $active_tab === 'backupsList' ? 'true' : 'false'; ?>">Бэкапы</a>
            </li>
        </ul>
        <div class="tab-content w-100">
            <div class="tab-pane fade <?php echo $active_tab === 'themesList' ? 'show active' : ''; ?>" id="themesList" role="tabpanel" aria-labelledby="themesList-tab">
                <table class="table table-striped">
                    <thead>
                        <tr><th>Название</th><th>Статус</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($themes as $theme): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($theme); ?></td>
                                <td><?php echo $theme === $active_theme ? 'Активна' : 'Неактивна'; ?></td>
                                <td>
<td>
    <div class="btn-group" role="group">
        <?php if ($theme !== $active_theme): ?>
            <form method="post" class="d-inline me-1">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="theme" value="<?php echo htmlspecialchars($theme); ?>">
                <button type="submit" class="btn btn-sm btn-success" title="Активировать">
                    <i class="fas fa-check"></i>
                </button>
            </form>
            <a href="?action=themes&delete=<?php echo urlencode($theme); ?>" class="btn btn-sm btn-danger me-1" onclick="return confirm('Удалить тему?')" title="Удалить">
                <i class="fas fa-trash"></i>
            </a>
        <?php endif; ?>
        <a href="?action=themes&edit=<?php echo urlencode($theme); ?>" class="btn btn-sm btn-warning me-1" title="Редактировать">
            <i class="fas fa-edit"></i>
        </a>
        <a href="?action=themes&preview_theme=<?php echo urlencode($theme); ?>" class="btn btn-sm btn-info" target="_blank" title="Предпросмотр">
            <i class="fas fa-eye"></i>
        </a>
    </div>
</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade <?php echo $active_tab === 'backupsList' ? 'show active' : ''; ?>" id="backupsList" role="tabpanel" aria-labelledby="backupsList-tab">
                    <?php if (!empty($backups)): ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Имя файла</th>
                                    <th>Размер</th>
                                    <th>
                                        <a href="?action=themes&tab=backupsList&sort=<?php echo $sort_order === 'desc' ? 'asc' : 'desc'; ?>" class="sortable">
                                            Дата
                                            <i class="fas fa-sort<?php echo $sort_order === 'desc' ? '-down' : '-up'; ?> sort-icon"></i>
                                        </a>
                                    </th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): 
                                    $filename = basename($backup);
                                    $size = filesize($backup) / 1024; // KB
                                    $date = date('Y-m-d H:i:s', filemtime($backup));
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($filename); ?></td>
                                        <td><?php echo round($size, 2); ?> KB</td>
                                        <td><?php echo $date; ?></td>
                                        <td>
                                            <a href="?action=themes&restore=<?php echo urlencode($filename); ?>" class="btn btn-sm btn-success" onclick="return confirm('Восстановить бэкап?')">Восстановить</a>
                                            <a href="<?php echo $domain . '/themes/backups/' . urlencode($filename); ?>" class="btn btn-sm btn-primary ms-2" download>Скачать</a>
                                            <a href="?action=themes&delete_backup=<?php echo urlencode($filename); ?>" class="btn btn-sm btn-danger ms-2" onclick="return confirm('Удалить бэкап?')">Удалить</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Бэкапов пока нет.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="modal fade" id="createThemeModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Создать тему</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post" action="?action=themes&create">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Название темы</label>
                                <input type="text" name="theme_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-modern">Создать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal fade" id="convertThemeModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Конвертировать тему из ZIP</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="?action=themes&convert">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <label class="form-label">ZIP-архив темы</label>
                                <input type="file" name="zip_theme" class="form-control" accept=".zip" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-modern">Конвертировать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal fade" id="importThemeModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Импортировать тему</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="?action=themes&import">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <label class="form-label">ZIP-архив темы</label>
                                <input type="file" name="import_theme" class="form-control" accept=".zip" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-modern">Импортировать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php
}
    break;
	
// Добавляем новый case перед case 'plugins':
case 'filemanager':
    if (!auth_check('admin')) die("Доступ запрещён");
    $files = get_all_files();
    
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        csrf_check();
        if (delete_file($_GET['delete'])) {
            echo "<div class='alert alert-success'>Файл удалён</div>";
            $files = get_all_files();
        }
    }

	if (isset($_GET['upload']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
		csrf_check();
		foreach ($_FILES['attachments']['name'] as $i => $name) {
			if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
				$file = [
					'name' => $name,
					'type' => $_FILES['attachments']['type'][$i],
					'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
					'error' => $_FILES['attachments']['error'][$i],
					'size' => $_FILES['attachments']['size'][$i]
				];
				upload_file($file, null); // Функция upload_file уже есть в коде
			}
		}
		exit;
	}

    if (isset($_GET['attach']) && is_numeric($_GET['attach']) && isset($_GET['post_id']) && is_numeric($_GET['post_id'])) {
        csrf_check();
        if (attach_file_to_post($_GET['attach'], $_GET['post_id'])) {
            echo "<div class='alert alert-success'>Файл прикреплён к посту</div>";
            $files = get_all_files();
        }
    }
	if (isset($_GET['bulk_delete'])) {
    csrf_check();
    $ids = explode(',', $_GET['bulk_delete']);
    foreach ($ids as $id) {
        if (is_numeric($id)) delete_file($id);
    }
    echo "<div class='alert alert-success'>Выбранные файлы удалены</div>";
    $files = get_all_files();
}
    ?>
    <h1 class="mb-4">Файловый менеджер</h1>
    <div class="row mb-3">
        <div class="col-md-4">
            
        </div>
    </div>
	<div class="mb-3">

    <input type="file" id="multiUpload" class="form-control" multiple>
    <div id="uploadProgress" class="progress mt-2" style="display: none;">
        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
    </div>
</div>

<div id="contextMenu" class="dropdown-menu" style="position: absolute; display: none;">
    <a class="dropdown-item context-download">Скачать</a>
    <a class="dropdown-item context-copy">Копировать ссылку</a>
    <a class="dropdown-item context-delete">Удалить</a>
</div>

<button class="btn btn-danger mb-3" id="bulkDeleteFiles" disabled>Удалить выбранное</button>

    <table class="table table-striped">
<thead>
    <tr>
        <th><input type="checkbox" id="selectAllFiles"></th>
        <th>ID</th>
        <th class="sortable" data-sort="name">Имя файла <i class="fas fa-sort"></i></th>
        <th class="sortable" data-sort="size">Размер <i class="fas fa-sort"></i></th>
        <th>Тип</th>
        <th class="sortable" data-sort="date">Дата загрузки <i class="fas fa-sort"></i></th>
        <th>Загрузил</th>
        <th>Прикреплён к посту</th>
        <th>Действия</th>
    </tr>
</thead>
        <tbody id="fileTable">
            <?php foreach ($files as $file): ?>
                <tr>
				<td><input type="checkbox" name="fileIds[]" value="<?php echo $file['id']; ?>"></td>
                    <td><?php echo $file['id']; ?></td>
<td>
    <a href="/uploads/<?php echo $file['filename']; ?>" target="_blank" 
       data-bs-toggle="tooltip" data-bs-html="true" 
       data-file="<?php echo $file['filename']; ?>" 
       data-type="<?php echo $file['mime_type']; ?>" 
       class="file-tooltip">
        <?php echo htmlspecialchars($file['original_name']); ?>
    </a>
</td>
                    <td><?php echo round($file['size']/1024, 2); ?> KB</td>
                    <td><?php echo $file['mime_type']; ?></td>
                    <td><?php echo $file['upload_date']; ?></td>
                    <td><?php echo htmlspecialchars($file['username']); ?></td>
                    <td>
                        <?php echo $file['post_id'] ? 
                            '<a href="?action=posts&edit=' . $file['post_id'] . '">' . htmlspecialchars($file['post_title']) . '</a>' : 
                            'Не прикреплён'; 
                        ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="/uploads/<?php echo $file['filename']; ?>" class="btn btn-sm btn-primary" download="<?php echo htmlspecialchars($file['original_name']); ?>">Скачать</a>
							<a href="/uploads/<?php echo $file['filename']; ?>" class="btn btn-sm btn-info preview-btn" data-file="<?php echo $file['filename']; ?>" data-type="<?php echo $file['mime_type']; ?>" data-bs-toggle="modal" data-bs-target="#previewFileModal">Предпросмотр</a>
                            <?php if (!$file['post_id']): ?>
                                <button class="btn btn-sm btn-success attach-btn" 
                                        data-file-id="<?php echo $file['id']; ?>" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#attachModal">Прикрепить</button>
                            <?php endif; ?>
                            <a href="?action=filemanager&delete=<?php echo $file['id']; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Удалить файл?')">Удалить</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>


<div class="modal fade" id="previewFileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Предпросмотр файла</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewFileContent"></div>
        </div>
    </div>
</div>

    <!-- Модальное окно для прикрепления файла -->
    <div class="modal fade" id="attachModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Прикрепить файл к посту</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="get" action="?action=filemanager">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="filemanager">
                        <input type="hidden" name="attach" id="attachFileId">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Выберите пост</label>
                            <select name="post_id" class="form-select" required>
                                <option value="">-- Выберите пост --</option>
                                <?php foreach (get_posts() as $post): ?>
                                    <option value="<?php echo $post['id']; ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-modern">Прикрепить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    break;	
	
            case 'plugins':
                if (!auth_check('admin')) die("Доступ запрещён");
                $conn = db_connect();
                $result = mysqli_query($conn, "SELECT * FROM plugins");
                $plugins = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $available_plugins = array_map('basename', glob(__DIR__ . '/../plugins/*.php'));
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin']) && isset($_POST['active'])) {
                    csrf_check();
                    $plugin = mysqli_real_escape_string($conn, $_POST['plugin']);
                    $active = (int)$_POST['active'];
                    mysqli_query($conn, "INSERT INTO plugins (name, active) VALUES ('$plugin', $active) ON DUPLICATE KEY UPDATE active=$active");
                    $plugins = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM plugins"), MYSQLI_ASSOC);
                    echo "<div class='alert alert-success'>Статус плагина обновлён</div>";
                }
                ?>
                <h1 class="mb-4">Плагины</h1>
                <table class="table table-striped">
                    <thead>
                        <tr><th>Название</th><th>Статус</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_plugins as $plugin_file): ?>
                            <?php
                            $plugin_name = $plugin_file;
                            $plugin = array_filter($plugins, fn($p) => $p['name'] === $plugin_name);
                            $plugin = reset($plugin);
                            $active = $plugin['active'] ?? 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($plugin_name); ?></td>
                                <td><?php echo $active ? 'Активен' : 'Неактивен'; ?></td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="plugin" value="<?php echo htmlspecialchars($plugin_name); ?>">
                                        <input type="hidden" name="active" value="<?php echo $active ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $active ? 'btn-danger' : 'btn-success'; ?>">
                                            <?php echo $active ? 'Деактивировать' : 'Активировать'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                break;

			case 'settings':
				if (!auth_check('admin')) die("Доступ запрещён");
				$conn = db_connect();
				if ($_SERVER['REQUEST_METHOD'] === 'POST') {
					csrf_check(); // Проверяем CSRF-токен
			
					// Список всех ожидаемых настроек
					$fields = [
						'site_name' => $_POST['site_name'] ?? '',
						'site_desc' => $_POST['site_desc'] ?? '',
						'site_keywords' => $_POST['site_keywords'] ?? '',
						'use_home_template' => isset($_POST['use_home_template']) ? '1' : '0', // Чекбокс: 1 или 0
						'auto_approve_comments' => isset($_POST['auto_approve_comments']) ? '1' : '0', // Чекбокс: 1 или 0
						'smtp_host' => $_POST['smtp_host'] ?? '',
						'smtp_username' => $_POST['smtp_username'] ?? '',
						'smtp_password' => $_POST['smtp_password'] ?? '',
						'smtp_port' => $_POST['smtp_port'] ?? '',
						'smtp_secure' => $_POST['smtp_secure'] ?? '',
						'smtp_from' => $_POST['smtp_from'] ?? ''
					];
			
					// Обновляем каждую настройку
					foreach ($fields as $name => $value) {
						update_setting($name, $value);
					}
			
					echo "<div class='alert alert-success'>Настройки сохранены</div>";
				}
				
				$use_php_mail = get_setting('use_php_mail');
				
				if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_php_mail'])) {
				update_setting('use_php_mail', $_POST['use_php_mail']);
				}
				
				// Загружаем текущие настройки
				$result = mysqli_query($conn, "SELECT name, value FROM settings");
				$settings = [];
				while ($row = mysqli_fetch_assoc($result)) {
					$settings[$row['name']] = $row['value'];
				}
				?>
				<h1 class="mb-4">Настройки</h1>
				<form method="post">
					<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
					<div class="mb-3">
						<label class="form-label">Название сайта</label>
						<input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required>
					</div>
					<div class="mb-3">
						<label class="form-label">Описание сайта</label>
						<textarea name="site_desc" class="form-control"><?php echo htmlspecialchars($settings['site_desc'] ?? ''); ?></textarea>
					</div>
					<div class="mb-3">
						<label class="form-label">Ключевые слова</label>
						<input type="text" name="site_keywords" class="form-control" value="<?php echo htmlspecialchars($settings['site_keywords'] ?? ''); ?>">
					</div>
					<div class="mb-3 form-check form-switch">
						<input class="form-check-input" type="checkbox" name="use_home_template" value="1" id="useHomeTemplate" <?php echo ($settings['use_home_template'] ?? 0) ? 'checked' : ''; ?>>
						<label class="form-check-label" for="useHomeTemplate">Использовать home.php для главной</label>
					</div>
					<div class="mb-3 form-check form-switch">
						<input class="form-check-input" type="checkbox" name="auto_approve_comments" value="1" id="autoApproveComments" <?php echo ($settings['auto_approve_comments'] ?? 0) ? 'checked' : ''; ?>>
						<label class="form-check-label" for="autoApproveComments">Автоматически одобрять комментарии</label>
					</div>
					
					<div class="form-group mb-3">
					<label for="use_php_mail">Использовать PHP mail() вместо PHPMailer</label>
					<select name="use_php_mail" id="use_php_mail" class="form-control">
						<option value="0" <?php echo $use_php_mail == '0' ? 'selected' : ''; ?>>Нет (использовать PHPMailer)</option>
						<option value="1" <?php echo $use_php_mail == '1' ? 'selected' : ''; ?>>Да (использовать mail())</option>
					</select>
					</div>
					
					<div class="mb-3">
						<label class="form-label">SMTP Хост</label>
						<input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label">SMTP Пользователь</label>
						<input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label">SMTP Пароль</label>
						<input type="text" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label">SMTP Порт</label>
						<input type="text" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label">SMTP Шифрование</label>
						<input type="text" name="smtp_secure" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_secure'] ?? ''); ?>">
					</div>
					<div class="mb-3">
						<label class="form-label">SMTP От кого</label>
						<input type="text" name="smtp_from" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_from'] ?? ''); ?>">
					</div>
					<button type="submit" class="btn btn-modern">Сохранить</button>
				</form>
				<?php
				break;

            default:
                echo "<h1>404 - Страница не найдена</h1>";
                break;
        }
        ?>
    </div>
<?php unset($_SESSION['preview_theme']); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/addon/hint/show-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/addon/hint/javascript-hint.min.js"></script>
	<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
<?php if ($action === 'themes'): ?>
// Глобальная переменная для редактора
let editor = null;

function saveFile() {
    if (!editor) {
        console.error('Редактор CodeMirror не инициализирован');
        showAlert('Ошибка: редактор не инициализирован', 'danger');
        return;
    }

    const editorElement = document.getElementById('codeEditor');
    const content = editor.getValue();
    const file = editorElement.dataset.file || editorElement.dataset.asset || '';
    const isAsset = editorElement.dataset.isAsset === '1';

    if (!file) {
        console.error('Имя файла не определено');
        showAlert('Ошибка: выберите файл для сохранения', 'danger');
        return;
    }

    console.log('Сохранение файла:', { file, isAsset, contentLength: content.length });

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `csrf_token=<?php echo $_SESSION['csrf_token']; ?>&${isAsset ? 'asset' : 'file'}=${encodeURIComponent(file)}&content=${encodeURIComponent(content)}`
    })
    .then(response => {
        console.log('Статус ответа:', response.status);
        if (!response.ok) throw new Error('Сетевая ошибка: ' + response.status);
        return response.text();
    })
    .then(data => {
        console.log('Ответ сервера:', data);
        showAlert(data.includes('Файл сохранён') ? 'Файл сохранён' : 'Ошибка сохранения', data.includes('Файл сохранён') ? 'success' : 'danger');
    })
    .catch(error => {
        console.error('Ошибка при сохранении:', error);
        showAlert('Ошибка: ' + error.message, 'danger');
    });
}

// Универсальная функция для уведомлений
function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    document.body.appendChild(alert);
    setTimeout(() => alert.classList.add('show'), 10);
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 500);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
    const editorElement = document.getElementById('codeEditor');
    if (editorElement) {
        editor = CodeMirror.fromTextArea(editorElement, {
            mode: editorElement.dataset.mode,
            lineNumbers: true,
            theme: 'default',
            mode: "htmlmixed",
            extraKeys: { 'Ctrl-S': saveFile, 'Ctrl-F': 'findPersistent' }
        });
    } else {
        console.error('Элемент #codeEditor не найден');
    }

    // Привязываем saveFile к кнопке "Сохранить"
    const saveButton = document.querySelector('.editor-header .btn[title="Сохранить (Ctrl+S)"]');
    if (saveButton) {
        saveButton.addEventListener('click', saveFile);
    } else {
        console.error('Кнопка "Сохранить" не найдена');
    }

    const previewModal = document.querySelector('#previewModal');
    if (previewModal) {
        previewModal.addEventListener('show.bs.modal', () => {
            console.log('Открытие модального окна предпросмотра');
            if (!editor) {
                console.error('Редактор CodeMirror не инициализирован');
                document.getElementById('previewModalBody').innerHTML = '<div class="alert alert-danger">Редактор не инициализирован</div>';
                return;
            }

            const content = editor.getValue();
            const file = editorElement.dataset.file || editorElement.dataset.asset || '';
            const isAsset = editorElement.dataset.isAsset === '1';
            console.log('Данные для предпросмотра:', { file, content, isAsset });

            if (!file || !content) {
                console.warn('Файл или содержимое пусты');
                document.getElementById('previewModalBody').innerHTML = '<div class="alert alert-danger">Выберите файл и добавьте содержимое</div>';
                return;
            }

            const body = `csrf_token=<?php echo $_SESSION['csrf_token']; ?>&${isAsset ? 'asset' : 'file'}=${encodeURIComponent(file)}&content=${encodeURIComponent(content)}&preview=1`;
            console.log('Тело запроса:', body);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(response => {
                console.log('Статус ответа:', response.status);
                return response.text();
            }).then(data => {
                console.log('Полученные данные:', data);
                document.getElementById('previewModalBody').innerHTML = data || '<div class="alert alert-warning">Сервер вернул пустой результат</div>';
            }).catch(error => {
                console.error('Ошибка fetch:', error);
                document.getElementById('previewModalBody').innerHTML = '<div class="alert alert-danger">Ошибка запроса: ' + error.message + '</div>';
            });
        });
    } else {
        console.error('Модальное окно #previewModal не найдено');
    }

    const fileSearch = document.getElementById('fileSearch');
    fileSearch.addEventListener('input', (e) => {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.file-item').forEach(item => {
            item.style.display = item.textContent.toLowerCase().includes(search) ? 'block' : 'none';
        });
    });

    document.querySelectorAll('[data-bs-target="#renameFileModal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('renameOldName').value = btn.dataset.file;
        });
    });
    document.querySelectorAll('[data-bs-target="#deleteFileModal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('deleteFileName').value = btn.dataset.file;
            document.getElementById('deleteFileDisplay').textContent = btn.dataset.file;
        });
    });
    document.querySelectorAll('[data-bs-target="#moveFileModal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('moveFileName').value = btn.dataset.file;
        });
    });
});
<?php endif; ?>
</script>
<style>
.filemanager-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
}
.file-item {
    transition: background 0.3s;
}
.file-item:hover {
    background: #f8f9fa;
}
.attach-btn {
    margin-left: 5px;
}
</style>

<script>

document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', () => {
        const table = th.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const index = Array.from(th.parentNode.children).indexOf(th);
        const isAsc = th.classList.contains('asc');
        const direction = isAsc ? 'desc' : 'asc';

        document.querySelectorAll('.sortable').forEach(t => t.classList.remove('asc', 'desc'));
        th.classList.add(direction);

        rows.sort((a, b) => {
            let aVal = a.children[index].textContent.trim();
            let bVal = b.children[index].textContent.trim();
            if (th.dataset.sort === 'size') {
                aVal = parseFloat(aVal) || 0;
                bVal = parseFloat(bVal) || 0;
            } else if (th.dataset.sort === 'date') {
                aVal = new Date(aVal);
                bVal = new Date(bVal);
            }
            return direction === 'asc' ? (aVal > bVal ? 1 : -1) : (aVal < bVal ? 1 : -1);
        });

        rows.forEach(row => tbody.appendChild(row));
    });
});

document.getElementById('selectAllFiles').addEventListener('change', (e) => {
    document.querySelectorAll('input[name="fileIds[]"]').forEach(cb => cb.checked = e.target.checked);
    document.getElementById('bulkDeleteFiles').disabled = !e.target.checked;
});
document.querySelectorAll('input[name="fileIds[]"]').forEach(cb => {
    cb.addEventListener('change', () => {
        document.getElementById('bulkDeleteFiles').disabled = !document.querySelectorAll('input[name="fileIds[]"]:checked').length;
    });
});
document.getElementById('bulkDeleteFiles').addEventListener('click', () => {
    if (confirm('Удалить выбранные файлы?')) {
        const ids = Array.from(document.querySelectorAll('input[name="fileIds[]"]:checked')).map(cb => cb.value);
        window.location.href = `?action=filemanager&bulk_delete=${ids.join(',')}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`;
    }
});

document.getElementById('multiUpload').addEventListener('change', (e) => {
    const files = e.target.files;
    const formData = new FormData();
    for (let file of files) {
        formData.append('attachments[]', file);
    }
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

    const progressBar = document.querySelector('#uploadProgress .progress-bar');
    document.getElementById('uploadProgress').style.display = 'block';

    fetch('?action=filemanager&upload', {
        method: 'POST',
        body: formData
    }).then(response => {
        progressBar.style.width = '100%';
        progressBar.textContent = '100%';
        setTimeout(() => location.reload(), 1000); // Обновление страницы после загрузки
    }).catch(err => {
        console.error(err);
        progressBar.classList.add('bg-danger');
        progressBar.textContent = 'Ошибка';
    });
});

document.querySelectorAll('.preview-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const file = btn.dataset.file;
        const type = btn.dataset.type;
        const previewContent = document.getElementById('previewFileContent');
        if (type.startsWith('image/')) {
            previewContent.innerHTML = `<img src="/uploads/${file}" class="img-fluid" style="max-height: 500px;">`;
        } else if (type === 'text/plain') {
            fetch(`/uploads/${file}`).then(res => res.text()).then(text => {
                previewContent.innerHTML = `<pre>${text}</pre>`;
            });
        } else {
            previewContent.innerHTML = '<p>Предпросмотр недоступен для этого типа файла</p>';
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    // Переменная для хранения предыдущего модального окна
    let previousModal = null;

    // Функция для показа предыдущего модального окна
    function showPreviousModal() {
        if (previousModal) {
            const modalInstance = bootstrap.Modal.getInstance(previousModal) || new bootstrap.Modal(previousModal);
            modalInstance.show();
            console.log(`Показываем предыдущее модальное окно: ${previousModal.id}`);
        } else {
            console.warn('Предыдущее модальное окно не определено');
        }
    }

    // Обновление previousModal на основе текущего состояния
    function updatePreviousModal() {
        const activeModal = document.querySelector('#addPostModal.show') || document.querySelector('#editPostModal.show');
        if (activeModal) {
            previousModal = activeModal;
            console.log(`Обновлено previousModal: ${previousModal.id}`);
        } else {
            const editPostModal = document.getElementById('editPostModal');
            if (editPostModal && editPostModal.style.display === 'block') {
                previousModal = editPostModal;
                console.log(`Установлено previousModal при загрузке: ${previousModal.id}`);
            }
        }
    }

    // Инициализация при загрузке страницы
    updatePreviousModal();

    // Отслеживаем открытие и закрытие основных модальных окон
    ['addPostModal', 'editPostModal'].forEach(modalId => {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            modalElement.addEventListener('shown.bs.modal', () => {
                previousModal = modalElement;
                console.log(`Модальное окно ${modalId} открыто, previousModal обновлён`);
            });
            // Убираем сброс previousModal при hidden.bs.modal, чтобы не терять его
            modalElement.addEventListener('hidden.bs.modal', () => {
                console.log(`Модальное окно ${modalId} закрыто`);
            });
        }
    });

    // Обработка открытия модальных окон выбора файлов
    document.querySelectorAll('[data-bs-target="#attachExistingModalAdd"], [data-bs-target="#attachExistingModalEdit"]').forEach(btn => {
        btn.addEventListener('click', () => {
            updatePreviousModal();
            if (!previousModal) {
                console.warn('Нет открытого модального окна для сохранения в previousModal');
            } else {
                console.log('previousModal перед открытием выбора файлов:', previousModal.id);
            }
        });
    });

    // Обработка выбора существующих файлов для добавления поста
    const attachSelectedAdd = document.getElementById('attachSelectedAdd');
    if (attachSelectedAdd) {
        attachSelectedAdd.addEventListener('click', () => {
            const selectedFiles = [];
            document.querySelectorAll('#fileListAdd .file-item input:checked').forEach(checkbox => {
                const fileId = checkbox.value;
                const fileName = checkbox.nextElementSibling.textContent.trim();
                selectedFiles.push({ id: fileId, name: fileName });
            });

            const attachedFilesDiv = document.getElementById('attached-files-add');
            if (attachedFilesDiv) {
                attachedFilesDiv.innerHTML = '';
                selectedFiles.forEach(file => {
                    attachedFilesDiv.innerHTML += `
                        <div class="file-item" data-file-id="${file.id}">
                            ${file.name}
                            <button type="button" class="btn btn-sm btn-danger remove-attached-file" data-file-id="${file.id}">Удалить</button>
                        </div>
                    `;
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'existing_files[]';
                    input.value = file.id;
                    attachedFilesDiv.appendChild(input);
                });

                attachedFilesDiv.querySelectorAll('.remove-attached-file').forEach(btn => {
                    btn.addEventListener('click', () => {
                        btn.parentElement.remove();
                    });
                });
            }

            const modalElement = document.getElementById('attachExistingModalAdd');
            const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modalInstance.hide();
            showPreviousModal();
        });
    }

    // Обработка выбора существующих файлов для редактирования поста
    const attachSelectedEdit = document.getElementById('attachSelectedEdit');
    if (attachSelectedEdit) {
        attachSelectedEdit.addEventListener('click', () => {
            const selectedFiles = [];
            document.querySelectorAll('#fileListEdit .file-item input:checked').forEach(checkbox => {
                const fileId = checkbox.value;
                const fileName = checkbox.nextElementSibling.textContent.trim();
                selectedFiles.push({ id: fileId, name: fileName });
            });

            const attachedFilesDiv = document.getElementById('new-attached-files-edit');
            if (attachedFilesDiv) {
                attachedFilesDiv.innerHTML = '';
                selectedFiles.forEach(file => {
                    attachedFilesDiv.innerHTML += `
                        <div class="file-item" data-file-id="${file.id}">
                            ${file.name}
                            <button type="button" class="btn btn-sm btn-danger remove-attached-file" data-file-id="${file.id}">Удалить</button>
                        </div>
                    `;
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'existing_files[]';
                    input.value = file.id;
                    attachedFilesDiv.appendChild(input);
                });

                attachedFilesDiv.querySelectorAll('.remove-attached-file').forEach(btn => {
                    btn.addEventListener('click', () => {
                        btn.parentElement.remove();
                    });
                });
            }

            const modalElement = document.getElementById('attachExistingModalEdit');
            const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            modalInstance.hide();
            showPreviousModal();
        });
    }

    // Обработка закрытия модальных окон выбора файлов
    ['attachExistingModalAdd', 'attachExistingModalEdit'].forEach(modalId => {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            modalElement.addEventListener('hidden.bs.modal', () => {
                console.log(`Модальное окно ${modalId} закрыто, вызываем showPreviousModal`);
                showPreviousModal();
            });
        }
    });

    // Поиск в модальных окнах
    ['fileSearchAttachAdd', 'fileSearchAttachEdit'].forEach(searchId => {
        const searchInput = document.getElementById(searchId);
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const search = e.target.value.toLowerCase();
                const fileListId = searchId === 'fileSearchAttachAdd' ? '#fileListAdd' : '#fileListEdit';
                document.querySelectorAll(`${fileListId} .file-item`).forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(search) ? '' : 'none';
                });
            });
        }
    });

    // Обработка удаления ранее прикреплённых файлов (в редактировании)
    document.querySelectorAll('.remove-file').forEach(btn => {
        btn.addEventListener('click', () => {
            const fileId = btn.dataset.fileId;
            const postId = <?php echo isset($_GET['edit']) ? json_encode($_GET['edit']) : 'null'; ?>;
            if (confirm('Открепить файл от поста?')) {
                window.location.href = `?action=posts&edit=${postId}&remove_file=${fileId}&csrf_token=<?php echo json_encode($_SESSION['csrf_token']); ?>`;
            }
        });
    });
});
document.addEventListener('DOMContentLoaded', () => {
    const newAttachmentsAdd = document.getElementById('newAttachmentsAdd');
    if (newAttachmentsAdd) {
        newAttachmentsAdd.addEventListener('change', (e) => {
            const files = e.target.files;
            const attachedFilesDiv = document.getElementById('new-attached-files-add');
            attachedFilesDiv.innerHTML = '';
            for (const file of files) {
                attachedFilesDiv.innerHTML += `
                    <div class="file-item" data-file-name="${file.name}">
                        ${file.name} (${(file.size / 1024).toFixed(2)} KB)
                    </div>
                `;
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // Настройка панели инструментов Quill
    const quillToolbarOptions = [
        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        ['link', 'image', 'video'],
        ['blockquote', 'code-block'],
        [{ 'align': [] }],
        ['clean']
    ];

    // Переменные для хранения экземпляров Quill
    let quillAdd = null;
    let quillEdit = null;
    let quillPageAdd = null;
    let quillPageEdit = null;

    // Функция для инициализации Quill
    function initializeQuill(containerId, hiddenInputId, initialContent = '') {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Контейнер #${containerId} не найден`);
            return null;
        }
        const editor = new Quill(container, {
            theme: 'snow',
            modules: { toolbar: quillToolbarOptions },
            placeholder: 'Введите содержимое...'
        });
        if (initialContent) {
            try {
                editor.root.innerHTML = initialContent;
            } catch (e) {
                console.error(`Ошибка при установке начального содержимого для #${containerId}:`, e);
                editor.root.innerHTML = '';
            }
        }
        editor.on('text-change', () => {
            document.getElementById(hiddenInputId).value = editor.root.innerHTML;
        });
        return editor;
    }

    // Добавление поста
    const addPostModal = document.getElementById('addPostModal');
    if (addPostModal) {
        addPostModal.addEventListener('shown.bs.modal', () => {
            if (!quillAdd) {
                quillAdd = initializeQuill('editor', 'editorContent');
            }
        });
        addPostModal.addEventListener('hidden.bs.modal', () => {
            if (quillAdd) {
                quillAdd.root.innerHTML = '';
            }
        });
    }

    // Редактирование поста
    const editPostModal = document.getElementById('editPostModal');
    if (editPostModal) {
        // Инициализация при загрузке страницы, если модальное окно уже открыто
        if (editPostModal.style.display === 'block') {
            if (!quillEdit) {
                const initialContent = <?php echo isset($post['content']) ? json_encode(html_entity_decode($post['content'], ENT_QUOTES, 'UTF-8')) : '""'; ?>;
                quillEdit = initializeQuill('editorEdit', 'editorEditContent', initialContent);
            }
        }
        editPostModal.addEventListener('shown.bs.modal', () => {
            if (!quillEdit) {
                const initialContent = <?php echo isset($post['content']) ? json_encode(html_entity_decode($post['content'], ENT_QUOTES, 'UTF-8')) : '""'; ?>;
                quillEdit = initializeQuill('editorEdit', 'editorEditContent', initialContent);
            }
        });
    }

    // Добавление страницы
    const addPageModal = document.getElementById('addPageModal');
    if (addPageModal) {
        addPageModal.addEventListener('shown.bs.modal', () => {
            if (!quillPageAdd) {
                quillPageAdd = initializeQuill('pageEditor', 'pageEditorContent');
            }
        });
        addPageModal.addEventListener('hidden.bs.modal', () => {
            if (quillPageAdd) {
                quillPageAdd.root.innerHTML = '';
            }
        });
    }

    // Редактирование страницы
    const editPageModal = document.getElementById('editPageModal');
    if (editPageModal) {
        // Инициализация при загрузке страницы, если модальное окно уже открыто
        if (editPageModal.style.display === 'block') {
            if (!quillPageEdit) {
                const initialContent = <?php echo isset($page['content']) ? json_encode(html_entity_decode($page['content'], ENT_QUOTES, 'UTF-8')) : '""'; ?>;
                quillPageEdit = initializeQuill('editPageEditor', 'editPageEditorContent', initialContent);
            }
        }
        editPageModal.addEventListener('shown.bs.modal', () => {
            if (!quillPageEdit) {
                const initialContent = <?php echo isset($page['content']) ? json_encode(html_entity_decode($page['content'], ENT_QUOTES, 'UTF-8')) : '""'; ?>;
                quillPageEdit = initializeQuill('editPageEditor', 'editPageEditorContent', initialContent);
            }
        });
    }

    // Обработка отправки форм
    const addPostForm = document.getElementById('addPostForm');
    if (addPostForm) {
        addPostForm.addEventListener('submit', (e) => {
            if (quillAdd) {
                document.getElementById('editorContent').value = quillAdd.root.innerHTML;
            }
        });
    }

    const editPostForm = document.getElementById('editPostForm');
    if (editPostForm) {
        editPostForm.addEventListener('submit', (e) => {
            if (quillEdit) {
                document.getElementById('editorEditContent').value = quillEdit.root.innerHTML;
            }
        });
    }

    const addPageForm = document.getElementById('addPageForm');
    if (addPageForm) {
        addPageForm.addEventListener('submit', (e) => {
            if (quillPageAdd) {
                document.getElementById('pageEditorContent').value = quillPageAdd.root.innerHTML;
            }
        });
    }

    const editPageForm = document.getElementById('editPageForm');
    if (editPageForm) {
        editPageForm.addEventListener('submit', (e) => {
            if (quillPageEdit) {
                document.getElementById('editPageEditorContent').value = quillPageEdit.root.innerHTML;
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const editPostModal = document.getElementById('editPostModal');
    if (editPostModal && <?php echo isset($_GET['edit']) ? 'true' : 'false'; ?>) {
        const modalInstance = new bootstrap.Modal(editPostModal);
        modalInstance.show();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('contentSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('table tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('selectAllPosts');
    const bulkDelete = document.getElementById('bulkDeletePosts');
    const checkboxes = document.querySelectorAll('input[name="bulk[]"]');

    selectAll.addEventListener('change', () => {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        bulkDelete.disabled = !selectAll.checked && !Array.from(checkboxes).some(cb => cb.checked);
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            bulkDelete.disabled = !Array.from(checkboxes).some(cb => cb.checked);
        });
    });

    bulkDelete.addEventListener('click', () => {
        if (confirm('Удалить выбранные посты?')) {
            const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
            window.location.href = `?action=posts&bulk_delete=${selected.join(',')}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`;
        }
    });
});


document.addEventListener('DOMContentLoaded', () => {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    
    if (titleInput && slugInput) {
        let isSlugManuallyEdited = false;

        slugInput.addEventListener('input', () => {
            isSlugManuallyEdited = true;
        });

        titleInput.addEventListener('input', () => {
            const titleValue = titleInput.value;
            const generatedSlug = transliterateSlug(titleValue);
            
            if (!isSlugManuallyEdited || !slugInput.value) {
                slugInput.value = generatedSlug;
            }
        });
    } 
});

function transliterateSlug(text) {
    const translitMap = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e', 'ж': 'zh',
        'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o',
        'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'ts',
        'ч': 'ch', 'ш': 'sh', 'щ': 'sch', 'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu',
        'я': 'ya', ' ': '-'
    };

    text = text.toLowerCase();
    let slug = '';
    
    for (let char of text) {
        slug += translitMap[char] || (/[a-z0-9]/.test(char) ? char : '-');
    }
    
    slug = slug.replace(/[^a-z0-9-]+/g, '')
               .replace(/-+/g, '-')
               .replace(/(^-|-$)/g, '');
    
    return slug;
}

const contextMenu = document.getElementById('contextMenu');
document.querySelectorAll('#fileTable tr').forEach(row => {
    row.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        const fileUrl = row.querySelector('a').href;
        const fileId = row.querySelector('input[name="fileIds[]"]').value;

        contextMenu.style.top = `${e.pageY}px`;
        contextMenu.style.left = `${e.pageX}px`;
        contextMenu.style.display = 'block';

        contextMenu.querySelector('.context-download').onclick = () => window.location.href = fileUrl;
        contextMenu.querySelector('.context-copy').onclick = () => navigator.clipboard.writeText(fileUrl);
        contextMenu.querySelector('.context-delete').onclick = () => {
            if (confirm('Удалить файл?')) window.location.href = `?action=filemanager&delete=${fileId}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`;
        };
    });
});
document.addEventListener('click', () => contextMenu.style.display = 'none');

document.querySelectorAll('.file-tooltip').forEach(link => {
    const tooltip = new bootstrap.Tooltip(link, {
		trigger: 'hover',
        customClass: 'file-tooltip-preview',
        placement: 'right',
        delay: { show: 300, hide: 100 }
    });

    link.addEventListener('mouseover', () => {
        const file = link.dataset.file;
        const type = link.dataset.type;
        let content = 'Предпросмотр недоступен';
        if (type.startsWith('image/')) {
            content = `<img src="/uploads/${file}" width="200" height="200" >`;
        } else if (type === 'text/plain') {
            fetch(`/uploads/${file}`).then(res => res.text()).then(text => {
                tooltip._config.title = `<pre max-width="400" max-height="400">${text.slice(0, 500)}</pre>`;
                tooltip.update();
            });
            content = 'Загрузка...';
        }
        tooltip._config.title = content;
        tooltip.update();
    });
});
</script>
</html>