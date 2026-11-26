<?php
/**
 * @var int $saldo
 * @var array $extrato
 */
$titulo = 'Moedas';
?>
<section class="card">
    <div class="cabecalho-secao">
        <h1>Moedas</h1>
        <a class="botao secundario" href="/loja">Ir para a loja</a>
    </div>
    <p class="saldo-grande"><i class="moeda"></i> <?= $saldo ?></p>
    <p class="suave">Cada sessão de foco concluída rende exatamente 1 moeda, seja qual for a duração. Sessões abandonadas não rendem.</p>

    <h2>Extrato</h2>
    <?php if (!$extrato): ?>
        <p class="vazio">Nenhuma transação ainda.</p>
    <?php else: ?>
        <table class="extrato">
            <thead><tr><th>Data</th><th>Descrição</th><th class="num">Valor</th><th class="num">Saldo</th></tr></thead>
            <tbody>
            <?php foreach ($extrato as $t): ?>
                <tr>
                    <td><?= e(date('d/m H:i', strtotime($t['criada_em']))) ?></td>
                    <td><?= e($t['descricao']) ?></td>
                    <td class="num <?= $t['valor'] > 0 ? 'positivo' : 'negativo' ?>"><?= $t['valor'] > 0 ? '+' : '−' ?><?= abs((int) $t['valor']) ?></td>
                    <td class="num"><?= (int) $t['saldo_apos'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
