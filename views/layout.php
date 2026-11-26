<?php
/**
 * @var string $conteudo
 * @var string|null $titulo
 */
$usuario = $GLOBALS['usuario'] ?? null;
$saldoTopo = $usuario ? App\Carteira::saldo(App\Database::pdo(), (int) $usuario['id']) : 0;
$caminhoAtual = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$flash = pegar_flash();
$menu = ['/' => 'Painel', '/foco' => 'Foco', '/historico' => 'Histórico', '/materias' => 'Matérias',
    '/loja' => 'Loja', '/calendario' => 'Calendário'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($titulo ?? null) ? $titulo . ' · Gerenciador de Estudos' : 'Gerenciador de Estudos') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
</head>
<body>
<header class="barra-topo">
    <a class="marca" href="/">Gerenciador de Estudos</a>
    <nav class="menu">
        <?php foreach ($menu as $href => $rotulo): ?>
            <a href="<?= $href ?>" <?= $caminhoAtual === $href ? 'aria-current="page"' : '' ?>><?= $rotulo ?></a>
        <?php endforeach; ?>
    </nav>
    <a class="carteira" href="/carteira" title="Moedas: ganhe 1 a cada sessão concluída">
        <i class="moeda"></i> <strong data-saldo><?= $saldoTopo ?></strong>
    </a>
    <form method="post" action="/sair" class="sair">
        <?= campos_form() ?>
        <button class="link" title="<?= e($usuario['email'] ?? '') ?>">Sair</button>
    </form>
</header>

<main>
    <?php if ($flash): ?>
        <p class="flash flash-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensagem']) ?></p>
    <?php endif; ?>
    <?= $conteudo ?>
</main>

<script src="/assets/js/app.js"></script>
</body>
</html>
