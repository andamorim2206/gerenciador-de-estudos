<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            // ESTUDOS_DB permite apontar para outro arquivo (ex.: uma cópia para testar).
            $arquivo = getenv('ESTUDOS_DB') ?: RAIZ . '/data/estudos.sqlite';
            self::$pdo = self::conectar($arquivo);
            self::migrar(self::$pdo, $arquivo);
        }
        return self::$pdo;
    }

    public static function conectar(string $arquivo): PDO
    {
        $dir = dirname($arquivo);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $arquivo, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10, // espera o lock de escrita em vez de falhar na hora
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    /**
     * Executa $fn numa transação com lock de escrita desde o início (BEGIN IMMEDIATE).
     * Duas operações concorrentes nunca leem o mesmo saldo/status antes de gravar.
     * Qualquer exceção desfaz tudo.
     */
    public static function transacao(PDO $pdo, callable $fn): mixed
    {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $resultado = $fn($pdo);
            $pdo->exec('COMMIT');
            return $resultado;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    /** Aplica as migrações de migrations/ ainda não registradas em schema_migrations. */
    public static function migrar(PDO $pdo, string $arquivo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            versao TEXT PRIMARY KEY,
            aplicada_em TEXT NOT NULL
        )');
        $aplicadas = $pdo->query('SELECT versao FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

        $pendentes = [];
        foreach (glob(RAIZ . '/migrations/*.php') as $caminho) {
            $versao = basename($caminho, '.php');
            if (!in_array($versao, $aplicadas, true)) {
                $pendentes[$versao] = $caminho;
            }
        }
        if (!$pendentes) {
            return;
        }
        ksort($pendentes);

        // Cópia de segurança antes de mexer num banco que já tem dados.
        if ($aplicadas || self::temTabelasLegadas($pdo)) {
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            copy($arquivo, $arquivo . '.backup-' . date('Ymd-His'));
        }

        // Reconstruir tabelas exige as FKs desligadas (senão DROP TABLE apaga em cascata).
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            foreach ($pendentes as $versao => $caminho) {
                $migracao = require $caminho;
                self::transacao($pdo, function (PDO $pdo) use ($migracao, $versao) {
                    $migracao($pdo);
                    $erros = $pdo->query('PRAGMA foreign_key_check')->fetchAll();
                    if ($erros) {
                        throw new \RuntimeException("Migração $versao deixou chaves estrangeiras inválidas.");
                    }
                    $pdo->prepare('INSERT INTO schema_migrations (versao, aplicada_em) VALUES (?, ?)')
                        ->execute([$versao, agora()]);
                });
            }
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    private static function temTabelasLegadas(PDO $pdo): bool
    {
        return (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'materias'")->fetchColumn();
    }
}
