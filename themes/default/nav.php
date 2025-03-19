<nav class="nav">
    <div class="container">
        <a href="/" class="<?php echo $page_param === 'home' ? 'active' : ''; ?>">Главная</a>
        <?php foreach (get_categories() as $category): ?>
            <a href="/category/<?php echo urlencode($category['slug']); ?>">
                <?php echo htmlspecialchars($category['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>