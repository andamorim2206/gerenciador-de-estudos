<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Moedas: 1 por sessão concluída, gastas na loja.
 * Toda mudança de saldo grava uma linha no extrato (transacoes_moedas), na mesma transação.
 * Os métodos que alteram saldo devem ser chamados dentro de Database::transacao().
 */
final class Carteira
{
    public static function saldo(PDO $pdo, int $usuarioId): int
    {
        $stmt = $pdo->prepare('SELECT saldo FROM carteiras WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    /** Credita a moeda de uma sessão concluída. O sessao_id único no extrato impede crédito em dobro. */
    public static function creditarSessao(PDO $pdo, int $usuarioId, int $sessaoId, string $agora): int
    {
        self::alterarSaldo($pdo, $usuarioId, 1, $agora);
        return self::registrar($pdo, $usuarioId, 'ganho_sessao', 1, $agora, sessaoId: $sessaoId);
    }

    /** Debita uma compra só se houver saldo. Devolve false (sem alterar nada) se faltar saldo. */
    public static function debitar(PDO $pdo, int $usuarioId, int $valor, string $agora): bool
    {
        $stmt = $pdo->prepare('UPDATE carteiras SET saldo = saldo - ?, atualizado_em = ?
                               WHERE usuario_id = ? AND saldo >= ?');
        $stmt->execute([$valor, $agora, $usuarioId, $valor]);
        return $stmt->rowCount() === 1;
    }

    public static function registrarCompra(PDO $pdo, int $usuarioId, int $itemUsuarioId, int $preco, string $agora): int
    {
        return self::registrar($pdo, $usuarioId, 'compra', -$preco, $agora, itemUsuarioId: $itemUsuarioId);
    }

    /** Correção manual, só por administrador (bin/ajustar-moedas.php), sempre com motivo. */
    public static function ajustar(PDO $pdo, int $usuarioId, int $valor, string $motivo, string $agora): int
    {
        if ($valor === 0 || trim($motivo) === '') {
            throw new \InvalidArgumentException('O ajuste precisa de um valor diferente de zero e de um motivo.');
        }
        if ($valor < 0 && !self::debitar($pdo, $usuarioId, -$valor, $agora)) {
            throw new \DomainException('O ajuste deixaria o saldo negativo.');
        }
        if ($valor > 0) {
            self::alterarSaldo($pdo, $usuarioId, $valor, $agora);
        }
        return self::registrar($pdo, $usuarioId, 'ajuste', $valor, $agora, motivo: trim($motivo));
    }

    /** Extrato do mais recente para o mais antigo, com uma descrição legível de cada linha. */
    public static function extrato(PDO $pdo, int $usuarioId, int $limite = 100): array
    {
        $stmt = $pdo->prepare('
            SELECT t.*, s.duracao_real_min, m.nome AS materia, m.cor AS materia_cor, i.nome AS item
            FROM transacoes_moedas t
            LEFT JOIN sessoes_foco s ON s.id = t.sessao_id
            LEFT JOIN materias m ON m.id = s.materia_id
            LEFT JOIN itens_usuario iu ON iu.id = t.item_usuario_id
            LEFT JOIN itens_loja i ON i.id = iu.item_id
            WHERE t.usuario_id = ?
            ORDER BY t.id DESC
            LIMIT ?
        ');
        $stmt->execute([$usuarioId, $limite]);
        return array_map(function (array $t): array {
            $t['descricao'] = match ($t['tipo']) {
                'ganho_sessao' => 'Sessão de ' . $t['materia'] . ', ' . duracao((int) $t['duracao_real_min']),
                'compra' => 'Compra: ' . $t['item'],
                'ajuste' => 'Ajuste: ' . $t['motivo'],
            };
            return $t;
        }, $stmt->fetchAll());
    }

    private static function alterarSaldo(PDO $pdo, int $usuarioId, int $valor, string $agora): void
    {
        $stmt = $pdo->prepare('UPDATE carteiras SET saldo = saldo + ?, atualizado_em = ? WHERE usuario_id = ?');
        $stmt->execute([$valor, $agora, $usuarioId]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Usuário sem carteira.');
        }
    }

    private static function registrar(PDO $pdo, int $usuarioId, string $tipo, int $valor, string $agora,
                                      ?int $sessaoId = null, ?int $itemUsuarioId = null, ?string $motivo = null): int
    {
        $pdo->prepare('
            INSERT INTO transacoes_moedas (usuario_id, tipo, valor, saldo_apos, sessao_id, item_usuario_id, motivo, criada_em)
            VALUES (?, ?, ?, (SELECT saldo FROM carteiras WHERE usuario_id = ?), ?, ?, ?, ?)
        ')->execute([$usuarioId, $tipo, $valor, $usuarioId, $sessaoId, $itemUsuarioId, $motivo, $agora]);
        return (int) $pdo->lastInsertId();
    }
}
