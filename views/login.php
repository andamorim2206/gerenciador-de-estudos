<?php /** @var string $email */ $flash = pegar_flash(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · Gerenciador de Estudos</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="pagina-login">
<main class="login">
    <div class="login-neuronio">
        <?= parcial('neuronio', ['estagio' => 3, 'equipados' => []]) ?>
    </div>
    <h1>Gerenciador de Estudos</h1>
    <p class="suave">Cada minuto de foco faz seu neurônio evoluir.</p>

    <?php if ($flash): ?>
        <p class="flash flash-<?= e($flash['tipo']) ?>" role="alert"><?= e($flash['mensagem']) ?></p>
    <?php endif; ?>

    <form method="post" action="/login" class="card pilha">
        <?= campos_form() ?>
        <label>E-mail <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
        <label>Senha <input type="password" name="senha" required autocomplete="current-password"></label>
        <button>Entrar</button>
    </form>
    <p class="suave troca-acesso">Ainda não tem conta? <a href="/cadastro">Criar conta</a></p>
</main>
</body>
</html>
