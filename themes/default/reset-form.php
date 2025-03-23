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