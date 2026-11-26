<?php

// Esquema do protótipo (calendário de tarefas), incluindo as migrações que viviam no antigo db.php.
// Em um banco do protótipo, só completa o que faltar; em um banco novo, cria tudo.

return function (PDO $pdo): void {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS materias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL UNIQUE
        );
        CREATE TABLE IF NOT EXISTS tarefas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            materia_id INTEGER NOT NULL REFERENCES materias(id) ON DELETE CASCADE,
            titulo TEXT NOT NULL,
            data TEXT NOT NULL,
            concluida INTEGER NOT NULL DEFAULT 0,
            minutos INTEGER NOT NULL DEFAULT 0,
            serie TEXT,
            criada_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ');

    $colunas = array_column($pdo->query('PRAGMA table_info(tarefas)')->fetchAll(), 'name');

    // Versão em lista: a coluna "prazo" (opcional) virou "data" (dia do estudo).
    if (in_array('prazo', $colunas, true)) {
        $pdo->exec("UPDATE tarefas SET prazo = date(criada_em) WHERE prazo IS NULL OR prazo = ''");
        $pdo->exec('ALTER TABLE tarefas RENAME COLUMN prazo TO data');
    }
    // Primeira versão do calendário: tempo estudado e repetição semanal.
    if (!in_array('minutos', $colunas, true)) {
        $pdo->exec('ALTER TABLE tarefas ADD COLUMN minutos INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('serie', $colunas, true)) {
        $pdo->exec('ALTER TABLE tarefas ADD COLUMN serie TEXT');
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tarefas_data ON tarefas(data)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tarefas_serie ON tarefas(serie)');
};
