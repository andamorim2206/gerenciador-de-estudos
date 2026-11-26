<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Carteira;
use App\Estatisticas;
use App\Loja;
use App\Neuronio;
use App\Sessoes;
use DateTimeImmutable;
use PDO;

final class PainelController
{
    public static function index(PDO $pdo, array $usuario): void
    {
        $id = (int) $usuario['id'];
        render('painel', [
            'neuronio' => Neuronio::progresso($pdo, $id),
            'celebracao' => Neuronio::consumirCelebracao($pdo, $id),
            'equipados' => Loja::equipados($pdo, $id),
            'resumo' => Estatisticas::resumo($pdo, $id, new DateTimeImmutable('today')),
            'saldo' => Carteira::saldo($pdo, $id),
            'ativa' => Sessoes::ativa($pdo, $id),
        ]);
    }
}
