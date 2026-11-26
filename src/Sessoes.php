<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

/** Sessões de foco em contagem regressiva, sem pausa. */
final class Sessoes
{
    public const MIN_MINUTOS = 10;
    public const MAX_MINUTOS = 180;
    public const DURACOES_RAPIDAS = [15, 25, 50, 90];
    /** Folga para diferença de relógio entre navegador e servidor ao concluir. */
    private const TOLERANCIA_SEGUNDOS = 3;

    /** Inicia uma sessão, ou devolve a que já está em andamento (só pode haver uma). */
    public static function iniciar(PDO $pdo, int $usuarioId, int $materiaId, int $minutos, DateTimeImmutable $agora): int
    {
        if ($minutos < self::MIN_MINUTOS || $minutos > self::MAX_MINUTOS) {
            throw new \DomainException(sprintf('A duração deve ficar entre %d e %d minutos.', self::MIN_MINUTOS, self::MAX_MINUTOS));
        }

        return Database::transacao($pdo, function (PDO $pdo) use ($usuarioId, $materiaId, $minutos, $agora): int {
            $ativa = self::ativa($pdo, $usuarioId);
            if ($ativa) {
                return (int) $ativa['id'];
            }

            $stmt = $pdo->prepare('SELECT 1 FROM materias WHERE id = ? AND usuario_id = ? AND arquivada_em IS NULL');
            $stmt->execute([$materiaId, $usuarioId]);
            if (!$stmt->fetchColumn()) {
                throw new \DomainException('Escolha uma das suas matérias.');
            }

            $inicio = $agora->format('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO sessoes_foco (usuario_id, materia_id, iniciada_em, duracao_planejada_min, criada_em)
                           VALUES (?, ?, ?, ?, ?)')
                ->execute([$usuarioId, $materiaId, $inicio, $minutos, $inicio]);
            return (int) $pdo->lastInsertId();
        });
    }

    public static function ativa(PDO $pdo, int $usuarioId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT s.*, m.nome AS materia, m.cor AS materia_cor
            FROM sessoes_foco s JOIN materias m ON m.id = s.materia_id
            WHERE s.usuario_id = ? AND s.status = 'em_andamento'
        ");
        $stmt->execute([$usuarioId]);
        return $stmt->fetch() ?: null;
    }

    public static function fimPrevisto(array $sessao): DateTimeImmutable
    {
        return (new DateTimeImmutable($sessao['iniciada_em']))->modify('+' . (int) $sessao['duracao_planejada_min'] . ' minutes');
    }

    /**
     * Conclui a sessão e concede as recompensas numa única transação:
     * status → concluida, +XP (minutos estudados) e +1 moeda. Ou tudo acontece, ou nada.
     *
     * Devolve null se a sessão não estava em andamento (já concluída, abandonada ou de outro usuário):
     * chamar de novo nunca concede nada em dobro.
     */
    public static function concluir(PDO $pdo, int $usuarioId, int $sessaoId, DateTimeImmutable $agora): ?array
    {
        return Database::transacao($pdo, function (PDO $pdo) use ($usuarioId, $sessaoId, $agora): ?array {
            $stmt = $pdo->prepare("SELECT * FROM sessoes_foco WHERE id = ? AND usuario_id = ? AND status = 'em_andamento'");
            $stmt->execute([$sessaoId, $usuarioId]);
            $sessao = $stmt->fetch();
            if (!$sessao) {
                return null;
            }

            $inicio = new DateTimeImmutable($sessao['iniciada_em']);
            $fim = self::fimPrevisto($sessao);
            if ($agora->getTimestamp() < $fim->getTimestamp() - self::TOLERANCIA_SEGUNDOS) {
                throw new \DomainException('A sessão ainda não terminou.');
            }

            // Duração calculada pelo servidor; quem volta depois do fim recebe a duração planejada.
            $encerrada = $agora < $fim ? $agora : $fim;
            $minutos = min(
                (int) $sessao['duracao_planejada_min'],
                (int) round(($encerrada->getTimestamp() - $inicio->getTimestamp()) / 60)
            );
            $agoraTexto = $agora->format('Y-m-d H:i:s');

            $atualizar = $pdo->prepare("
                UPDATE sessoes_foco
                SET status = 'concluida', encerrada_em = ?, duracao_real_min = ?, recompensada_em = ?
                WHERE id = ? AND status = 'em_andamento'
            ");
            $atualizar->execute([$encerrada->format('Y-m-d H:i:s'), $minutos, $agoraTexto, $sessaoId]);
            if ($atualizar->rowCount() !== 1) {
                return null;
            }

            Carteira::creditarSessao($pdo, $usuarioId, $sessaoId, $agoraTexto);
            $progresso = Neuronio::somarXp($pdo, $usuarioId, $minutos, $agoraTexto);

            return [
                'sessao_id' => $sessaoId,
                'minutos' => $minutos,
                'xp_ganho' => $minutos,
                'moedas_ganhas' => 1,
            ] + $progresso;
        });
    }

    /**
     * Desistir: a sessão vira abandonada e não gera XP nem moeda.
     * Se o tempo já tinha acabado, ela é concluída normalmente.
     */
    public static function abandonar(PDO $pdo, int $usuarioId, int $sessaoId, DateTimeImmutable $agora): ?array
    {
        $sessao = self::ativa($pdo, $usuarioId);
        if (!$sessao || (int) $sessao['id'] !== $sessaoId) {
            return null;
        }
        if ($agora >= self::fimPrevisto($sessao)) {
            return self::concluir($pdo, $usuarioId, $sessaoId, $agora);
        }

        $minutos = intdiv($agora->getTimestamp() - (new DateTimeImmutable($sessao['iniciada_em']))->getTimestamp(), 60);
        $pdo->prepare("
            UPDATE sessoes_foco SET status = 'abandonada', encerrada_em = ?, duracao_real_min = ?
            WHERE id = ? AND usuario_id = ? AND status = 'em_andamento'
        ")->execute([$agora->format('Y-m-d H:i:s'), max(0, $minutos), $sessaoId, $usuarioId]);
        return null;
    }

    /** Conclui a sessão que terminou enquanto o usuário estava fora. Chamada a cada requisição. */
    public static function finalizarExpirada(PDO $pdo, int $usuarioId, DateTimeImmutable $agora): ?array
    {
        $sessao = self::ativa($pdo, $usuarioId);
        if ($sessao && $agora >= self::fimPrevisto($sessao)) {
            return self::concluir($pdo, $usuarioId, (int) $sessao['id'], $agora);
        }
        return null;
    }
}
