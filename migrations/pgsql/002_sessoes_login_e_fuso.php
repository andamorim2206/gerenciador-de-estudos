<?php

// 1. Sessões de login (PHP) guardadas no banco, e não em arquivos: em hospedagens como a Vercel,
//    cada instância tem seu próprio disco temporário, e o login se perderia entre requisições.
// 2. Fuso horário do banco fixado em Brasília (vale para as próximas conexões), em vez de mandá-lo
//    em cada conexão, o que poolers como o PgBouncer não aceitam.

return function (PDO $pdo): void {
    $pdo->exec('
        CREATE TABLE sessoes_login (
            id text PRIMARY KEY,
            dados text NOT NULL,
            atualizada_em timestamp(0) NOT NULL
        )
    ');
    $pdo->exec('CREATE INDEX idx_sessoes_login_atualizada ON sessoes_login(atualizada_em)');

    // Sem permissão para alterar o banco (alguns provedores), segue sem: o app grava os horários
    // vindos do PHP, que já usa o horário de Brasília.
    $pdo->exec("
        DO \$\$
        BEGIN
            EXECUTE format('ALTER DATABASE %I SET TimeZone TO %L', current_database(), 'America/Sao_Paulo');
        EXCEPTION WHEN insufficient_privilege THEN
            RAISE NOTICE 'sem permissão para definir o fuso do banco';
        END
        \$\$
    ");
};
