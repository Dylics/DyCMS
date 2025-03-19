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
    <main class="content">
        <h1 class="mb-4"><?php echo htmlspecialchars($category_data['name']); ?></h1>
        <?php if (count($posts_data) > 0): ?>
            <?php foreach ($posts_data as $post): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                        <div class="card-text">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                        <p class="text-muted mt-2">Опубликовано: <?php echo $post['created_at']; ?></p>
                        <a href="/post/<?php echo htmlspecialchars($post['id']); ?>" class="btn btn-modern mt-3">Читать далее</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <p class="card-text">В этой категории пока нет новостей.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>
    <?php include "footer.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>