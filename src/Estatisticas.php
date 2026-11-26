<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

/** Leituras para o painel e o histórico. Só sessões concluídas contam tempo. */
final class Estatisticas
{
    public static function resumo(PDO $pdo, int $usuarioId, DateTimeImmutable $hoje): array
    {
        $dia = $hoje->format('Y-m-d');
        $inicioSemana = $hoje->modify('-6 days')->format('Y-m-d');

        // Datas comparadas como texto 'AAAA-MM-DD HH:MM:SS' para aproveitar o índice.
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN iniciada_em >= :hoje THEN duracao_real_min END), 0) AS hoje,
                COALESCE(SUM(CASE WHEN iniciada_em >= :semana THEN duracao_real_min END), 0) AS semana,
                COALESCE(SUM(duracao_real_min), 0) AS total,
                COUNT(*) AS sessoes
            FROM sessoes_foco
            WHERE usuario_id = :usuario AND status = 'concluida'
        ");
        $stmt->execute(['hoje' => $dia, 'semana' => $inicioSemana, 'usuario' => $usuarioId]);
        $resumo = array_map('intval', $stmt->fetch());

        $stmt = $pdo->prepare("
            SELECT m.nome, m.cor, SUM(s.duracao_real_min) AS minutos
            FROM sessoes_foco s JOIN materias m ON m.id = s.materia_id
            WHERE s.usuario_id = ? AND s.status = 'concluida' AND s.iniciada_em >= ?
            GROUP BY m.id
            ORDER BY minutos DESC
        ");
        $stmt->execute([$usuarioId, $inicioSemana]);
        $resumo['por_materia'] = $stmt->fetchAll();

        return $resumo;
    }

    /**
     * Sessões (concluídas e abandonadas) dos últimos $dias dias, agrupadas por dia, mais recentes primeiro.
     * O total de cada dia soma só as concluídas e respeita o filtro de matéria.
     */
    public static function historico(PDO $pdo, int $usuarioId, DateTimeImmutable $hoje, int $dias, ?int $materiaId): array
    {
        $desde = $hoje->modify('-' . ($dias - 1) . ' days')->format('Y-m-d');
        $sql = "
            SELECT s.*, m.nome AS materia, m.cor AS materia_cor, m.arquivada_em AS materia_arquivada
            FROM sessoes_foco s JOIN materias m ON m.id = s.materia_id
            WHERE s.usuario_id = ? AND s.status <> 'em_andamento' AND s.iniciada_em >= ?"
            . ($materiaId ? ' AND s.materia_id = ?' : '')
            . ' ORDER BY s.iniciada_em DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($materiaId ? [$usuarioId, $desde, $materiaId] : [$usuarioId, $desde]);

        $porDia = [];
        foreach ($stmt->fetchAll() as $s) {
            $dia = substr($s['iniciada_em'], 0, 10);
            $porDia[$dia] ??= ['sessoes' => [], 'minutos' => 0];
            $porDia[$dia]['sessoes'][] = $s;
            if ($s['status'] === 'concluida') {
                $porDia[$dia]['minutos'] += (int) $s['duracao_real_min'];
            }
        }
        return $porDia;
    }

    public static function temSessoesAntesDe(PDO $pdo, int $usuarioId, DateTimeImmutable $hoje, int $dias): bool
    {
        $desde = $hoje->modify('-' . ($dias - 1) . ' days')->format('Y-m-d');
        $stmt = $pdo->prepare("SELECT 1 FROM sessoes_foco WHERE usuario_id = ? AND status <> 'em_andamento' AND iniciada_em < ? LIMIT 1");
        $stmt->execute([$usuarioId, $desde]);
        return (bool) $stmt->fetchColumn();
    }
}
