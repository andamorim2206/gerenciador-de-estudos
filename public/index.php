<?php

declare(strict_types=1);

use App\Auth;
use App\Controllers;
use App\Database;
use App\Sessoes;

// No servidor embutido (php -S), arquivos estáticos de public/ são servidos direto.
if (PHP_SAPI === 'cli-server') {
    $arquivo = realpath(__DIR__ . urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    if ($arquivo && $arquivo !== __FILE__ && str_starts_with($arquivo, __DIR__ . '/') && is_file($arquivo)) {
        return false;
    }
}

require __DIR__ . '/../src/bootstrap.php';

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);

$metodo = $_SERVER['REQUEST_METHOD'];
$caminho = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

$rotas = [
    'GET /login' => [Controllers\AuthController::class, 'formulario', false],
    'POST /login' => [Controllers\AuthController::class, 'entrar', false],
    'GET /cadastro' => [Controllers\AuthController::class, 'formularioCadastro', false],
    'POST /cadastro' => [Controllers\AuthController::class, 'cadastrar', false],
    'POST /sair' => [Controllers\AuthController::class, 'sair', true],

    'GET /' => [Controllers\PainelController::class, 'index', true],

    'GET /foco' => [Controllers\FocoController::class, 'index', true],
    'POST /foco/iniciar' => [Controllers\FocoController::class, 'iniciar', true],
    'POST /foco/concluir' => [Controllers\FocoController::class, 'concluir', true],
    'POST /foco/abandonar' => [Controllers\FocoController::class, 'abandonar', true],

    'GET /historico' => [Controllers\HistoricoController::class, 'index', true],

    'GET /materias' => [Controllers\MateriasController::class, 'index', true],
    'POST /materias/criar' => [Controllers\MateriasController::class, 'criar', true],
    'POST /materias/editar' => [Controllers\MateriasController::class, 'editar', true],
    'POST /materias/arquivar' => [Controllers\MateriasController::class, 'arquivar', true],
    'POST /materias/reativar' => [Controllers\MateriasController::class, 'reativar', true],

    'GET /loja' => [Controllers\LojaController::class, 'index', true],
    'POST /loja/comprar' => [Controllers\LojaController::class, 'comprar', true],
    'POST /loja/equipar' => [Controllers\LojaController::class, 'equipar', true],

    'GET /carteira' => [Controllers\CarteiraController::class, 'index', true],

    'GET /calendario' => [Controllers\CalendarioController::class, 'index', true],
    'POST /calendario' => [Controllers\CalendarioController::class, 'acao', true],
];

$rota = $rotas["$metodo $caminho"] ?? null;
if ($rota === null) {
    http_response_code(404);
    render('erro', ['titulo' => 'Página não encontrada', 'mensagem' => 'Esse endereço não existe.'], layout: false);
}
[$controller, $acao, $exigeLogin] = $rota;

if ($metodo === 'POST' && !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    render('erro', ['titulo' => 'Formulário expirado', 'mensagem' => 'Recarregue a página e tente de novo.'], layout: false);
}

$pdo = Database::pdo();
$usuario = Auth::usuarioAtual($pdo);

if ($exigeLogin && !$usuario) {
    redirecionar('/login');
}

if ($usuario) {
    // Sessão que terminou com a aba fechada é concluída (e recompensada) no próximo acesso.
    // /foco/concluir faz isso por conta própria para devolver o resultado ao cronômetro.
    if ($caminho !== '/foco/concluir') {
        $resultado = Sessoes::finalizarExpirada($pdo, (int) $usuario['id'], new DateTimeImmutable());
        if ($resultado) {
            flash(sprintf('Sua sessão de %s terminou: +%d XP e +1 moeda.', duracao($resultado['minutos']), $resultado['xp_ganho']), 'sucesso');
        }
    }
    $GLOBALS['usuario'] = $usuario;
}

try {
    $controller::$acao($pdo, $usuario);
} catch (DomainException $e) {
    // Regras de negócio violadas viram mensagem para o usuário, sem alterar nada.
    $voltar = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '/';
    redirecionar($voltar . (($q = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY)) ? "?$q" : ''), $e->getMessage(), 'erro');
}
