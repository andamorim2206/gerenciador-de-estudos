// Comportamentos comuns a todas as páginas.

// Celebração de novo estágio do neurônio (renderizada pelo servidor uma única vez).
function abrirCelebracao(dialogo) {
    if (!dialogo) return;
    if (typeof dialogo.showModal === 'function') dialogo.showModal();
    else dialogo.setAttribute('open', '');
}

document.querySelectorAll('dialog[data-celebracao]').forEach(abrirCelebracao);
