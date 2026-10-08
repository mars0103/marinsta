<?php
/** @var string $adminUser */
?>
<div class="page-header">
    <div>
        <h1>Perfil</h1>
        <p>Dados de acesso ao painel.</p>
    </div>
</div>

<div class="card" style="max-width:420px;">
    <div class="form-group">
        <label>Usuário</label>
        <input type="text" value="<?= h($adminUser) ?>" disabled>
    </div>

    <form method="post" action="<?= h(url('/profile')) ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="current_password">Senha atual</label>
            <input type="password" id="current_password" name="current_password" required autofocus>
        </div>

        <div class="form-group">
            <label for="new_password">Nova senha</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
            <p class="form-hint" style="margin-top:4px;">Mínimo de 8 caracteres.</p>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirmar nova senha</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Trocar senha</button>
        </div>
    </form>
</div>
