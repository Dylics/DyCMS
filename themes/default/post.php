<?php require_once __DIR__ . "/../../core/app.php"; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($category_data['name']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($category_data['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($category_data['name']); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/themes/default/assets/style.css" rel="stylesheet">
    <script src="/themes/default/assets/script.js"></script>
</head>
<body>
    <?php 
    include "header.php"; 
    include "nav.php"; 
    ?>
    <?php
    $post = $page['post'];
    ?>
    <main class="content">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                <div class="post-content">
                    <?php echo nl2br($post['content']); ?>
                </div>
                <!-- Секция прикреплённых файлов -->
                <?php
                $files = get_post_files($post['id']);
                if ($files): ?>
                    <div class="attached-files mt-3">
                        <h5>Прикреплённые файлы:</h5>
                        <ul class="list-group">
                            <?php foreach ($files as $file): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="/uploads/<?php echo htmlspecialchars($file['filename']); ?>" 
                                       download="<?php echo htmlspecialchars($file['original_name']); ?>" 
                                       class="text-decoration-none">
                                        <i class="fas fa-file me-2"></i>
                                        <?php echo htmlspecialchars($file['original_name']); ?>
                                        (<?php echo round($file['size'] / 1024, 2); ?> KB)
                                    </a>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($file['mime_type']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <p class="text-muted mt-2">Опубликовано: <?php echo $post['created_at']; ?></p>
                <a href="/" class="btn btn-modern mt-3">Назад</a>
            </div>
        </div>
        <!-- Секция комментариев -->
        <div class="card mt-4" id="comments">
            <div class="card-body">
                <h3>Комментарии</h3>
                <?php
                $comments = get_comments($post['id'], 'approved');
                if ($comments) {
                    foreach ($comments as $comment) {
                        echo '<div class="comment mb-3">';
                        echo '<p><strong>' . htmlspecialchars($comment['username'] ?? 'Гость') . '</strong> (' . $comment['created_at'] . ')</p>';
                        echo '<p>' . nl2br(htmlspecialchars($comment['content'])) . '</p>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>Комментариев пока нет. Будьте первым!</p>';
                }
                ?>
                <!-- Форма для добавления комментария -->
                <?php if (auth_check()): ?>
                    <form action="/comment" method="post" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">
                        <div class="mb-3">
                            <textarea name="content" class="form-control" rows="3" placeholder="Ваш комментарий" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-modern">Отправить</button>
                    </form>
                <?php else: ?>
                    <p class="mt-3">Пожалуйста, <a href="/login">войдите</a>, чтобы оставить комментарий.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <?php include "footer.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>