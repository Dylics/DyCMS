<main class="content">
    <?php
    if ($page_param === 'home') {
        $posts = get_posts();
        if ($posts) {
            foreach ($posts as $post) {
                ?>
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                        <p class="card-text"><?php echo substr($post['content'], 0, 200); ?></p>
                        <a href="/post/<?php echo $post['id']; ?>" class="btn btn-modern">Читать далее</a>
                    </div>
                </div>
                <?php
            }
        } else {
            echo '<p>Постов пока нет.</p>';
        }
    } elseif ($page_param === 'post' && isset($page['post'])) {
        $post = $page['post'];
        ?>
        <div class="card">
            <div class="card-body">
                <h1 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                <div class="post-content">
                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                </div>
                <p class="text-muted mt-2">Опубликовано: <?php echo $post['created_at']; ?></p>
                <a href="/" class="btn btn-modern mt-3">Назад</a>
            </div>
        </div>
        <?php
    } elseif ($page && isset($page['content'])) {
        echo $page['content'];
    } else {
        include "404.php";
    }
    ?>
</main>