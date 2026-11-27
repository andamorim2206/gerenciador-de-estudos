<?php

declare(strict_types=1);

// Copia todos os dados de um banco SQLite para o PostgreSQL configurado (DB_HOST etc.).
// Uso: php bin/migrar-sqlite-para-postgres.php [data/estudos.sqlite]
//
// O PostgreSQL precisa estar sem usuários (recém-criado). Tudo roda numa única transação:
// se algo falhar, nada é gravado. Os ids são mantidos, então o extrato e o histórico ficam idênticos.

require __DIR__ . '/../src/bootstrap.php';

use App\Database;

$arquivo = $argv[1] ?? RAIZ . '/data/estudos.sqlite';
if (!is_file($arquivo)) {
    fwrite(STDERR, "Arquivo SQLite não encontrado: $arquivo\n");
    exit(1);
}
$config = Database::config();
if ($config['driver'] !== 'pgsql') {
    fwrite(STDERR, "Defina DB_HOST (e DB_NAME, DB_USER, DB_PASSWORD) para apontar para o PostgreSQL.\n");
    exit(1);
}

// O SQLite de origem é migrado até a última versão antes da cópia (com o backup de sempre).
$origemConfig = ['driver' => 'sqlite', 'arquivo' => $arquivo];
$origem = Database::conectar($origemConfig);
Database::migrar($origem, $origemConfig);

$destino = Database::conectar($config);
Database::migrar($destino, $config);

if ((int) $destino->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
    fwrite(STDERR, "O PostgreSQL já tem usuários; a cópia só é feita num banco novo.\n");
    exit(1);
}

// Em ordem de dependência (chaves estrangeiras).
$tabelas = ['usuarios', 'materias', 'tarefas', 'sessoes_foco', 'progresso_neuronio', 'carteiras',
    'itens_loja', 'itens_usuario', 'itens_equipados', 'transacoes_moedas'];

Database::transacao($destino, function (PDO $destino) use ($origem, $tabelas) {
    // Os itens da loja criados pela migração dão lugar aos do SQLite (mesmos ids das compras).
    $destino->exec('DELETE FROM itens_loja');

    foreach ($tabelas as $tabela) {
        $colunas = $destino->query("SELECT column_name FROM information_schema.columns
            WHERE table_schema = current_schema() AND table_name = '$tabela' ORDER BY ordinal_position")
            ->fetchAll(PDO::FETCH_COLUMN);
        $lista = implode(', ', $colunas);
        $inserir = $destino->prepare("INSERT INTO $tabela ($lista) VALUES (" . implode(', ', array_fill(0, count($colunas), '?')) . ')');

        $linhas = $origem->query("SELECT $lista FROM $tabela")->fetchAll(PDO::FETCH_NUM);
        foreach ($linhas as $linha) {
            $inserir->execute($linha);
        }

        // Próximos ids continuam depois dos copiados.
        if (in_array('id', $colunas, true)) {
            $destino->exec("SELECT setval(pg_get_serial_sequence('$tabela', 'id'), COALESCE((SELECT MAX(id) FROM $tabela), 0) + 1, false)");
        }
        printf("  %-20s %d linha(s)\n", $tabela, count($linhas));
    }
});

echo "\nCópia concluída. Confira com: php bin/verificar-consistencia.php\n";
