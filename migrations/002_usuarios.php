<?php

// Usuários e matérias por usuário.
// As matérias existentes ficam sem dono (usuario_id NULL) até o primeiro usuário ser criado,
// que as herda junto com as tarefas (ver bin/criar-usuario.php).

return function (PDO $pdo): void {
    $pdo->exec('
        CREATE TABLE usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            nome TEXT NOT NULL,
            senha_hash TEXT NOT NULL,
            criado_em TEXT NOT NULL
        )
    ');

    // UNIQUE(nome) global vira único por usuário: o SQLite exige reconstruir a tabela.
    $pdo->exec('
        CREATE TABLE materias_nova (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER REFERENCES usuarios(id) ON DELETE CASCADE,
            nome TEXT NOT NULL CHECK (length(nome) BETWEEN 1 AND 60),
            cor INTEGER NOT NULL DEFAULT 0 CHECK (cor BETWEEN 0 AND 7),
            arquivada_em TEXT,
            criada_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ');
    // Mantém a cor que cada matéria já tinha (antes era calculada por id % 8).
    $pdo->exec('INSERT INTO materias_nova (id, nome, cor) SELECT id, substr(nome, 1, 60), id % 8 FROM materias');
    $pdo->exec('DROP TABLE materias');
    $pdo->exec('ALTER TABLE materias_nova RENAME TO materias');
    $pdo->exec('CREATE UNIQUE INDEX idx_materias_usuario_nome ON materias(usuario_id, nome COLLATE NOCASE)');
};
