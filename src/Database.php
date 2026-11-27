<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use Throwable;

/**
 * Conexão com o banco. O principal é o PostgreSQL (usado quando DB_HOST está definido);
 * sem DB_HOST, cai no SQLite em data/estudos.sqlite (ou ESTUDOS_DB), como antes.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $config = self::config();
            self::$pdo = self::conectar($config);
            self::migrar(self::$pdo, $config);
        }
        return self::$pdo;
    }

    /** Configuração lida das variáveis de ambiente. */
    public static function config(): array
    {
        if (getenv('DB_HOST')) {
            return [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST'),
                'port' => getenv('DB_PORT') ?: '5432',
                'dbname' => getenv('DB_NAME') ?: 'estudos',
                'user' => getenv('DB_USER') ?: 'estudos',
                'password' => getenv('DB_PASSWORD') ?: '',
                'schema' => null,
            ];
        }
        // ESTUDOS_DB permite apontar para outro arquivo (ex.: uma cópia para testar).
        return ['driver' => 'sqlite', 'arquivo' => getenv('ESTUDOS_DB') ?: RAIZ . '/data/estudos.sqlite'];
    }

    public static function conectar(array $config): PDO
    {
        $opcoes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($config['driver'] === 'pgsql') {
            // Datas gravadas e comparadas no horário de Brasília, como no resto do app.
            $parametros = '-c TimeZone=America/Sao_Paulo';
            if ($config['schema']) {
                $parametros .= ' -c search_path=' . $config['schema'] . ',public';
            }
            $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;options='%s'",
                $config['host'], $config['port'], $config['dbname'], $parametros);
            return new PDO($dsn, $config['user'], $config['password'], $opcoes);
        }

        $arquivo = $config['arquivo'];
        $dir = dirname($arquivo);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $arquivo, options: $opcoes + [
            PDO::ATTR_TIMEOUT => 10, // espera o lock de escrita em vez de falhar na hora
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    public static function ehPostgres(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    /** A exceção veio de um índice/restrição UNIQUE (nos dois bancos). */
    public static function violaUnicidade(PDOException $e): bool
    {
        return $e->getCode() === '23505' || str_contains($e->getMessage(), 'UNIQUE');
    }

    /**
     * Executa $fn numa transação. Qualquer exceção desfaz tudo.
     * No SQLite, BEGIN IMMEDIATE pega o lock de escrita desde o início. No PostgreSQL, a
     * concorrência é garantida pelos locks de linha: os UPDATEs condicionais (saldo >= preço,
     * status = 'em_andamento') esperam a transação concorrente e reavaliam a condição.
     */
    public static function transacao(PDO $pdo, callable $fn): mixed
    {
        $pdo->exec(self::ehPostgres($pdo) ? 'BEGIN' : 'BEGIN IMMEDIATE');
        try {
            $resultado = $fn($pdo);
            $pdo->exec('COMMIT');
            return $resultado;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Aplica as migrações ainda não registradas em schema_migrations:
     * migrations/pgsql/*.php no PostgreSQL, migrations/*.php no SQLite.
     */
    public static function migrar(PDO $pdo, array $config): void
    {
        $postgres = self::ehPostgres($pdo);
        if ($postgres) {
            // Dois processos subindo ao mesmo tempo não aplicam a mesma migração duas vezes.
            $pdo->exec('SELECT pg_advisory_lock(714023)');
        }
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                versao TEXT PRIMARY KEY,
                aplicada_em TEXT NOT NULL
            )');
            $aplicadas = $pdo->query('SELECT versao FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

            $pendentes = [];
            foreach (glob(RAIZ . '/migrations/' . ($postgres ? 'pgsql/' : '') . '*.php') as $caminho) {
                $versao = basename($caminho, '.php');
                if (!in_array($versao, $aplicadas, true)) {
                    $pendentes[$versao] = $caminho;
                }
            }
            if (!$pendentes) {
                return;
            }
            ksort($pendentes);

            $postgres
                ? self::aplicar($pdo, $pendentes)
                : self::migrarSqlite($pdo, $config['arquivo'], $aplicadas, $pendentes);
        } finally {
            if ($postgres) {
                $pdo->exec('SELECT pg_advisory_unlock(714023)');
            }
        }
    }

    private static function migrarSqlite(PDO $pdo, string $arquivo, array $aplicadas, array $pendentes): void
    {
        // Cópia de segurança antes de mexer num banco que já tem dados.
        if ($aplicadas || $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'materias'")->fetchColumn()) {
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            copy($arquivo, $arquivo . '.backup-' . date('Ymd-His'));
        }

        // Reconstruir tabelas exige as FKs desligadas (senão DROP TABLE apaga em cascata).
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            self::aplicar($pdo, $pendentes, function (PDO $pdo, string $versao) {
                if ($pdo->query('PRAGMA foreign_key_check')->fetchAll()) {
                    throw new \RuntimeException("Migração $versao deixou chaves estrangeiras inválidas.");
                }
            });
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    /** Cada migração roda na sua própria transação, junto com o registro em schema_migrations. */
    private static function aplicar(PDO $pdo, array $pendentes, ?callable $conferir = null): void
    {
        foreach ($pendentes as $versao => $caminho) {
            $migracao = require $caminho;
            self::transacao($pdo, function (PDO $pdo) use ($migracao, $versao, $conferir) {
                $migracao($pdo);
                if ($conferir) {
                    $conferir($pdo, $versao);
                }
                $pdo->prepare('INSERT INTO schema_migrations (versao, aplicada_em) VALUES (?, ?)')
                    ->execute([$versao, agora()]);
            });
        }
    }
}
