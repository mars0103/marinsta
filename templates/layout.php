<?php
/** @var string $title */
/** @var string $active */
/** @var string $content */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? 'Marinsta') ?></title>
<link rel="icon" type="image/png" href="<?= h(url('assets/brand/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>?v=<?= asset_version() ?>">
</head>
<body>
<div class="app">
    <div class="mobile-topbar">
        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Abrir menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <img src="<?= h(url('assets/brand/logo-preta.svg')) ?>" alt="Marinsta" class="mobile-topbar-logo">
    </div>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <aside class="sidebar" id="sidebar">
        <div class="brand"><img src="<?= h(url('assets/brand/logo-branca.svg')) ?>" alt="Marinsta" class="brand-logo"></div>
        <nav class="nav">
            <a href="<?= h(url('/')) ?>" class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>"><?= icon('automations') ?> Automações</a>
            <a href="<?= h(url('/packs')) ?>" class="<?= ($active ?? '') === 'packs' ? 'active' : '' ?>"><?= icon('packs') ?> Pack de respostas</a>
            <a href="<?= h(url('/logs')) ?>" class="<?= ($active ?? '') === 'logs' ? 'active' : '' ?>"><?= icon('logs') ?> Respostas enviadas</a>
            <a href="<?= h(url('/crm')) ?>" class="<?= ($active ?? '') === 'crm' ? 'active' : '' ?>"><?= icon('crm') ?> CRM</a>
            <a href="<?= h(url('/content')) ?>" class="<?= ($active ?? '') === 'content' ? 'active' : '' ?>"><?= icon('content') ?> Análise de Conteúdo</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= h(url('/profile')) ?>" class="<?= ($active ?? '') === 'profile' ? 'active' : '' ?>"><?= icon('settings') ?> Perfil</a>
            <a href="<?= h(url('/logout')) ?>"><?= icon('logout') ?> Sair</a>
        </div>
    </aside>
    <main class="main">
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-error"><?= h($msg) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</div>
<script>
    (function () {
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        var toggle = document.getElementById('menuToggle');

        function closeMenu() {
            sidebar.classList.remove('open');
            backdrop.classList.remove('show');
        }

        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            backdrop.classList.toggle('show');
        });
        backdrop.addEventListener('click', closeMenu);
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });
    })();
</script>
</body>
</html>
