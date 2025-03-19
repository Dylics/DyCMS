<?php require_once __DIR__ . "/../../core/app.php"; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($meta["title"]); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta["description"]); ?>">  
    <meta name="keywords" content="<?php echo htmlspecialchars($meta["keywords"]); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/themes/default/assets/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="/themes/default/assets/script.js"></script>
</head>
<body>
    <?php 
    include "header.php"; 
    include "nav.php"; 
    include "main.php"; 
    include "footer.php"; 
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>