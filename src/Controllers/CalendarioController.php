<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Materias;
use App\Tarefas;
use DateTimeImmutable;
use PDO;

/** Calendário de tarefas do protótipo (congelado no MVP). */
final class CalendarioController
{
    public static function index(PDO $pdo, array $usuario): void
    {
        $id = (int) $usuario['id'];
        $hoje = date('Y-m-d');

        // Dia selecionado e mês exibido: ?dia=AAAA-MM-DD ou ?mes=AAAA-MM
        $diaSelecionado = data_valida($_GET['dia'] ?? null);
        $mes = (string) ($_GET['mes'] ?? '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $mes = substr($diaSelecionado ?? $hoje, 0, 7);
        }
        if ($diaSelecionado === null || substr($diaSelecionado, 0, 7) !== $mes) {
            $diaSelecionado = substr($hoje, 0, 7) === $mes ? $hoje : $mes . '-01';
        }

        $primeiro = new DateTimeImmutable($mes . '-01');
        // A grade começa no domingo antes do dia 1 e termina no sábado após o último dia.
        $inicioGrade = $primeiro->modify('-' . $primeiro->format('w') . ' days');
        $ultimo = $primeiro->modify('last day of this month');
        $fimGrade = $ultimo->modify('+' . (6 - (int) $ultimo->format('w')) . ' days');

        $tarefasPorDia = [];
        foreach (Tarefas::doPeriodo($pdo, $id, $inicioGrade->format('Y-m-d'), $fimGrade->format('Y-m-d')) as $t) {
            $tarefasPorDia[$t['data']][] = $t;
        }

        render('calendario', [
            'hoje' => $hoje,
            'mes' => $mes,
            'primeiro' => $primeiro,
            'inicioGrade' => $inicioGrade,
            'fimGrade' => $fimGrade,
            'diaSelecionado' => $diaSelecionado,
            'tarefasPorDia' => $tarefasPorDia,
            'resumoMes' => Tarefas::resumoDoMes($pdo, $id, $mes),
            'materias' => Materias::listar($pdo, $id),
        ]);
    }

    public static function acao(PDO $pdo, array $usuario): void
    {
        $id = (int) $usuario['id'];
        $tarefa = (int) ($_POST['id'] ?? 0);
        $dia = data_valida($_POST['dia'] ?? null);
        $voltar = '/calendario' . ($dia ? '?dia=' . $dia : '');

        switch ($_POST['acao'] ?? '') {
            case 'nova_tarefa':
                if ($dia === null) {
                    redirecionar($voltar, 'Dia inválido.', 'erro');
                }
                $semanas = Tarefas::criar($pdo, $id, (int) ($_POST['materia_id'] ?? 0), (string) ($_POST['titulo'] ?? ''), $dia, (int) ($_POST['repetir'] ?? 0));
                redirecionar($voltar, $semanas ? "Tarefa repetida nas próximas $semanas semanas." : '');

            case 'editar_tarefa':
                // Chamado pelo fetch da edição no lugar: responde JSON em vez de redirecionar.
                try {
                    $editadas = Tarefas::editar($pdo, $id, $tarefa, (string) ($_POST['titulo'] ?? ''),
                        (int) ($_POST['materia_id'] ?? 0), ($_POST['proximas'] ?? '') === '1');
                } catch (\DomainException $e) {
                    json(['ok' => false, 'erro' => $e->getMessage()], 422);
                }
                json(['ok' => $editadas > 0, 'editadas' => $editadas], $editadas ? 200 : 404);

            case 'alternar_tarefa':
                Tarefas::alternar($pdo, $id, $tarefa);
                redirecionar($voltar);

            case 'tempo_tarefa':
                Tarefas::definirMinutos($pdo, $id, $tarefa, (int) ($_POST['minutos'] ?? 0));
                redirecionar($voltar);

            case 'mover_tarefa':
                $nova = data_valida($_POST['data'] ?? null);
                if ($nova === null) {
                    redirecionar($voltar, 'Data inválida.', 'erro');
                }
                Tarefas::mover($pdo, $id, $tarefa, $nova);
                redirecionar($voltar);

            case 'excluir_tarefa':
                $excluidas = Tarefas::excluir($pdo, $id, $tarefa, ($_POST['proximas'] ?? '') === '1');
                redirecionar($voltar, $excluidas > 1 ? "$excluidas tarefas excluídas." : 'Tarefa excluída.');
        }
        redirecionar($voltar);
    }
}
