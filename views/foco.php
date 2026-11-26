<?php
/**
 * @var array|null $ativa
 * @var DateTimeImmutable|null $fim
 * @var array $materias
 * @var array $neuronio
 * @var int|null $celebracao
 * @var array $equipados
 */
use App\Sessoes;

$titulo = 'Foco';
?>
<?php if ($ativa): ?>
    <section class="card foco-ativo" data-foco
             data-sessao="<?= (int) $ativa['id'] ?>"
             data-fim="<?= $fim->getTimestamp() * 1000 ?>"
             data-agora="<?= time() * 1000 ?>">
        <p class="materia-foco"><i class="bolinha <?= cor((int) $ativa['materia_cor']) ?>"></i><?= e($ativa['materia']) ?> · <?= (int) $ativa['duracao_planejada_min'] ?> min</p>
        <p class="cronometro" data-cronometro aria-live="off">--:--</p>
        <div data-neuronio-area>
            <?= parcial('neuronio_card', ['neuronio' => $neuronio, 'equipados' => $equipados, 'ativo' => true]) ?>
        </div>
        <div class="resultado" data-resultado hidden></div>
        <form method="post" action="/foco/abandonar" data-desistir>
            <?= campos_form(['sessao_id' => $ativa['id']]) ?>
            <button class="perigo-suave">Desistir</button>
        </form>
        <p class="suave dica">Você pode fechar a aba: a sessão continua contando e é concluída quando você voltar.</p>
    </section>
<?php else: ?>
    <section class="card foco-novo">
        <h1>Nova sessão de foco</h1>
        <p class="suave">Sem pausa: se desistir, o tempo não conta para o neurônio nem rende moeda.</p>
        <form method="post" action="/foco/iniciar" class="pilha" data-form-foco>
            <?= campos_form() ?>
            <label>Matéria
                <select name="materia_id" required>
                    <?php foreach ($materias as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= e($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <fieldset class="duracoes">
                <legend>Duração</legend>
                <?php foreach (Sessoes::DURACOES_RAPIDAS as $i => $min): ?>
                    <label class="pilula"><input type="radio" name="minutos" value="<?= $min ?>" <?= $min === 25 ? 'checked' : '' ?>> <?= $min ?> min</label>
                <?php endforeach; ?>
                <label class="pilula"><input type="radio" name="minutos" value="outro"> Outro</label>
                <label class="minutos-livre" hidden>
                    <input type="number" name="minutos_livre" min="<?= Sessoes::MIN_MINUTOS ?>" max="<?= Sessoes::MAX_MINUTOS ?>" step="1" placeholder="<?= Sessoes::MIN_MINUTOS ?>–<?= Sessoes::MAX_MINUTOS ?>"> min
                </label>
            </fieldset>
            <button class="botao-grande">Começar</button>
        </form>
        <p class="suave">Cada minuto concluído vale 1 XP para o neurônio. Cada sessão concluída vale 1 moeda.</p>
    </section>
    <section class="card">
        <?= parcial('neuronio_card', ['neuronio' => $neuronio, 'equipados' => $equipados]) ?>
    </section>
<?php endif; ?>

<?php if ($celebracao !== null): ?>
    <?= parcial('celebracao', ['estagio' => $celebracao, 'equipados' => $equipados]) ?>
<?php endif; ?>
<script src="/assets/js/foco.js"></script>
