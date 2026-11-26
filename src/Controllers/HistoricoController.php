<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Estatisticas;
use App\Materias;
use DateTimeImmutable;
use PDO;

final class HistoricoController
{
    private const PAGINA_DIAS = 30;

    public static function index(PDO $pdo, array $usuario): void
    {
        $id = (int) $usuario['id'];
        $hoje = new DateTimeImmutable('today');
        $dias = max(self::PAGINA_DIAS, min(366, (int) ($_GET['dias'] ?? self::PAGINA_DIAS)));
        $materiaId = (int) ($_GET['materia'] ?? 0) ?: null;

        render('historico', [
            'materias' => Materias::listar($pdo, $id, incluirArquivadas: true),
            'materiaId' => $materiaId,
            'dias' => $dias,
            'porDia' => Estatisticas::historico($pdo, $id, $hoje, $dias, $materiaId),
            'temMais' => $dias < 366 && Estatisticas::temSessoesAntesDe($pdo, $id, $hoje, $dias),
            'proximaPagina' => $dias + self::PAGINA_DIAS,
        ]);
    }
}
