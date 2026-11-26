<?php

declare(strict_types=1);

namespace App;

use PDO;

/** Progressão do neurônio: 1 XP por minuto de sessão concluída. Nada aqui envolve moedas. */
final class Neuronio
{
    /**
     * Estágios em ordem: [XP mínimo, nome, descrição].
     * Os limites são uma hipótese a ajustar com o Davi; o estágio salvo nunca regride se eles mudarem.
     */
    public const ESTAGIOS = [
        [0, 'Neurônio em repouso', 'Só o corpo celular, esperando o primeiro estímulo.'],
        [60, 'Primeiros dendritos', 'As primeiras ramificações começam a surgir.'],
        [240, 'Dendritos ramificados', 'Os dendritos se dividem e captam mais sinais.'],
        [600, 'Axônio formado', 'Seu neurônio já consegue transmitir impulsos.'],
        [1200, 'Mielinização', 'A bainha de mielina acelera cada impulso.'],
        [2400, 'Primeiras sinapses', 'Os terminais do axônio se conectam a outros neurônios.'],
        [4800, 'Rede neural', 'Seu neurônio agora faz parte de uma rede.'],
    ];

    public static function estagioPara(int $xp): int
    {
        $estagio = 0;
        foreach (self::ESTAGIOS as $i => [$minimo]) {
            if ($xp >= $minimo) {
                $estagio = $i;
            }
        }
        return $estagio;
    }

    public static function progresso(PDO $pdo, int $usuarioId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM progresso_neuronio WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);
        return self::descrever($stmt->fetch() ?: ['xp_total' => 0, 'estagio_atual' => 0, 'ultimo_estagio_visto' => 0]);
    }

    /** Dados prontos para exibir: estágio, nome e progresso até o próximo. */
    public static function descrever(array $linha): array
    {
        $xp = (int) $linha['xp_total'];
        $estagio = (int) $linha['estagio_atual'];
        [$minimoAtual, $nome, $descricao] = self::ESTAGIOS[$estagio];
        $proximo = self::ESTAGIOS[$estagio + 1] ?? null;

        $percentual = 100;
        if ($proximo) {
            $faixa = $proximo[0] - $minimoAtual;
            $percentual = (int) floor(max(0, min($faixa, $xp - $minimoAtual)) / $faixa * 100);
        }

        return [
            'xp' => $xp,
            'estagio' => $estagio,
            'nome' => $nome,
            'descricao' => $descricao,
            'proximo_xp' => $proximo[0] ?? null,
            'proximo_nome' => $proximo[1] ?? null,
            'percentual' => $percentual,
            'ultimo_visto' => (int) $linha['ultimo_estagio_visto'],
        ];
    }

    /**
     * Soma XP de uma sessão concluída. Deve ser chamada dentro da transação que concluiu a sessão.
     * O estágio salvo é o maior já alcançado: nunca regride.
     */
    public static function somarXp(PDO $pdo, int $usuarioId, int $xp, string $agora): array
    {
        $stmt = $pdo->prepare('SELECT xp_total, estagio_atual FROM progresso_neuronio WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);
        $atual = $stmt->fetch();
        if (!$atual) {
            throw new \RuntimeException('Usuário sem progresso do neurônio.');
        }

        $novoXp = (int) $atual['xp_total'] + $xp;
        $estagioAntes = (int) $atual['estagio_atual'];
        $estagioDepois = max($estagioAntes, self::estagioPara($novoXp));

        $pdo->prepare('UPDATE progresso_neuronio SET xp_total = ?, estagio_atual = ?, atualizado_em = ? WHERE usuario_id = ?')
            ->execute([$novoXp, $estagioDepois, $agora, $usuarioId]);

        return ['xp_total' => $novoXp, 'estagio_antes' => $estagioAntes, 'estagio_depois' => $estagioDepois];
    }

    /**
     * Se o usuário subiu de estágio e ainda não viu a celebração, devolve o estágio e marca como visto.
     * Assim a celebração aparece uma única vez, mesmo se a sessão terminou com a aba fechada.
     */
    public static function consumirCelebracao(PDO $pdo, int $usuarioId): ?int
    {
        $stmt = $pdo->prepare('UPDATE progresso_neuronio SET ultimo_estagio_visto = estagio_atual
                               WHERE usuario_id = ? AND estagio_atual > ultimo_estagio_visto
                               RETURNING estagio_atual');
        $stmt->execute([$usuarioId]);
        $estagio = $stmt->fetchColumn();
        return $estagio === false ? null : (int) $estagio;
    }
}
