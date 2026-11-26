// Calendário de tarefas: confirmação de exclusão e arrastar tarefas para outro dia.

document.querySelectorAll('[data-excluir-tarefa]').forEach(form => {
    form.addEventListener('submit', ev => {
        if (!confirm('Excluir esta tarefa?')) return ev.preventDefault();
        if (form.dataset.excluirTarefa === 'serie') {
            form.proximas.value = confirm('Ela se repete toda semana. Excluir também as próximas repetições?') ? '1' : '0';
        }
    });
});

document.querySelectorAll('[data-tarefa]').forEach(el => {
    el.addEventListener('dragstart', ev => {
        ev.stopPropagation();
        ev.dataTransfer.setData('application/x-tarefa', el.dataset.tarefa);
        ev.dataTransfer.effectAllowed = 'move';
        el.classList.add('arrastando');
    });
    el.addEventListener('dragend', () => el.classList.remove('arrastando'));
});

document.querySelectorAll('.dia[data-data]').forEach(dia => {
    dia.addEventListener('dragover', ev => {
        if (!ev.dataTransfer.types.includes('application/x-tarefa')) return;
        ev.preventDefault();
        dia.classList.add('alvo');
    });
    dia.addEventListener('dragleave', () => dia.classList.remove('alvo'));
    dia.addEventListener('drop', async ev => {
        ev.preventDefault();
        dia.classList.remove('alvo');
        const id = ev.dataTransfer.getData('application/x-tarefa');
        if (!id) return;
        const dados = new FormData();
        dados.append('csrf', window.CSRF);
        dados.append('acao', 'mover_tarefa');
        dados.append('id', id);
        dados.append('data', dia.dataset.data);
        dados.append('dia', window.DIA_SELECIONADO);
        try {
            const r = await fetch('/calendario', { method: 'POST', body: dados });
            if (!r.ok) throw new Error(r.status);
            location.reload();
        } catch (e) {
            alert('Não foi possível mover a tarefa. Tente de novo.');
        }
    });
});

// ---------- Edição no lugar: clicou no título ou na matéria, editou ----------
// Enter ou clicar fora salva; Esc cancela.

async function enviar(campos) {
    const dados = new FormData();
    dados.append('csrf', window.CSRF);
    dados.append('dia', window.DIA_SELECIONADO);
    for (const [k, v] of Object.entries(campos)) dados.append(k, v);
    const r = await fetch('/calendario', { method: 'POST', body: dados });
    const json = await r.json().catch(() => ({ ok: false }));
    if (!json.ok) throw new Error(json.erro || 'Não foi possível salvar. Tente de novo.');
    return json;
}

function editarTarefa(li, foco) {
    if (li.querySelector('.edicao')) return;
    const info = li.querySelector('.info');
    const tituloEl = info.querySelector('.titulo');
    const tagEl = info.querySelector('.tag');

    const form = document.createElement('form');
    form.className = 'edicao';
    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 200;
    input.required = true;
    input.value = li.dataset.titulo;
    input.setAttribute('aria-label', 'Título da tarefa');

    const select = document.createElement('select');
    select.setAttribute('aria-label', 'Matéria');
    const materias = [...window.MATERIAS];
    // Matéria arquivada não aparece na lista, mas a tarefa pode continuar nela.
    if (!materias.some(m => String(m.id) === li.dataset.materiaId)) {
        materias.unshift({ id: li.dataset.materiaId, nome: li.dataset.materia });
    }
    for (const m of materias) select.add(new Option(m.nome, m.id, false, String(m.id) === li.dataset.materiaId));

    form.append(input, select);
    tituloEl.hidden = true;
    tagEl.hidden = true;
    info.prepend(form);
    li.draggable = false;
    li.classList.add('editando');
    (foco === 'materia' ? select : input).focus();
    if (foco !== 'materia') input.select();

    let terminado = false;
    function fechar() {
        terminado = true;
        form.remove();
        tituloEl.hidden = false;
        tagEl.hidden = false;
        li.draggable = true;
        li.classList.remove('editando');
    }

    async function salvar() {
        if (terminado) return;
        const titulo = input.value.trim();
        const materiaId = select.value;
        const mudouMateria = materiaId !== li.dataset.materiaId;
        if (!titulo || (titulo === li.dataset.titulo && !mudouMateria)) return fechar();

        terminado = true;
        let proximas = '0';
        if (li.dataset.serie) {
            proximas = confirm('Esta tarefa se repete toda semana. Aplicar a mudança também às próximas repetições?') ? '1' : '0';
        }
        form.classList.add('salvando');
        try {
            await enviar({ acao: 'editar_tarefa', id: li.dataset.tarefa, titulo, materia_id: materiaId, proximas });
        } catch (e) {
            terminado = false;
            form.classList.remove('salvando');
            alert(e.message);
            return input.focus();
        }
        // Trocar matéria muda cores e outras repetições mudam outros dias: recarrega.
        if (mudouMateria || proximas === '1') return location.reload();

        li.dataset.titulo = titulo;
        tituloEl.querySelector('[data-texto]').textContent = titulo;
        document.querySelectorAll(`.chip[data-tarefa="${li.dataset.tarefa}"]`).forEach(chip => {
            chip.textContent = titulo;
            chip.title = li.dataset.materia + ': ' + titulo;
        });
        fechar();
    }

    form.addEventListener('submit', ev => { ev.preventDefault(); salvar(); });
    form.addEventListener('keydown', ev => {
        if (ev.key === 'Escape') { ev.preventDefault(); fechar(); }
        if (ev.key === 'Enter' && ev.target === select) { ev.preventDefault(); salvar(); }
    });
    form.addEventListener('focusout', ev => {
        if (!form.contains(ev.relatedTarget)) salvar();
    });
}

document.querySelectorAll('.lista.tarefas li[data-tarefa]').forEach(li => {
    li.querySelectorAll('[data-editar]').forEach(alvo => {
        alvo.addEventListener('click', () => editarTarefa(li, alvo.dataset.editar));
        alvo.addEventListener('keydown', ev => {
            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); editarTarefa(li, alvo.dataset.editar); }
        });
    });
});

// Clicar numa tarefa da grade abre a edição dela (indo para o dia, se preciso).
document.querySelectorAll('.chip[data-tarefa]').forEach(chip => {
    chip.addEventListener('click', ev => {
        ev.preventDefault();
        ev.stopPropagation();
        const data = chip.closest('.dia').dataset.data;
        const li = document.querySelector(`.lista.tarefas li[data-tarefa="${chip.dataset.tarefa}"]`);
        if (data === window.DIA_SELECIONADO && li) return editarTarefa(li, 'titulo');
        location.href = `?dia=${data}#editar-${chip.dataset.tarefa}`;
    });
});

const pedido = location.hash.match(/^#editar-(\d+)$/);
if (pedido) {
    history.replaceState(null, '', location.pathname + location.search);
    const li = document.querySelector(`.lista.tarefas li[data-tarefa="${pedido[1]}"]`);
    if (li) {
        li.scrollIntoView({ block: 'center' });
        editarTarefa(li, 'titulo');
    }
}
