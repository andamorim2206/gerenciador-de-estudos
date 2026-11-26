<?php
/**
 * @var string $hoje
 * @var string $mes
 * @var DateTimeImmutable $primeiro
 * @var DateTimeImmutable $inicioGrade
 * @var DateTimeImmutable $fimGrade
 * @var string $diaSelecionado
 * @var array $tarefasPorDia
 * @var array $resumoMes
 * @var array $materias
 */
use App\Tarefas;

const MAX_CHIPS = 3;

$titulo = 'Calendário';
$mesAnterior = $primeiro->modify('-1 month')->format('Y-m');
$proximoMes = $primeiro->modify('+1 month')->format('Y-m');
$tarefasDoDia = $tarefasPorDia[$diaSelecionado] ?? [];
$minutosDoDia = array_sum(array_column($tarefasDoDia, 'minutos'));
$progresso = $resumoMes['total'] ? (int) round($resumoMes['feitas'] / $resumoMes['total'] * 100) : 0;
$campos = fn(string $acao, array $extras = []) => campos_form(['acao' => $acao, 'dia' => $diaSelecionado] + $extras);
?>
<div class="cabecalho-secao">
    <h1>Calendário</h1>
    <p class="resumo">
        <?= $resumoMes['feitas'] ?> de <?= $resumoMes['total'] ?> tarefas concluídas
        <span class="barra"><span style="width: <?= $progresso ?>%"></span></span>
        <?= $progresso ?>% <span class="so-desktop">neste mês</span>
    </p>
</div>
<p class="suave">Planeje o que estudar em cada dia. O tempo que conta para o neurônio vem das sessões de <a href="/foco">foco</a>.</p>

<div class="layout">
    <section class="card calendario">
        <nav class="mes">
            <a class="botao-nav" href="?mes=<?= $mesAnterior ?>" title="Mês anterior">‹</a>
            <h2><?= MESES[(int) $primeiro->format('n') - 1] ?> <?= $primeiro->format('Y') ?></h2>
            <a class="botao-nav" href="?mes=<?= $proximoMes ?>" title="Próximo mês">›</a>
            <span class="dica so-desktop">Clique numa tarefa para editar · arraste para outro dia</span>
            <a class="hoje" href="?dia=<?= $hoje ?>">Hoje</a>
        </nav>

        <div class="grade">
            <?php foreach (SEMANA as $nomeDia): ?>
                <div class="dia-semana"><?= $nomeDia ?></div>
            <?php endforeach; ?>

            <?php for ($d = $inicioGrade; $d <= $fimGrade; $d = $d->modify('+1 day')):
                $data = $d->format('Y-m-d');
                $doDia = $tarefasPorDia[$data] ?? [];
                $minutos = array_sum(array_column($doDia, 'minutos'));
                $classes = ['dia'];
                if ($d->format('Y-m') !== $mes) $classes[] = 'fora';
                if ($data === $hoje) $classes[] = 'e-hoje';
                if ($data === $diaSelecionado) $classes[] = 'selecionado';
                if ($doDia && !array_filter($doDia, fn($t) => !$t['concluida'])) $classes[] = 'completo';
                ?>
                <a class="<?= implode(' ', $classes) ?>" href="?dia=<?= $data ?>" data-data="<?= $data ?>" draggable="false">
                    <span class="cabeca">
                        <span class="numero"><?= $d->format('j') ?></span>
                        <?php if ($minutos): ?><span class="tempo"><?= duracao($minutos) ?></span><?php endif; ?>
                    </span>
                    <?php foreach (array_slice($doDia, 0, MAX_CHIPS) as $t): ?>
                        <span class="chip <?= cor((int) $t['materia_cor']) ?> <?= $t['concluida'] ? 'feita' : '' ?>"
                              draggable="true" data-tarefa="<?= (int) $t['id'] ?>"
                              title="<?= e($t['materia'] . ': ' . $t['titulo']) ?>"><?= e($t['titulo']) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($doDia) > MAX_CHIPS): ?>
                        <span class="mais">+<?= count($doDia) - MAX_CHIPS ?></span>
                    <?php endif; ?>
                    <?php if ($doDia): ?>
                        <span class="pontos">
                            <?php foreach (array_slice($doDia, 0, 4) as $t): ?>
                                <i class="<?= cor((int) $t['materia_cor']) ?>"></i>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endfor; ?>
        </div>
    </section>

    <aside class="lateral">
        <section class="card">
            <h2 class="titulo-dia">
                <?= e(data_extenso($diaSelecionado)) ?>
                <?php if ($minutosDoDia): ?><span class="tempo"><?= duracao($minutosDoDia) ?></span><?php endif; ?>
            </h2>

            <?php if ($materias): ?>
                <form method="post" action="/calendario" class="nova-tarefa">
                    <?= $campos('nova_tarefa') ?>
                    <input type="text" name="titulo" placeholder="O que vou estudar?" required maxlength="200">
                    <div class="linha">
                        <select name="materia_id" required>
                            <?php foreach ($materias as $m): ?>
                                <option value="<?= (int) $m['id'] ?>"><?= e($m['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="repetir" title="Repetição">
                            <?php foreach (Tarefas::REPETICOES as $semanas => $rotulo): ?>
                                <option value="<?= $semanas ?>"><?= $rotulo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button>Adicionar</button>
                </form>
            <?php else: ?>
                <p class="vazio"><a href="/materias">Cadastre uma matéria</a> para começar a planejar seus estudos.</p>
            <?php endif; ?>

            <?php if (!$tarefasDoDia): ?>
                <p class="vazio">Nada planejado para este dia.</p>
            <?php else: ?>
                <ul class="lista tarefas">
                    <?php foreach ($tarefasDoDia as $t): ?>
                        <li class="<?= $t['concluida'] ? 'feita' : '' ?>" draggable="true" data-tarefa="<?= (int) $t['id'] ?>"
                            data-titulo="<?= e($t['titulo']) ?>" data-materia-id="<?= (int) $t['materia_id'] ?>"
                            data-materia="<?= e($t['materia']) ?>" data-serie="<?= $t['serie'] ? '1' : '' ?>">
                            <form method="post" action="/calendario">
                                <?= $campos('alternar_tarefa', ['id' => $t['id']]) ?>
                                <button class="check" title="<?= $t['concluida'] ? 'Desmarcar' : 'Concluir' ?>"><?= $t['concluida'] ? '✓' : '' ?></button>
                            </form>
                            <div class="info">
                                <span class="titulo" data-editar="titulo" tabindex="0" role="button" title="Clique para editar">
                                    <span data-texto><?= e($t['titulo']) ?></span>
                                    <?php if ($t['serie']): ?><span class="repete" title="Repete toda semana">↻</span><?php endif; ?>
                                </span>
                                <span class="meta">
                                    <span class="tag <?= cor((int) $t['materia_cor']) ?>" data-editar="materia" tabindex="0" role="button" title="Clique para trocar a matéria"><?= e($t['materia']) ?></span>
                                    <form method="post" action="/calendario" class="campo-mini" title="Tempo planejado ou anotado (não conta XP)">
                                        <?= $campos('tempo_tarefa', ['id' => $t['id']]) ?>
                                        <span aria-hidden="true">⏱</span>
                                        <input type="number" name="minutos" value="<?= (int) $t['minutos'] ?: '' ?>"
                                               min="0" max="<?= Tarefas::MAX_MINUTOS ?>" step="5" placeholder="0"
                                               aria-label="Minutos" onchange="this.form.submit()">
                                        <span>min</span>
                                    </form>
                                    <form method="post" action="/calendario" class="campo-mini" title="Mover para outro dia">
                                        <?= $campos('mover_tarefa', ['id' => $t['id']]) ?>
                                        <span aria-hidden="true">📅</span>
                                        <input type="date" name="data" value="<?= e($t['data']) ?>"
                                               aria-label="Mover para o dia" onchange="this.form.submit()">
                                    </form>
                                </span>
                            </div>
                            <form method="post" action="/calendario" data-excluir-tarefa="<?= $t['serie'] ? 'serie' : 'unica' ?>">
                                <?= $campos('excluir_tarefa', ['id' => $t['id'], 'proximas' => 0]) ?>
                                <button class="icone" title="Excluir">✕</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>

<script>
    window.DIA_SELECIONADO = <?= json_encode($diaSelecionado) ?>;
    window.MATERIAS = <?= json_encode(array_map(fn($m) => ['id' => (int) $m['id'], 'nome' => $m['nome']], $materias), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="/assets/js/calendario.js"></script>
