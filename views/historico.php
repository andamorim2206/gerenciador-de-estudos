<?php
/**
 * @var array $materias
 * @var int|null $materiaId
 * @var int $dias
 * @var array $porDia
 * @var bool $temMais
 * @var int $proximaPagina
 */
$titulo = 'Histórico';
?>
<section class="card">
    <div class="cabecalho-secao">
        <h1>Histórico de estudos</h1>
        <form method="get" class="filtro">
            <label class="so-leitor" for="filtro-materia">Matéria</label>
            <select id="filtro-materia" name="materia" onchange="this.form.submit()">
                <option value="">Todas as matérias</option>
                <?php foreach ($materias as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= $materiaId === (int) $m['id'] ? 'selected' : '' ?>>
                        <?= e($m['nome']) ?><?= $m['arquivada_em'] ? ' (arquivada)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($dias > 30): ?><input type="hidden" name="dias" value="<?= $dias ?>"><?php endif; ?>
        </form>
    </div>
    <p class="suave">Últimos <?= $dias ?> dias. Sessões concluídas não podem ser editadas nem excluídas.</p>

    <?php if (!$porDia): ?>
        <p class="vazio">Nenhuma sessão neste período.</p>
    <?php endif; ?>

    <?php foreach ($porDia as $dia => $grupo): ?>
        <div class="dia-historico">
            <h2><?= e(data_extenso($dia)) ?> <span class="tempo"><?= duracao($grupo['minutos']) ?></span></h2>
            <ul class="lista">
                <?php foreach ($grupo['sessoes'] as $s): ?>
                    <li class="<?= $s['status'] === 'abandonada' ? 'abandonada' : '' ?>">
                        <i class="bolinha <?= cor((int) $s['materia_cor']) ?>"></i>
                        <span class="info">
                            <span><?= e($s['materia']) ?></span>
                            <span class="meta">
                                <?= substr($s['iniciada_em'], 11, 5) ?>–<?= substr((string) $s['encerrada_em'], 11, 5) ?>
                                · <?= duracao((int) $s['duracao_real_min']) ?> de <?= (int) $s['duracao_planejada_min'] ?>min
                            </span>
                        </span>
                        <?php if ($s['status'] === 'concluida'): ?>
                            <span class="recompensa">+<?= (int) $s['duracao_real_min'] ?> XP · +1 <i class="moeda pequena"></i></span>
                        <?php else: ?>
                            <span class="etiqueta">Abandonada</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <?php if ($temMais): ?>
        <a class="botao secundario" href="?<?= e(http_build_query(array_filter(['materia' => $materiaId, 'dias' => $proximaPagina]))) ?>">Carregar mais 30 dias</a>
    <?php endif; ?>
</section>
