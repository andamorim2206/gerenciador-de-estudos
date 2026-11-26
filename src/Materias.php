<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Materias
{
    public static function listar(PDO $pdo, int $usuarioId, bool $incluirArquivadas = false): array
    {
        $sql = 'SELECT m.*,
                       (SELECT COUNT(*) FROM sessoes_foco s WHERE s.materia_id = m.id AND s.status = \'concluida\') AS sessoes,
                       (SELECT COALESCE(SUM(s.duracao_real_min), 0) FROM sessoes_foco s
                        WHERE s.materia_id = m.id AND s.status = \'concluida\') AS minutos
                FROM materias m WHERE m.usuario_id = ?'
            . ($incluirArquivadas ? '' : ' AND m.arquivada_em IS NULL')
            . ' ORDER BY m.arquivada_em IS NOT NULL, m.nome COLLATE NOCASE';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll();
    }

    public static function criar(PDO $pdo, int $usuarioId, string $nome, ?int $cor = null): int
    {
        $nome = self::validarNome($nome);
        $cor ??= self::proximaCor($pdo, $usuarioId);
        try {
            $pdo->prepare('INSERT INTO materias (usuario_id, nome, cor, criada_em) VALUES (?, ?, ?, ?)')
                ->execute([$usuarioId, $nome, self::validarCor($cor), agora()]);
        } catch (PDOException $e) {
            throw self::traduzir($e);
        }
        return (int) $pdo->lastInsertId();
    }

    public static function editar(PDO $pdo, int $usuarioId, int $id, string $nome, int $cor): void
    {
        try {
            $pdo->prepare('UPDATE materias SET nome = ?, cor = ? WHERE id = ? AND usuario_id = ?')
                ->execute([self::validarNome($nome), self::validarCor($cor), $id, $usuarioId]);
        } catch (PDOException $e) {
            throw self::traduzir($e);
        }
    }

    /**
     * Matéria com sessões ou tarefas é arquivada (o histórico e o XP continuam intactos);
     * sem nada ligado a ela, é excluída de vez. Devolve 'arquivada' ou 'excluida'.
     */
    public static function arquivarOuExcluir(PDO $pdo, int $usuarioId, int $id): string
    {
        return Database::transacao($pdo, function (PDO $pdo) use ($usuarioId, $id): string {
            $stmt = $pdo->prepare('SELECT
                    (SELECT COUNT(*) FROM sessoes_foco WHERE materia_id = m.id)
                  + (SELECT COUNT(*) FROM tarefas WHERE materia_id = m.id)
                FROM materias m WHERE m.id = ? AND m.usuario_id = ?');
            $stmt->execute([$id, $usuarioId]);
            $vinculos = $stmt->fetchColumn();
            if ($vinculos === false) {
                throw new \DomainException('Matéria não encontrada.');
            }
            if ((int) $vinculos > 0) {
                $pdo->prepare('UPDATE materias SET arquivada_em = ? WHERE id = ?')->execute([agora(), $id]);
                return 'arquivada';
            }
            $pdo->prepare('DELETE FROM materias WHERE id = ?')->execute([$id]);
            return 'excluida';
        });
    }

    public static function reativar(PDO $pdo, int $usuarioId, int $id): void
    {
        $pdo->prepare('UPDATE materias SET arquivada_em = NULL WHERE id = ? AND usuario_id = ?')->execute([$id, $usuarioId]);
    }

    /** A primeira cor da paleta ainda não usada pelas matérias ativas. */
    private static function proximaCor(PDO $pdo, int $usuarioId): int
    {
        $stmt = $pdo->prepare('SELECT cor FROM materias WHERE usuario_id = ? AND arquivada_em IS NULL');
        $stmt->execute([$usuarioId]);
        $usadas = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        for ($cor = 0; $cor < TOTAL_CORES; $cor++) {
            if (!in_array($cor, $usadas, true)) {
                return $cor;
            }
        }
        return count($usadas) % TOTAL_CORES;
    }

    private static function validarNome(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '' || mb_strlen($nome) > 60) {
            throw new \DomainException('O nome da matéria deve ter de 1 a 60 caracteres.');
        }
        return $nome;
    }

    private static function validarCor(int $cor): int
    {
        if ($cor < 0 || $cor >= TOTAL_CORES) {
            throw new \DomainException('Cor inválida.');
        }
        return $cor;
    }

    private static function traduzir(PDOException $e): \Throwable
    {
        return str_contains($e->getMessage(), 'UNIQUE') ? new \DomainException('Essa matéria já existe.') : $e;
    }
}
