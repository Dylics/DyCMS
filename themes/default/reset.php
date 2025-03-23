   <?php require_once __DIR__ . "/../../core/app.php"; handle_reset_password_request(); ?>
   <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Сброс пароля</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #f5f7fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
            .reset-card { max-width: 400px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class="card reset-card">
            <div class="card-body">
                <h3 class="text-center mb-4 text-primary">Сброс пароля</h3>
                <form method="post">
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Отправить</button>
                    <div class="text-center mt-3">
                        <a href="<?php echo $is_admin ? '/admin' : '/login'; ?>" class="text-muted">Вернуться к входу</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>