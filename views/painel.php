<?php
/**
 * @var array $neuronio
 * @var int|null $celebracao
 * @var array $equipados
 * @var array $resumo
 * @var int $saldo
 * @var array|null $ativa
 */
$titulo = 'Painel';
$maiorMateria = max(1, ...array_map(fn($m) => (int) $m['minutos'], $resumo['por_materia'] ?: [['minutos' => 1]]));
?>
<div class="painel">
    <section class="card destaque">
        <?= parcial('neuronio_card', ['neuronio' => $neuronio, 'equipados' => $equipados, 'ativo' => (bool) $ativa]) ?>
        <a class="botao botao-grande" href="/foco"><?= $ativa ? 'Retomar sessão' : 'Iniciar foco' ?></a>
    </section>

    <section class="numeros">
        <div class="card numero-card"><span class="rotulo">Hoje</span><strong><?= duracao($resumo['hoje']) ?></strong></div>
        <div class="card numero-card"><span class="rotulo">Últimos 7 dias</span><strong><?= duracao($resumo['semana']) ?></strong></div>
        <div class="card numero-card"><span class="rotulo">Total</span><strong><?= duracao($resumo['total']) ?></strong></div>
        <a class="card numero-card" href="/carteira"><span class="rotulo">Moedas</span><strong><i class="moeda"></i> <?= $saldo ?></strong></a>
    </section>

    <section class="card">
        <h2>Horas por matéria <span class="suave">· últimos 7 dias</span></h2>
        <?php if (!$resumo['sessoes']): ?>
            <p class="vazio">Nenhuma sessão ainda. Sua primeira sessão de foco dá vida ao neurônio, com XP e 1 moeda.</p>
            <a class="botao" href="/foco">Começar a primeira sessão</a>
        <?php elseif (!$resumo['por_materia']): ?>
            <p class="vazio">Nenhuma sessão concluída nos últimos 7 dias.</p>
        <?php else: ?>
            <ul class="barras">
                <?php foreach ($resumo['por_materia'] as $m): ?>
                    <li>
                        <span class="nome"><i class="bolinha <?= cor((int) $m['cor']) ?>"></i><?= e($m['nome']) ?></span>
                        <span class="trilho"><span class="<?= cor((int) $m['cor']) ?>" style="width: <?= round($m['minutos'] / $maiorMateria * 100) ?>%"></span></span>
                        <span class="valor"><?= duracao((int) $m['minutos']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php if ($celebracao !== null): ?>
    <?= parcial('celebracao', ['estagio' => $celebracao, 'equipados' => $equipados]) ?>
<?php endif; ?>
