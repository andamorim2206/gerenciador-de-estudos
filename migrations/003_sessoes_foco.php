<?php

// Sessões de foco: a única fonte de tempo estudado (XP) e de moedas.
// Horários gravados como 'AAAA-MM-DD HH:MM:SS' no horário de Brasília.

return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE sessoes_foco (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            materia_id INTEGER NOT NULL REFERENCES materias(id) ON DELETE RESTRICT,
            iniciada_em TEXT NOT NULL,
            encerrada_em TEXT,
            duracao_planejada_min INTEGER NOT NULL CHECK (duracao_planejada_min BETWEEN 10 AND 180),
            duracao_real_min INTEGER CHECK (duracao_real_min >= 0),
            status TEXT NOT NULL DEFAULT 'em_andamento'
                CHECK (status IN ('em_andamento', 'concluida', 'abandonada')),
            recompensada_em TEXT,
            criada_em TEXT NOT NULL,
            CHECK (status <> 'concluida' OR (encerrada_em IS NOT NULL AND recompensada_em IS NOT NULL
                AND duracao_real_min IS NOT NULL AND duracao_real_min <= duracao_planejada_min)),
            CHECK (status = 'concluida' OR recompensada_em IS NULL)
        )
    ");
    $pdo->exec('CREATE INDEX idx_sessoes_usuario_status ON sessoes_foco(usuario_id, status, iniciada_em)');
    // No máximo uma sessão em andamento por usuário, garantido pelo banco.
    $pdo->exec("CREATE UNIQUE INDEX idx_sessoes_uma_ativa ON sessoes_foco(usuario_id) WHERE status = 'em_andamento'");
};
