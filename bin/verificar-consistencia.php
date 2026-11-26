<?php

declare(strict_types=1);

// Confere, para todos os usuários, as invariantes da gamificação:
//   saldo da carteira = soma do extrato = saldo_apos da última transação
//   XP = soma das durações das sessões concluídas
//   moedas ganhas = sessões concluídas (exatamente 1 por sessão)
// Sai com código 1 se encontrar alguma divergência.

require __DIR__ . '/../src/bootstrap.php';

use App\Database;

$pdo = Database::pdo();
$problemas = 0;

$usuarios = $pdo->query('
    SELECT u.id, u.email, c.saldo, p.xp_total,
        (SELECT COALESCE(SUM(valor), 0) FROM transacoes_moedas t WHERE t.usuario_id = u.id) AS soma_extrato,
        (SELECT saldo_apos FROM transacoes_moedas t WHERE t.usuario_id = u.id ORDER BY id DESC LIMIT 1) AS ultimo_saldo,
        (SELECT COALESCE(SUM(duracao_real_min), 0) FROM sessoes_foco s WHERE s.usuario_id = u.id AND s.status = \'concluida\') AS soma_sessoes,
        (SELECT COUNT(*) FROM sessoes_foco s WHERE s.usuario_id = u.id AND s.status = \'concluida\') AS concluidas,
        (SELECT COUNT(*) FROM transacoes_moedas t WHERE t.usuario_id = u.id AND t.tipo = \'ganho_sessao\') AS ganhos
    FROM usuarios u
    LEFT JOIN carteiras c ON c.usuario_id = u.id
    LEFT JOIN progresso_neuronio p ON p.usuario_id = u.id
')->fetchAll();

foreach ($usuarios as $u) {
    $erros = [];
    if ($u['saldo'] === null || $u['xp_total'] === null) {
        $erros[] = 'sem carteira ou sem progresso do neurônio';
    } else {
        if ((int) $u['saldo'] !== (int) $u['soma_extrato']) {
            $erros[] = "saldo {$u['saldo']} ≠ soma do extrato {$u['soma_extrato']}";
        }
        if ($u['ultimo_saldo'] !== null && (int) $u['ultimo_saldo'] !== (int) $u['saldo']) {
            $erros[] = "saldo {$u['saldo']} ≠ saldo_apos da última transação {$u['ultimo_saldo']}";
        }
        if ((int) $u['xp_total'] !== (int) $u['soma_sessoes']) {
            $erros[] = "XP {$u['xp_total']} ≠ soma das sessões concluídas {$u['soma_sessoes']}";
        }
    }
    if ((int) $u['concluidas'] !== (int) $u['ganhos']) {
        $erros[] = "{$u['concluidas']} sessões concluídas ≠ {$u['ganhos']} moedas ganhas";
    }

    echo ($erros ? '✗ ' : '✓ ') . $u['email'] . ($erros ? ': ' . implode('; ', $erros) : '') . "\n";
    $problemas += count($erros);
}

echo $problemas ? "\n$problemas divergência(s) encontrada(s).\n" : "\nTudo consistente (" . count($usuarios) . " usuário(s)).\n";
exit($problemas ? 1 : 0);
