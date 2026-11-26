<?php
/**
 * @var int $estagio  estágio recém-alcançado
 * @var array $equipados
 */
[, $nome, $descricao] = App\Neuronio::ESTAGIOS[$estagio];
?>
<dialog class="celebracao" data-celebracao>
    <p class="estagio-rotulo">Novo estágio!</p>
    <div class="celebracao-neuronio">
        <?= parcial('neuronio', ['estagio' => $estagio, 'equipados' => $equipados ?? []]) ?>
    </div>
    <h2>Seu neurônio evoluiu: <?= e(mb_strtolower($nome)) ?></h2>
    <p class="suave"><?= e($descricao) ?></p>
    <form method="dialog"><button autofocus>Continuar</button></form>
</dialog>
