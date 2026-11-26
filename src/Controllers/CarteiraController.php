<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Carteira;
use PDO;

final class CarteiraController
{
    public static function index(PDO $pdo, array $usuario): void
    {
        $id = (int) $usuario['id'];
        render('carteira', [
            'saldo' => Carteira::saldo($pdo, $id),
            'extrato' => Carteira::extrato($pdo, $id),
        ]);
    }
}
