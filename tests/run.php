<?php

declare(strict_types=1);

// Testes das regras de gamificação, sem dependências externas.
// Uso: php tests/run.php
// Com DB_HOST definido, cada teste roda num schema temporário do PostgreSQL; senão, num SQLite temporário.

require __DIR__ . '/../src/bootstrap.php';

use App\Auth;
use App\Carteira;
use App\Database;
use App\Loja;
use App\Materias;
use App\Neuronio;
use App\Sessoes;
use App\Tarefas;

$testes = [];
function teste(string $nome, callable $fn): void
{
    $GLOBALS['testes'][$nome] = $fn;
}

function verificar(bool $condicao, string $mensagem = 'condição falsa'): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function igual(mixed $esperado, mixed $obtido, string $contexto = ''): void
{
    if ($esperado !== $obtido) {
        throw new RuntimeException(($contexto ? "$contexto: " : '') . 'esperado ' . var_export($esperado, true) . ', obtido ' . var_export($obtido, true));
    }
}

function lanca(string $classe, callable $fn, ?string $mensagem = null): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        verificar($e instanceof $classe, "esperava $classe, veio " . get_class($e) . ': ' . $e->getMessage());
        if ($mensagem !== null) {
            igual($mensagem, $e->getMessage(), 'mensagem');
        }
        return;
    }
    throw new RuntimeException("esperava a exceção $classe");
}

function usa_postgres(): bool
{
    return Database::config()['driver'] === 'pgsql';
}

/** Banco novo e migrado; devolve [pdo, config] (a config serve para abrir outras conexões). */
function banco_novo(): array
{
    $config = Database::config();
    if ($config['driver'] === 'pgsql') {
        $config['schema'] = 'teste_' . getmypid() . '_' . count($GLOBALS['temporarios'] ?? []);
        Database::conectar($config)->exec("CREATE SCHEMA {$config['schema']}");
    } else {
        $config['arquivo'] = tempnam(sys_get_temp_dir(), 'estudos-teste-') . '.sqlite';
    }
    $GLOBALS['temporarios'][] = $config;
    $pdo = Database::conectar($config);
    Database::migrar($pdo, $config);
    return [$pdo, $config];
}

function usuario(PDO $pdo, string $email = 'davi@exemplo.com'): int
{
    return Auth::criarUsuario($pdo, $email, 'Davi', 'senha-segura');
}

function momento(string $quando): DateTimeImmutable
{
    return new DateTimeImmutable($quando);
}

/** Inicia e conclui uma sessão de $minutos; devolve o resultado da conclusão. */
function sessao_concluida(PDO $pdo, int $usuarioId, int $materiaId, int $minutos, string $inicio = '2026-11-02 10:00:00'): array
{
    $sessao = Sessoes::iniciar($pdo, $usuarioId, $materiaId, $minutos, momento($inicio));
    return Sessoes::concluir($pdo, $usuarioId, $sessao, momento($inicio)->modify("+$minutos minutes"));
}

function dar_moedas(PDO $pdo, int $usuarioId, int $quantidade): void
{
    Database::transacao($pdo, fn(PDO $pdo) => Carteira::ajustar($pdo, $usuarioId, $quantidade, 'teste', agora()));
}

function item_id(PDO $pdo, string $slug): int
{
    $stmt = $pdo->prepare('SELECT id FROM itens_loja WHERE slug = ?');
    $stmt->execute([$slug]);
    return (int) $stmt->fetchColumn();
}

function contar(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Roda $fn em $n processos paralelos, cada um com sua conexão; devolve quantos terminaram com sucesso. */
function em_paralelo(array $config, int $n, callable $fn): int
{
    $pids = [];
    for ($i = 0; $i < $n; $i++) {
        $resultado = tempnam(sys_get_temp_dir(), 'estudos-paralelo-');
        $pid = pcntl_fork();
        if ($pid === 0) {
            try {
                $ok = $fn(Database::conectar($config), $i);
            } catch (Throwable) {
                $ok = false;
            }
            file_put_contents($resultado, $ok ? '1' : '0');
            // Sai sem rodar destrutores: a conexão herdada do processo pai não pode ser encerrada aqui.
            posix_kill(getmypid(), SIGKILL);
        }
        $pids[$pid] = $resultado;
    }
    $sucessos = 0;
    foreach ($pids as $pid => $resultado) {
        pcntl_waitpid($pid, $status);
        $sucessos += file_get_contents($resultado) === '1' ? 1 : 0;
        unlink($resultado);
    }
    return $sucessos;
}

// ---------------------------------------------------------------------------

teste('usuário novo começa com carteira 0 e neurônio no estágio 0', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    igual(0, Carteira::saldo($pdo, $u));
    $n = Neuronio::progresso($pdo, $u);
    igual(0, $n['estagio']);
    igual(60, $n['proximo_xp']);
});

teste('migração do protótipo preserva matérias e tarefas e descarta as moedas antigas', function () {
    $original = RAIZ . '/data/estudos.antes-do-mvp.sqlite';
    if (!is_file($original) || usa_postgres()) {
        return; // sem banco do protótipo nesta máquina (ou rodando no PostgreSQL, que não tem migração legada)
    }
    $config = ['driver' => 'sqlite', 'arquivo' => tempnam(sys_get_temp_dir(), 'estudos-legado-') . '.sqlite'];
    $GLOBALS['temporarios'][] = $config;
    copy($original, $config['arquivo']);
    $pdo = Database::conectar($config);
    $materias = (int) $pdo->query('SELECT COUNT(*) FROM materias')->fetchColumn();
    $tarefas = (int) $pdo->query('SELECT COUNT(*) FROM tarefas')->fetchColumn();
    Database::migrar($pdo, $config);

    igual($materias, contar($pdo, 'SELECT COUNT(*) FROM materias'));
    igual($tarefas, contar($pdo, 'SELECT COUNT(*) FROM tarefas'));
    igual(0, contar($pdo, "SELECT COUNT(*) FROM sqlite_master WHERE name = 'carteira'"));

    $u = usuario($pdo);
    igual($materias, count(Materias::listar($pdo, $u)), 'primeiro usuário herda as matérias');
    igual(0, Carteira::saldo($pdo, $u));
});

teste('sessão concluída dá XP pelo tempo e exatamente 1 moeda', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');

    $r = sessao_concluida($pdo, $u, $m, 25);
    igual(25, $r['xp_ganho']);
    igual(1, Carteira::saldo($pdo, $u));
    igual(25, Neuronio::progresso($pdo, $u)['xp']);
});

teste('10 min e 90 min rendem 1 moeda cada; XP soma os minutos', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    sessao_concluida($pdo, $u, $m, 10, '2026-11-02 08:00:00');
    sessao_concluida($pdo, $u, $m, 90, '2026-11-02 09:00:00');
    igual(2, Carteira::saldo($pdo, $u));
    igual(100, Neuronio::progresso($pdo, $u)['xp']);
});

teste('não dá para concluir antes do fim', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    $s = Sessoes::iniciar($pdo, $u, $m, 25, momento('2026-11-02 10:00:00'));
    lanca(DomainException::class, fn() => Sessoes::concluir($pdo, $u, $s, momento('2026-11-02 10:20:00')));
    igual(0, Carteira::saldo($pdo, $u));
});

teste('concluir de novo não concede nada em dobro', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    $s = Sessoes::iniciar($pdo, $u, $m, 25, momento('2026-11-02 10:00:00'));
    verificar(Sessoes::concluir($pdo, $u, $s, momento('2026-11-02 10:25:00')) !== null);
    igual(null, Sessoes::concluir($pdo, $u, $s, momento('2026-11-02 10:26:00')));
    igual(null, Sessoes::finalizarExpirada($pdo, $u, momento('2026-11-02 11:00:00')));
    igual(1, Carteira::saldo($pdo, $u));
    igual(25, Neuronio::progresso($pdo, $u)['xp']);
    igual(1, contar($pdo, 'SELECT COUNT(*) FROM transacoes_moedas'));
});

teste('sessão abandonada não rende XP nem moeda, e não pode ser concluída depois', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    $s = Sessoes::iniciar($pdo, $u, $m, 25, momento('2026-11-02 10:00:00'));
    igual(null, Sessoes::abandonar($pdo, $u, $s, momento('2026-11-02 10:20:00')));
    igual(null, Sessoes::concluir($pdo, $u, $s, momento('2026-11-02 10:30:00')));
    igual(0, Carteira::saldo($pdo, $u));
    igual(0, Neuronio::progresso($pdo, $u)['xp']);
    igual('abandonada', $pdo->query("SELECT status FROM sessoes_foco WHERE id = $s")->fetchColumn());
});

teste('sessão que terminou com a aba fechada é concluída no próximo acesso com a duração planejada', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    Sessoes::iniciar($pdo, $u, $m, 50, momento('2026-11-02 10:00:00'));
    igual(null, Sessoes::finalizarExpirada($pdo, $u, momento('2026-11-02 10:30:00')), 'ainda em andamento');
    $r = Sessoes::finalizarExpirada($pdo, $u, momento('2026-11-02 18:00:00'));
    igual(50, $r['minutos']);
    igual(1, Carteira::saldo($pdo, $u));
});

teste('só uma sessão ativa: iniciar de novo devolve a mesma', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    $a = Sessoes::iniciar($pdo, $u, $m, 25, momento('2026-11-02 10:00:00'));
    $b = Sessoes::iniciar($pdo, $u, $m, 50, momento('2026-11-02 10:01:00'));
    igual($a, $b);
});

teste('duração fora de 10–180 minutos é recusada', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    lanca(DomainException::class, fn() => Sessoes::iniciar($pdo, $u, $m, 5, momento('2026-11-02 10:00:00')));
    lanca(DomainException::class, fn() => Sessoes::iniciar($pdo, $u, $m, 200, momento('2026-11-02 10:00:00')));
});

teste('sobe de estágio ao passar de 60 XP e a celebração aparece uma única vez', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    sessao_concluida($pdo, $u, $m, 55, '2026-11-02 08:00:00');
    igual(0, Neuronio::progresso($pdo, $u)['estagio']);
    $r = sessao_concluida($pdo, $u, $m, 10, '2026-11-02 09:00:00');
    igual(1, $r['estagio_depois']);
    igual(1, Neuronio::consumirCelebracao($pdo, $u));
    igual(null, Neuronio::consumirCelebracao($pdo, $u));
});

teste('estágio salvo nunca regride', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $pdo->exec("UPDATE progresso_neuronio SET estagio_atual = 3, xp_total = 100 WHERE usuario_id = $u");
    $r = Neuronio::somarXp($pdo, $u, 5, agora());
    igual(3, $r['estagio_depois']);
});

teste('compra com saldo suficiente debita, adiciona à coleção e registra no extrato', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    dar_moedas($pdo, $u, 5);
    Loja::comprar($pdo, $u, item_id($pdo, 'skin-aurora'), agora());
    igual(2, Carteira::saldo($pdo, $u));
    igual(1, contar($pdo, 'SELECT COUNT(*) FROM itens_usuario WHERE usuario_id = ?', [$u]));
    igual(-3, contar($pdo, "SELECT valor FROM transacoes_moedas WHERE tipo = 'compra'"));
    igual(2, contar($pdo, "SELECT saldo_apos FROM transacoes_moedas WHERE tipo = 'compra'"));
});

teste('compra com saldo insuficiente é recusada sem alterar nada', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    dar_moedas($pdo, $u, 2);
    lanca(DomainException::class, fn() => Loja::comprar($pdo, $u, item_id($pdo, 'skin-aurora'), agora()), 'Falta 1 moeda.');
    igual(2, Carteira::saldo($pdo, $u));
    igual(0, contar($pdo, 'SELECT COUNT(*) FROM itens_usuario'));
    igual(1, contar($pdo, 'SELECT COUNT(*) FROM transacoes_moedas'));
});

teste('o mesmo item não pode ser comprado duas vezes', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    dar_moedas($pdo, $u, 10);
    Loja::comprar($pdo, $u, item_id($pdo, 'skin-aurora'), agora());
    lanca(DomainException::class, fn() => Loja::comprar($pdo, $u, item_id($pdo, 'skin-aurora'), agora()));
    igual(7, Carteira::saldo($pdo, $u));
});

teste('item desativado sai da loja, mas continua na coleção', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    dar_moedas($pdo, $u, 10);
    $item = item_id($pdo, 'skin-aurora');
    Loja::comprar($pdo, $u, $item, agora());
    $pdo->exec("UPDATE itens_loja SET ativo = 0 WHERE id = $item");
    verificar(in_array('skin-aurora', array_column(Loja::catalogo($pdo, $u), 'slug'), true), 'continua na coleção');

    $outro = usuario($pdo, 'outra@exemplo.com');
    dar_moedas($pdo, $outro, 10);
    verificar(!in_array('skin-aurora', array_column(Loja::catalogo($pdo, $outro), 'slug'), true), 'sumiu da loja');
    lanca(DomainException::class, fn() => Loja::comprar($pdo, $outro, $item, agora()));
});

teste('só é possível equipar itens da própria coleção', function () {
    [$pdo] = banco_novo();
    $a = usuario($pdo);
    $b = usuario($pdo, 'outra@exemplo.com');
    dar_moedas($pdo, $a, 5);
    $compra = Loja::comprar($pdo, $a, item_id($pdo, 'skin-aurora'), agora());
    lanca(DomainException::class, fn() => Loja::equipar($pdo, $b, 'skin_neuronio', $compra['item_usuario_id']));
    lanca(DomainException::class, fn() => Loja::equipar($pdo, $a, 'cenario', $compra['item_usuario_id']));
    Loja::equipar($pdo, $a, 'skin_neuronio', $compra['item_usuario_id']);
    igual(['skin_neuronio' => 'skin-aurora'], Loja::equipados($pdo, $a));
    Loja::equipar($pdo, $a, 'skin_neuronio', 0);
    igual([], Loja::equipados($pdo, $a));
});

teste('comprar não altera XP nem estágio', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    foreach (['08', '09', '10'] as $h) {
        sessao_concluida($pdo, $u, $m, 25, "2026-11-02 $h:00:00");
    }
    $antes = Neuronio::progresso($pdo, $u);
    Loja::comprar($pdo, $u, item_id($pdo, 'skin-aurora'), agora());
    $depois = Neuronio::progresso($pdo, $u);
    igual($antes['xp'], $depois['xp']);
    igual($antes['estagio'], $depois['estagio']);
    igual(0, Carteira::saldo($pdo, $u));
});

teste('usuário não conclui sessão nem usa matéria de outro usuário', function () {
    [$pdo] = banco_novo();
    $a = usuario($pdo);
    $b = usuario($pdo, 'outra@exemplo.com');
    $m = Materias::criar($pdo, $a, 'Física');
    lanca(DomainException::class, fn() => Sessoes::iniciar($pdo, $b, $m, 25, momento('2026-11-02 10:00:00')));
    $s = Sessoes::iniciar($pdo, $a, $m, 25, momento('2026-11-02 10:00:00'));
    igual(null, Sessoes::concluir($pdo, $b, $s, momento('2026-11-02 10:30:00')));
    igual(0, Carteira::saldo($pdo, $b));
});

teste('matéria com sessões é arquivada, não excluída; nome repetido é recusado', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Matemática');
    lanca(DomainException::class, fn() => Materias::criar($pdo, $u, ' matemática '), 'Essa matéria já existe.');
    sessao_concluida($pdo, $u, $m, 25);
    igual('arquivada', Materias::arquivarOuExcluir($pdo, $u, $m));
    igual(25, Neuronio::progresso($pdo, $u)['xp']);
    $vazia = Materias::criar($pdo, $u, 'Química');
    igual('excluida', Materias::arquivarOuExcluir($pdo, $u, $vazia));
});

teste('extrato e sessões concluídas são imutáveis no banco', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    $r = sessao_concluida($pdo, $u, $m, 25);
    lanca(PDOException::class, fn() => $pdo->exec('UPDATE transacoes_moedas SET valor = 100'));
    lanca(PDOException::class, fn() => $pdo->exec('DELETE FROM transacoes_moedas'));
    lanca(PDOException::class, fn() => $pdo->exec("UPDATE sessoes_foco SET duracao_real_min = 999 WHERE id = {$r['sessao_id']}"));
    lanca(PDOException::class, fn() => $pdo->exec("DELETE FROM sessoes_foco WHERE id = {$r['sessao_id']}"));
    lanca(PDOException::class, fn() => $pdo->exec("UPDATE carteiras SET saldo = -1"));
});

teste('5 conclusões simultâneas da mesma sessão geram uma única moeda', function () {
    [$pdo, $config] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    $s = Sessoes::iniciar($pdo, $u, $m, 25, momento('2026-11-02 10:00:00'));

    $sucessos = em_paralelo($config, 5, fn(PDO $pdo) =>
        Sessoes::concluir($pdo, $u, $s, momento('2026-11-02 10:25:00')) !== null);

    igual(1, $sucessos, 'conclusões que concederam recompensa');
    igual(1, Carteira::saldo($pdo, $u));
    igual(25, Neuronio::progresso($pdo, $u)['xp']);
    igual(1, contar($pdo, 'SELECT COUNT(*) FROM transacoes_moedas'));
});

teste('compras simultâneas nunca deixam o saldo negativo', function () {
    [$pdo, $config] = banco_novo();
    $u = usuario($pdo);
    dar_moedas($pdo, $u, 6);
    $itens = [item_id($pdo, 'skin-neon'), item_id($pdo, 'cenario-noite')]; // 5 e 6 moedas

    $sucessos = em_paralelo($config, 2, fn(PDO $pdo, int $i) => (bool) Loja::comprar($pdo, $u, $itens[$i], agora()));

    igual(1, $sucessos, 'compras aprovadas');
    verificar(Carteira::saldo($pdo, $u) >= 0);
    igual(1, contar($pdo, 'SELECT COUNT(*) FROM itens_usuario'));
});

teste('compras simultâneas do mesmo item debitam uma única vez', function () {
    [$pdo, $config] = banco_novo();
    $u = usuario($pdo);
    dar_moedas($pdo, $u, 20);
    $item = item_id($pdo, 'skin-aurora');

    $sucessos = em_paralelo($config, 4, fn(PDO $pdo) => (bool) Loja::comprar($pdo, $u, $item, agora()));

    igual(1, $sucessos);
    igual(17, Carteira::saldo($pdo, $u));
});

teste('saldo = soma do extrato e XP = soma das sessões depois de várias operações', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $m = Materias::criar($pdo, $u, 'Física');
    foreach (range(8, 15) as $h) {
        sessao_concluida($pdo, $u, $m, 15 + $h, sprintf('2026-11-02 %02d:00:00', $h));
    }
    Loja::comprar($pdo, $u, item_id($pdo, 'skin-aurora'), agora());
    Loja::comprar($pdo, $u, item_id($pdo, 'skin-neon'), agora());

    igual(contar($pdo, 'SELECT SUM(valor) FROM transacoes_moedas WHERE usuario_id = ?', [$u]), Carteira::saldo($pdo, $u));
    igual(contar($pdo, "SELECT SUM(duracao_real_min) FROM sessoes_foco WHERE status = 'concluida'"), Neuronio::progresso($pdo, $u)['xp']);
    igual(0, Carteira::saldo($pdo, $u));
});

teste('editar tarefa renomeia e troca matéria; em série, só as próximas se pedido', function () {
    [$pdo] = banco_novo();
    $u = usuario($pdo);
    $fis = Materias::criar($pdo, $u, 'Física');
    $qui = Materias::criar($pdo, $u, 'Química');
    Tarefas::criar($pdo, $u, $fis, 'Cinemática', '2026-11-02', 4);
    $ids = array_column(Tarefas::doPeriodo($pdo, $u, '2026-11-01', '2026-12-31'), 'id');
    igual(4, count($ids));

    igual(1, Tarefas::editar($pdo, $u, (int) $ids[0], '  Vetores ', $fis, false));
    igual(3, Tarefas::editar($pdo, $u, (int) $ids[1], 'Dinâmica', $qui, true));
    $titulos = array_column(Tarefas::doPeriodo($pdo, $u, '2026-11-01', '2026-12-31'), 'titulo', 'data');
    igual(['2026-11-02' => 'Vetores', '2026-11-09' => 'Dinâmica', '2026-11-16' => 'Dinâmica', '2026-11-23' => 'Dinâmica'], $titulos);
    igual(3, contar($pdo, 'SELECT COUNT(*) FROM tarefas WHERE materia_id = ?', [$qui]));

    lanca(DomainException::class, fn() => Tarefas::editar($pdo, $u, (int) $ids[0], '   ', $fis, false));
    $outro = usuario($pdo, 'outra@exemplo.com');
    $alheia = Materias::criar($pdo, $outro, 'História');
    lanca(DomainException::class, fn() => Tarefas::editar($pdo, $u, (int) $ids[0], 'X', $alheia, false));
    igual(0, Tarefas::editar($pdo, $outro, (int) $ids[0], 'Invasão', $alheia, false), 'tarefa de outro usuário');
});

teste('cadastro cria conta zerada e recusa e-mail repetido, senha curta e confirmação diferente', function () {
    [$pdo] = banco_novo();
    $_SESSION = [];
    $u = Auth::cadastrar($pdo, 'ana@exemplo.com', 'Ana', 'senha-segura', 'senha-segura');
    igual($u, $_SESSION['usuario_id'], 'já entra logada');
    igual(0, Carteira::saldo($pdo, $u));
    igual(0, Neuronio::progresso($pdo, $u)['xp']);

    lanca(DomainException::class, fn() => Auth::cadastrar($pdo, 'ANA@exemplo.com', 'Outra', 'senha-segura', 'senha-segura'), 'Já existe uma conta com esse e-mail.');
    lanca(DomainException::class, fn() => Auth::cadastrar($pdo, 'b@exemplo.com', 'B', 'curta', 'curta'), 'A senha precisa ter pelo menos 8 caracteres.');
    lanca(DomainException::class, fn() => Auth::cadastrar($pdo, 'b@exemplo.com', 'B', 'senha-segura', 'senha-segurX'), 'As senhas não conferem.');
    lanca(DomainException::class, fn() => Auth::cadastrar($pdo, 'nao-e-email', 'B', 'senha-segura', 'senha-segura'), 'E-mail inválido.');
    igual(1, contar($pdo, 'SELECT COUNT(*) FROM usuarios'));
});

// ---------------------------------------------------------------------------

$falhas = 0;
foreach ($testes as $nome => $fn) {
    try {
        $fn();
        echo "  ✓ $nome\n";
    } catch (Throwable $e) {
        $falhas++;
        echo "  ✗ $nome\n      " . $e->getMessage() . "\n";
    }
}
// Remove os bancos temporários: schemas no PostgreSQL; no SQLite, os arquivos de tempnam, WAL e backups.
foreach ($GLOBALS['temporarios'] ?? [] as $config) {
    if ($config['driver'] === 'pgsql') {
        Database::conectar(['schema' => null] + $config)->exec("DROP SCHEMA {$config['schema']} CASCADE");
        continue;
    }
    array_map('unlink', glob($config['arquivo'] . '*'));
    @unlink(substr($config['arquivo'], 0, -strlen('.sqlite')));
}

echo "\n" . (count($testes) - $falhas) . ' de ' . count($testes) . " testes passaram.\n";
exit($falhas ? 1 : 0);
