<?php
/**
 * @var array $porCategoria
 * @var int $saldo
 * @var array $neuronio
 * @var array $equipados
 */
use App\Loja;

$titulo = 'Loja';
?>
<div class="loja-topo">
    <section class="card">
        <h1>Loja de recompensas</h1>
        <p>Você tem <strong><i class="moeda"></i> <?= plural($saldo, 'moeda', 'moedas') ?></strong>.
            Cada sessão concluída rende 1 moeda.</p>
        <p class="suave">Os itens são só visuais: mudam a aparência do neurônio, nunca o XP ou o estágio. Itens comprados são seus para sempre.</p>
    </section>
    <section class="card previa">
        <?= parcial('neuronio', ['estagio' => $neuronio['estagio'], 'equipados' => $equipados]) ?>
    </section>
</div>

<?php foreach ($porCategoria as $categoria => $itens): ?>
    <?php if (!$itens) continue; ?>
    <section class="card">
        <div class="cabecalho-secao">
            <h2><?= e(Loja::CATEGORIAS[$categoria]) ?></h2>
            <?php if (isset($equipados[$categoria])): ?>
                <form method="post" action="/loja/equipar">
                    <?= campos_form(['categoria' => $categoria, 'item_usuario_id' => 0]) ?>
                    <button class="link">Usar o padrão</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="itens">
            <?php foreach ($itens as $item):
                $tem = $item['item_usuario_id'] !== null;
                $falta = (int) $item['preco'] - $saldo;
                ?>
                <article class="item <?= $item['equipado'] ? 'equipado' : '' ?>">
                    <div class="item-previa">
                        <?= parcial('neuronio', ['estagio' => max(3, $neuronio['estagio']), 'equipados' => [$categoria => $item['slug']]]) ?>
                    </div>
                    <h3><?= e($item['nome']) ?></h3>
                    <p class="suave"><?= e($item['descricao']) ?></p>
                    <?php if ($tem): ?>
                        <?php if ($item['equipado']): ?>
                            <span class="etiqueta ok">Equipado</span>
                        <?php else: ?>
                            <form method="post" action="/loja/equipar">
                                <?= campos_form(['categoria' => $categoria, 'item_usuario_id' => $item['item_usuario_id']]) ?>
                                <button class="secundario">Equipar</button>
                            </form>
                        <?php endif; ?>
                        <span class="suave pequeno">Na coleção</span>
                    <?php else: ?>
                        <form method="post" action="/loja/comprar"
                              onsubmit="return confirm(<?= e(json_encode('Comprar ' . $item['nome'] . ' por ' . plural((int) $item['preco'], 'moeda', 'moedas') . '?')) ?>)">
                            <?= campos_form(['item_id' => $item['id']]) ?>
                            <button <?= $falta > 0 ? 'disabled' : '' ?>><i class="moeda pequena"></i> <?= (int) $item['preco'] ?></button>
                        </form>
                        <?php if ($falta > 0): ?>
                            <span class="suave pequeno"><?= $falta === 1 ? 'Falta 1 moeda' : "Faltam $falta moedas" ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
