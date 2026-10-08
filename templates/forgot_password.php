<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Esqueci minha senha · Marinsta</title>
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
        <p>Esqueceu a senha? A gente manda uma nova por email.</p>

        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-error"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= h($msg) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= h(url('/forgot-password')) ?>">
            <?= csrf_field() ?>
            <p class="form-hint" style="margin-bottom:16px;">
                Vamos gerar uma senha nova e mandar pro email de recuperação configurado.
                Depois é só trocar por uma de sua escolha em Perfil.
            </p>
            <button type="submit" class="btn btn-primary" style="width:100%">Enviar nova senha por email</button>
        </form>

        <p class="form-hint" style="margin-top:16px; text-align:center;">
            <a href="<?= h(url('/login')) ?>">Voltar pro login</a>
        </p>
    </div>
</div>
</body>
</html>
