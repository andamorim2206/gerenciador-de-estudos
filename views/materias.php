<?php
/** @var array $materias */
$titulo = 'Matérias';
$ativas = array_filter($materias, fn($m) => !$m['arquivada_em']);
$arquivadas = array_filter($materias, fn($m) => $m['arquivada_em']);

$seletorCor = function (string $nome, int $selecionada): string {
    $html = '<span class="cores" role="radiogroup" aria-label="Cor">';
    for ($c = 0; $c < TOTAL_CORES; $c++) {
        $html .= '<label class="cor-opcao ' . cor($c) . '"><input type="radio" name="' . $nome . '" value="' . $c . '"'
            . ($c === $selecionada ? ' checked' : '') . ' aria-label="Cor ' . ($c + 1) . '"><i></i></label>';
    }
    return $html . '</span>';
};
?>
<section class="card">
    <h1>Matérias</h1>
    <form method="post" action="/materias/criar" class="linha">
        <?= campos_form() ?>
        <input type="text" name="nome" placeholder="Ex.: Matemática" required maxlength="60" <?= $ativas ? '' : 'autofocus' ?>>
        <button>Adicionar</button>
    </form>

    <?php if (!$ativas): ?>
        <p class="vazio">Crie sua primeira matéria para começar a estudar.</p>
    <?php else: ?>
        <ul class="lista materias">
            <?php foreach ($ativas as $m): ?>
                <li>
                    <details class="editar-materia">
                        <summary>
                            <i class="bolinha <?= cor((int) $m['cor']) ?>"></i>
                            <span class="info">
                                <span class="nome"><?= e($m['nome']) ?></span>
                                <span class="meta"><?= plural((int) $m['sessoes'], 'sessão', 'sessões') ?> · <?= duracao((int) $m['minutos']) ?></span>
                            </span>
                            <span class="suave editar">Editar</span>
                        </summary>
                        <form method="post" action="/materias/editar" class="pilha">
                            <?= campos_form(['id' => $m['id']]) ?>
                            <input type="text" name="nome" value="<?= e($m['nome']) ?>" required maxlength="60" aria-label="Nome">
                            <?= $seletorCor('cor', (int) $m['cor']) ?>
                            <button>Salvar</button>
                        </form>
                    </details>
                    <form method="post" action="/materias/arquivar"
                          onsubmit="return confirm(<?= e(json_encode((int) $m['sessoes'] ? 'Arquivar esta matéria? As sessões e o XP dela continuam no histórico.' : 'Remover esta matéria? Se ela tiver tarefas no calendário, será arquivada.')) ?>)">
                        <?= campos_form(['id' => $m['id']]) ?>
                        <button class="icone" title="Arquivar">✕</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($arquivadas): ?>
    <section class="card">
        <h2>Arquivadas</h2>
        <p class="suave">Não aparecem no cronômetro, mas continuam no histórico e no XP.</p>
        <ul class="lista">
            <?php foreach ($arquivadas as $m): ?>
                <li>
                    <i class="bolinha <?= cor((int) $m['cor']) ?>"></i>
                    <span class="info">
                        <span class="nome"><?= e($m['nome']) ?></span>
                        <span class="meta"><?= plural((int) $m['sessoes'], 'sessão', 'sessões') ?> · <?= duracao((int) $m['minutos']) ?></span>
                    </span>
                    <form method="post" action="/materias/reativar">
                        <?= campos_form(['id' => $m['id']]) ?>
                        <button class="secundario">Reativar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
