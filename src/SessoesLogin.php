<?php

declare(strict_types=1);

namespace App;

use PDO;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Guarda as sessões de login do PHP na tabela sessoes_login (só no PostgreSQL).
 * Assim o login vale em qualquer instância do app e sobrevive a reinícios do container.
 * A validade é a de session.gc_maxlifetime, contada desde o último acesso.
 */
final class SessoesLogin implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public static function registrar(PDO $pdo): void
    {
        session_set_save_handler(new self($pdo), true);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare('SELECT dados FROM sessoes_login WHERE id = ? AND atualizada_em >= ?');
        $stmt->execute([$id, $this->validaDesde()]);
        return (string) $stmt->fetchColumn();
    }

    public function write(string $id, string $data): bool
    {
        $this->pdo->prepare('
            INSERT INTO sessoes_login (id, dados, atualizada_em) VALUES (?, ?, ?)
            ON CONFLICT (id) DO UPDATE SET dados = excluded.dados, atualizada_em = excluded.atualizada_em
        ')->execute([$id, $data, agora()]);
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->pdo->prepare('DELETE FROM sessoes_login WHERE id = ?')->execute([$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessoes_login WHERE atualizada_em < ?');
        $stmt->execute([$this->validaDesde()]);
        return $stmt->rowCount();
    }

    /** Com use_strict_mode, o PHP só aceita ids que já existem (impede fixação de sessão). */
    public function validateId(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM sessoes_login WHERE id = ? AND atualizada_em >= ?');
        $stmt->execute([$id, $this->validaDesde()]);
        return (bool) $stmt->fetchColumn();
    }

    /** Sessão lida e não alterada: só renova a validade, sem regravar os dados. */
    public function updateTimestamp(string $id, string $data): bool
    {
        $this->pdo->prepare('UPDATE sessoes_login SET atualizada_em = ? WHERE id = ?')->execute([agora(), $id]);
        return true;
    }

    private function validaDesde(): string
    {
        return date('Y-m-d H:i:s', time() - (int) ini_get('session.gc_maxlifetime'));
    }
}
