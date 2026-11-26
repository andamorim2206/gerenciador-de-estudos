<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Materias;
use PDO;

final class MateriasController
{
    public static function index(PDO $pdo, array $usuario): void
    {
        render('materias', ['materias' => Materias::listar($pdo, (int) $usuario['id'], incluirArquivadas: true)]);
    }

    public static function criar(PDO $pdo, array $usuario): void
    {
        $cor = ($_POST['cor'] ?? '') === '' ? null : (int) $_POST['cor'];
        Materias::criar($pdo, (int) $usuario['id'], (string) ($_POST['nome'] ?? ''), $cor);
        redirecionar('/materias', 'Matéria criada.', 'sucesso');
    }

    public static function editar(PDO $pdo, array $usuario): void
    {
        Materias::editar($pdo, (int) $usuario['id'], (int) ($_POST['id'] ?? 0), (string) ($_POST['nome'] ?? ''), (int) ($_POST['cor'] ?? 0));
        redirecionar('/materias', 'Matéria atualizada.', 'sucesso');
    }

    public static function arquivar(PDO $pdo, array $usuario): void
    {
        $resultado = Materias::arquivarOuExcluir($pdo, (int) $usuario['id'], (int) ($_POST['id'] ?? 0));
        redirecionar('/materias', $resultado === 'arquivada'
            ? 'Matéria arquivada. Suas sessões e o XP dela continuam no histórico.'
            : 'Matéria excluída.');
    }

    public static function reativar(PDO $pdo, array $usuario): void
    {
        Materias::reativar($pdo, (int) $usuario['id'], (int) ($_POST['id'] ?? 0));
        redirecionar('/materias', 'Matéria reativada.', 'sucesso');
    }
}
