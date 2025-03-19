    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #f5f7fa; height: 100vh; display: flex; justify-content: center; align-items: center; }
            .login-card { max-width: 400px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class="card login-card">
            <div class="card-body">
                <h3 class="text-center mb-4">Вход</h3>
                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                <form method="post">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control" placeholder="Логин" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Пароль" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Войти</button>
                    <div class="text-center mt-3">
                        <a href="/register" class="text-muted">Регистрация</a> | <a href="/reset-password" class="text-muted">Забыли пароль?</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>