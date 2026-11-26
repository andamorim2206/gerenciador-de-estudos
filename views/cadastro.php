<?php /** @var string $nome @var string $email */ $flash = pegar_flash(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Criar conta · Gerenciador de Estudos</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="pagina-login">
<main class="login">
    <div class="login-neuronio">
        <?= parcial('neuronio', ['estagio' => 0, 'equipados' => []]) ?>
    </div>
    <h1>Criar conta</h1>
    <p class="suave">Seu neurônio começa pequeno. Cada minuto de foco faz ele evoluir.</p>

    <?php if ($flash): ?>
        <p class="flash flash-<?= e($flash['tipo']) ?>" role="alert"><?= e($flash['mensagem']) ?></p>
    <?php endif; ?>

    <form method="post" action="/cadastro" class="card pilha">
        <?= campos_form() ?>
        <label>Nome <input type="text" name="nome" value="<?= e($nome) ?>" required maxlength="60" autofocus autocomplete="name"></label>
        <label>E-mail <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email"></label>
        <label>Senha <input type="password" name="senha" required minlength="8" maxlength="72" autocomplete="new-password"></label>
        <label>Repita a senha <input type="password" name="confirmacao" required minlength="8" maxlength="72" autocomplete="new-password"></label>
        <button>Criar conta</button>
    </form>
    <p class="suave troca-acesso">Já tem conta? <a href="/login">Entrar</a></p>
</main>
</body>
</html>
