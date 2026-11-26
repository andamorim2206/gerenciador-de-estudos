<?php
/**
 * Neurônio com estágio, XP e progresso até o próximo estágio.
 * @var array $neuronio  saída de Neuronio::progresso()
 * @var array $equipados
 */
$ativo ??= false;
?>
<div class="neuronio-card" data-neuronio>
    <?= parcial('neuronio', ['estagio' => $neuronio['estagio'], 'equipados' => $equipados, 'ativo' => $ativo]) ?>
    <div class="neuronio-info">
        <p class="estagio-rotulo">Estágio <?= $neuronio['estagio'] ?> de <?= count(App\Neuronio::ESTAGIOS) - 1 ?></p>
        <h2 class="estagio-nome"><?= e($neuronio['nome']) ?></h2>
        <p class="suave"><?= e($neuronio['descricao']) ?></p>
        <?php if ($neuronio['proximo_xp'] !== null): ?>
            <div class="barra-xp" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $neuronio['percentual'] ?>">
                <span style="width: <?= $neuronio['percentual'] ?>%"></span>
            </div>
            <p class="xp">
                <strong><?= number_format($neuronio['xp'], 0, ',', '.') ?></strong> de <?= number_format($neuronio['proximo_xp'], 0, ',', '.') ?> XP
                <span class="suave">· próximo: <?= e($neuronio['proximo_nome']) ?></span>
            </p>
        <?php else: ?>
            <p class="xp"><strong><?= number_format($neuronio['xp'], 0, ',', '.') ?> XP</strong> <span class="suave">· estágio máximo</span></p>
        <?php endif; ?>
    </div>
</div>
