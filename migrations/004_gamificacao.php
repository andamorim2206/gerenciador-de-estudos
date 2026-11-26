<?php

// Três mecanismos independentes:
//   XP (progresso_neuronio)          ← tempo das sessões concluídas
//   moedas (carteiras + transacoes)  ← quantidade de sessões concluídas
//   loja (itens_loja, itens_usuario, itens_equipados) ← gasta moedas
// O extrato (transacoes_moedas) é a fonte da verdade; carteiras.saldo é uma cópia dele.

return function (PDO $pdo): void {
    // As moedas do protótipo (1 por tarefa) não são migradas: as carteiras começam em 0.
    $pdo->exec('DROP TABLE IF EXISTS carteira');

    $pdo->exec('
        CREATE TABLE progresso_neuronio (
            usuario_id INTEGER PRIMARY KEY REFERENCES usuarios(id) ON DELETE CASCADE,
            xp_total INTEGER NOT NULL DEFAULT 0 CHECK (xp_total >= 0),
            estagio_atual INTEGER NOT NULL DEFAULT 0 CHECK (estagio_atual >= 0),
            ultimo_estagio_visto INTEGER NOT NULL DEFAULT 0,
            atualizado_em TEXT NOT NULL
        )
    ');

    $pdo->exec('
        CREATE TABLE carteiras (
            usuario_id INTEGER PRIMARY KEY REFERENCES usuarios(id) ON DELETE CASCADE,
            saldo INTEGER NOT NULL DEFAULT 0 CHECK (saldo >= 0),
            atualizado_em TEXT NOT NULL
        )
    ');

    $pdo->exec("
        CREATE TABLE itens_loja (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            nome TEXT NOT NULL,
            descricao TEXT NOT NULL,
            categoria TEXT NOT NULL CHECK (categoria IN ('skin_neuronio', 'cenario', 'efeito')),
            preco INTEGER NOT NULL CHECK (preco > 0),
            ativo INTEGER NOT NULL DEFAULT 1,
            ordem INTEGER NOT NULL DEFAULT 0,
            criada_em TEXT NOT NULL
        )
    ");

    $pdo->exec('
        CREATE TABLE itens_usuario (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            item_id INTEGER NOT NULL REFERENCES itens_loja(id) ON DELETE RESTRICT,
            preco_pago INTEGER NOT NULL CHECK (preco_pago > 0),
            adquirido_em TEXT NOT NULL,
            UNIQUE (usuario_id, item_id)
        )
    ');

    $pdo->exec('
        CREATE TABLE itens_equipados (
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            categoria TEXT NOT NULL,
            item_usuario_id INTEGER NOT NULL REFERENCES itens_usuario(id) ON DELETE CASCADE,
            PRIMARY KEY (usuario_id, categoria)
        )
    ');

    $pdo->exec("
        CREATE TABLE transacoes_moedas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            tipo TEXT NOT NULL CHECK (tipo IN ('ganho_sessao', 'compra', 'ajuste')),
            valor INTEGER NOT NULL CHECK (valor <> 0),
            saldo_apos INTEGER NOT NULL CHECK (saldo_apos >= 0),
            sessao_id INTEGER UNIQUE REFERENCES sessoes_foco(id) ON DELETE RESTRICT,
            item_usuario_id INTEGER UNIQUE REFERENCES itens_usuario(id) ON DELETE RESTRICT,
            motivo TEXT,
            criada_em TEXT NOT NULL,
            CHECK (tipo <> 'ganho_sessao' OR (sessao_id IS NOT NULL AND valor = 1 AND item_usuario_id IS NULL)),
            CHECK (tipo <> 'compra' OR (item_usuario_id IS NOT NULL AND valor < 0 AND sessao_id IS NULL)),
            CHECK (tipo <> 'ajuste' OR (motivo IS NOT NULL AND sessao_id IS NULL AND item_usuario_id IS NULL))
        )
    ");
    $pdo->exec('CREATE INDEX idx_transacoes_usuario ON transacoes_moedas(usuario_id, id)');

    // O extrato é só de inclusão: nenhuma transação pode ser alterada ou apagada.
    $pdo->exec("
        CREATE TRIGGER transacoes_sem_update BEFORE UPDATE ON transacoes_moedas
        BEGIN SELECT RAISE(ABORT, 'transações de moedas são imutáveis'); END
    ");
    $pdo->exec("
        CREATE TRIGGER transacoes_sem_delete BEFORE DELETE ON transacoes_moedas
        BEGIN SELECT RAISE(ABORT, 'transações de moedas são imutáveis'); END
    ");

    // Sessões concluídas são imutáveis (já geraram XP e moeda).
    $pdo->exec("
        CREATE TRIGGER sessoes_concluidas_imutaveis BEFORE UPDATE ON sessoes_foco
        WHEN OLD.status = 'concluida'
        BEGIN SELECT RAISE(ABORT, 'sessões concluídas são imutáveis'); END
    ");
    $pdo->exec("
        CREATE TRIGGER sessoes_concluidas_sem_delete BEFORE DELETE ON sessoes_foco
        WHEN OLD.status = 'concluida'
        BEGIN SELECT RAISE(ABORT, 'sessões concluídas são imutáveis'); END
    ");

    $agora = date('Y-m-d H:i:s');
    $itens = $pdo->prepare('INSERT INTO itens_loja (slug, nome, descricao, categoria, preco, ordem, criada_em) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ([
        ['skin-aurora', 'Skin Aurora', 'Dendritos em degradê de verde-água a violeta.', 'skin_neuronio', 3, 1],
        ['skin-neon', 'Skin Neon', 'Um neurônio magenta que brilha no escuro.', 'skin_neuronio', 5, 2],
        ['cenario-noite', 'Noite estrelada', 'Seu neurônio sob um céu de estrelas.', 'cenario', 6, 3],
        ['cenario-biblioteca', 'Biblioteca', 'Tons quentes de madeira e papel antigo.', 'cenario', 6, 4],
        ['efeito-faiscas', 'Faíscas sinápticas', 'Impulsos elétricos percorrendo o axônio.', 'efeito', 8, 5],
    ] as [$slug, $nome, $descricao, $categoria, $preco, $ordem]) {
        $itens->execute([$slug, $nome, $descricao, $categoria, $preco, $ordem, $agora]);
    }
};
