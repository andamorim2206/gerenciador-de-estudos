<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Carteira;
use App\Loja;
use App\Neuronio;
use PDO;

final class LojaController
{
    public static function index(PDO $pdo, array $usuario): void
    {
        $id = (int) $usuario['id'];
        $porCategoria = array_fill_keys(array_keys(Loja::CATEGORIAS), []);
        foreach (Loja::catalogo($pdo, $id) as $item) {
            $porCategoria[$item['categoria']][] = $item;
        }

        render('loja', [
            'porCategoria' => $porCategoria,
            'saldo' => Carteira::saldo($pdo, $id),
            'neuronio' => Neuronio::progresso($pdo, $id),
            'equipados' => Loja::equipados($pdo, $id),
        ]);
    }

    public static function comprar(PDO $pdo, array $usuario): void
    {
        $item = Loja::comprar($pdo, (int) $usuario['id'], (int) ($_POST['item_id'] ?? 0), agora());
        redirecionar('/loja', $item['nome'] . ' agora faz parte da sua coleção.', 'sucesso');
    }

    public static function equipar(PDO $pdo, array $usuario): void
    {
        Loja::equipar($pdo, (int) $usuario['id'], (string) ($_POST['categoria'] ?? ''), (int) ($_POST['item_usuario_id'] ?? 0));
        redirecionar('/loja');
    }
}
