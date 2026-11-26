<?php

declare(strict_types=1);

// Todas as datas do app são no horário de Brasília (a sessão pertence ao dia em que começou).
date_default_timezone_set('America/Sao_Paulo');

const RAIZ = __DIR__ . '/..';

spl_autoload_register(function (string $classe): void {
    if (str_starts_with($classe, 'App\\')) {
        $arquivo = __DIR__ . '/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
        if (is_file($arquivo)) {
            require $arquivo;
        }
    }
});

require __DIR__ . '/helpers.php';
