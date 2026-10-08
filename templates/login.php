<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Marinsta</title>
<link rel="icon" type="image/png" href="<?= h(url('assets/brand/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>?v=<?= asset_version() ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <img src="<?= h(url('assets/brand/logo-preta.svg')) ?>" alt="Marinsta" class="login-logo">
        <p>Automações do seu Instagram.</p>

        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-error"><?= h($msg) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= h(url('/login')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" autofocus required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Entrar</button>
        </form>
        <p class="form-hint" style="margin-top:16px; text-align:center;">
            <a href="<?= h(url('/forgot-password')) ?>">Esqueceu a senha? Receber uma nova por email</a>
        </p>
    </div>
</div>
</body>
</html>
