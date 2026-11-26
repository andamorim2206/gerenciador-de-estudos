<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

/**
 * Calendário de tarefas do protótipo, congelado no MVP: funciona como antes, agora por usuário.
 * Tarefas não geram XP nem moedas — só sessões de foco geram.
 */
final class Tarefas
{
    public const REPETICOES = [0 => 'Não repetir', 4 => 'Toda semana, por 4 semanas', 8 => 'Toda semana, por 8 semanas',
        12 => 'Toda semana, por 12 semanas', 26 => 'Toda semana, por 6 meses'];
    public const MAX_MINUTOS = 1440;

    /** Restringe qualquer operação às tarefas das matérias do usuário. */
    private const DO_USUARIO = 'materia_id IN (SELECT id FROM materias WHERE usuario_id = ?)';

    public static function doPeriodo(PDO $pdo, int $usuarioId, string $inicio, string $fim): array
    {
        $stmt = $pdo->prepare('
            SELECT t.*, m.nome AS materia, m.cor AS materia_cor
            FROM tarefas t JOIN materias m ON m.id = t.materia_id
            WHERE m.usuario_id = ? AND t.data BETWEEN ? AND ?
            ORDER BY t.concluida, t.id
        ');
        $stmt->execute([$usuarioId, $inicio, $fim]);
        return $stmt->fetchAll();
    }

    public static function resumoDoMes(PDO $pdo, int $usuarioId, string $mes): array
    {
        $primeiro = new DateTimeImmutable($mes . '-01');
        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS total, COALESCE(SUM(concluida), 0) AS feitas
            FROM tarefas WHERE ' . self::DO_USUARIO . ' AND data BETWEEN ? AND ?
        ');
        $stmt->execute([$usuarioId, $primeiro->format('Y-m-d'), $primeiro->modify('last day of this month')->format('Y-m-d')]);
        return array_map('intval', $stmt->fetch());
    }

    public static function criar(PDO $pdo, int $usuarioId, int $materiaId, string $titulo, string $dia, int $semanas): int
    {
        $titulo = trim($titulo);
        if ($titulo === '' || mb_strlen($titulo) > 200) {
            throw new \DomainException('Informe o que vai estudar.');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM materias WHERE id = ? AND usuario_id = ? AND arquivada_em IS NULL');
        $stmt->execute([$materiaId, $usuarioId]);
        if (!$stmt->fetchColumn()) {
            throw new \DomainException('Escolha uma das suas matérias.');
        }

        $semanas = array_key_exists($semanas, self::REPETICOES) ? $semanas : 0;
        $serie = $semanas ? bin2hex(random_bytes(8)) : null;

        Database::transacao($pdo, function (PDO $pdo) use ($materiaId, $titulo, $dia, $semanas, $serie) {
            $stmt = $pdo->prepare('INSERT INTO tarefas (materia_id, titulo, data, serie) VALUES (?, ?, ?, ?)');
            $data = new DateTimeImmutable($dia);
            for ($i = 0; $i < max(1, $semanas); $i++) {
                $stmt->execute([$materiaId, $titulo, $data->modify("+$i week")->format('Y-m-d'), $serie]);
            }
        });
        return $semanas;
    }

    /**
     * Renomeia a tarefa e/ou troca a matéria. Com $proximas, aplica também às próximas
     * repetições da mesma série. Devolve quantas tarefas foram alteradas.
     */
    public static function editar(PDO $pdo, int $usuarioId, int $id, string $titulo, int $materiaId, bool $proximas): int
    {
        $titulo = trim($titulo);
        if ($titulo === '' || mb_strlen($titulo) > 200) {
            throw new \DomainException('Informe o que vai estudar.');
        }
        $stmt = $pdo->prepare('SELECT data, serie, materia_id FROM tarefas WHERE id = ? AND ' . self::DO_USUARIO);
        $stmt->execute([$id, $usuarioId]);
        $tarefa = $stmt->fetch();
        if (!$tarefa) {
            return 0;
        }
        if ($materiaId !== (int) $tarefa['materia_id']) {
            $stmt = $pdo->prepare('SELECT 1 FROM materias WHERE id = ? AND usuario_id = ? AND arquivada_em IS NULL');
            $stmt->execute([$materiaId, $usuarioId]);
            if (!$stmt->fetchColumn()) {
                throw new \DomainException('Escolha uma das suas matérias.');
            }
        }
        if ($tarefa['serie'] && $proximas) {
            $stmt = $pdo->prepare('UPDATE tarefas SET titulo = ?, materia_id = ? WHERE serie = ? AND data >= ? AND ' . self::DO_USUARIO);
            $stmt->execute([$titulo, $materiaId, $tarefa['serie'], $tarefa['data'], $usuarioId]);
            return $stmt->rowCount();
        }
        $pdo->prepare('UPDATE tarefas SET titulo = ?, materia_id = ? WHERE id = ?')->execute([$titulo, $materiaId, $id]);
        return 1;
    }

    public static function alternar(PDO $pdo, int $usuarioId, int $id): void
    {
        $pdo->prepare('UPDATE tarefas SET concluida = 1 - concluida WHERE id = ? AND ' . self::DO_USUARIO)
            ->execute([$id, $usuarioId]);
    }

    public static function definirMinutos(PDO $pdo, int $usuarioId, int $id, int $minutos): void
    {
        $minutos = max(0, min(self::MAX_MINUTOS, $minutos));
        $pdo->prepare('UPDATE tarefas SET minutos = ? WHERE id = ? AND ' . self::DO_USUARIO)
            ->execute([$minutos, $id, $usuarioId]);
    }

    public static function mover(PDO $pdo, int $usuarioId, int $id, string $data): void
    {
        $pdo->prepare('UPDATE tarefas SET data = ? WHERE id = ? AND ' . self::DO_USUARIO)
            ->execute([$data, $id, $usuarioId]);
    }

    /** Exclui a tarefa e, se pedido, as próximas repetições da mesma série. Devolve quantas foram excluídas. */
    public static function excluir(PDO $pdo, int $usuarioId, int $id, bool $proximas): int
    {
        $stmt = $pdo->prepare('SELECT data, serie FROM tarefas WHERE id = ? AND ' . self::DO_USUARIO);
        $stmt->execute([$id, $usuarioId]);
        $tarefa = $stmt->fetch();
        if (!$tarefa) {
            return 0;
        }
        if ($tarefa['serie'] && $proximas) {
            $stmt = $pdo->prepare('DELETE FROM tarefas WHERE serie = ? AND data >= ? AND ' . self::DO_USUARIO);
            $stmt->execute([$tarefa['serie'], $tarefa['data'], $usuarioId]);
            return $stmt->rowCount();
        }
        $pdo->prepare('DELETE FROM tarefas WHERE id = ?')->execute([$id]);
        return 1;
    }
}
