<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Carteira;
use App\Loja;
use App\Materias;
use App\Neuronio;
use App\Sessoes;
use DateTimeImmutable;
use PDO;

final class FocoController
{
    public static function index(PDO $pdo, array $usuario): void
    {
        $id = (int) $usuario['id'];
        $ativa = Sessoes::ativa($pdo, $id);
        $materias = Materias::listar($pdo, $id);

        if (!$ativa && !$materias) {
            redirecionar('/materias', 'Crie sua primeira matéria para começar a estudar.');
        }

        render('foco', [
            'ativa' => $ativa,
            'fim' => $ativa ? Sessoes::fimPrevisto($ativa) : null,
            'materias' => $materias,
            'neuronio' => Neuronio::progresso($pdo, $id),
            'celebracao' => Neuronio::consumirCelebracao($pdo, $id),
            'equipados' => Loja::equipados($pdo, $id),
        ]);
    }

    public static function iniciar(PDO $pdo, array $usuario): void
    {
        $minutos = (int) ($_POST['minutos'] ?? 0);
        if (($_POST['minutos'] ?? '') === 'outro') {
            $minutos = (int) ($_POST['minutos_livre'] ?? 0);
        }
        Sessoes::iniciar($pdo, (int) $usuario['id'], (int) ($_POST['materia_id'] ?? 0), $minutos, new DateTimeImmutable());
        redirecionar('/foco');
    }

    /** Chamado pelo cronômetro quando chega a 00:00. Responde em JSON. */
    public static function concluir(PDO $pdo, array $usuario): void
    {
        $id = (int) $usuario['id'];
        $sessaoId = (int) ($_POST['sessao_id'] ?? 0);

        try {
            $resultado = Sessoes::concluir($pdo, $id, $sessaoId, new DateTimeImmutable());
        } catch (\DomainException $e) {
            json(['ok' => false, 'erro' => $e->getMessage()], 409);
        }

        if (!$resultado) {
            // Já concluída (outra aba, clique repetido) ou abandonada: nada é concedido de novo.
            $stmt = $pdo->prepare('SELECT status FROM sessoes_foco WHERE id = ? AND usuario_id = ?');
            $stmt->execute([$sessaoId, $id]);
            json(['ok' => $stmt->fetchColumn() === 'concluida', 'repetida' => true]);
        }

        $celebracao = Neuronio::consumirCelebracao($pdo, $id);
        $neuronio = Neuronio::progresso($pdo, $id);
        $equipados = Loja::equipados($pdo, $id);
        json([
            'ok' => true,
            'minutos' => $resultado['minutos'],
            'xp_ganho' => $resultado['xp_ganho'],
            'moedas_ganhas' => $resultado['moedas_ganhas'],
            'saldo' => Carteira::saldo($pdo, $id),
            'neuronio_html' => parcial('neuronio_card', [
                'neuronio' => $neuronio,
                'equipados' => $equipados,
            ]),
            'celebracao' => $celebracao === null ? null : parcial('celebracao', ['estagio' => $celebracao, 'equipados' => $equipados]),
        ]);
    }

    public static function abandonar(PDO $pdo, array $usuario): void
    {
        $resultado = Sessoes::abandonar($pdo, (int) $usuario['id'], (int) ($_POST['sessao_id'] ?? 0), new DateTimeImmutable());
        if ($resultado) {
            redirecionar('/', sprintf('O tempo já tinha acabado: sessão concluída, +%d XP e +1 moeda.', $resultado['xp_ganho']), 'sucesso');
        }
        redirecionar('/', 'Sessão abandonada. Seu neurônio não recebeu esse tempo.');
    }
}
