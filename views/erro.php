<?php /** @var string $titulo @var string $mensagem */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Gerenciador de Estudos</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="pagina-login">
<main class="login">
    <h1><?= e($titulo) ?></h1>
    <p class="suave"><?= e($mensagem) ?></p>
    <p><a class="botao" href="/">Voltar ao painel</a></p>
</main>
</body>
</html>
