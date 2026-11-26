// Cronômetro de foco. O servidor é a fonte da verdade: o início fica gravado lá,
// e a conclusão (com XP e moeda) só é aceita depois do horário de fim.

(function () {
    const formNovo = document.querySelector('[data-form-foco]');
    if (formNovo) prepararFormulario(formNovo);

    const painel = document.querySelector('[data-foco]');
    if (painel) iniciarCronometro(painel);

    function prepararFormulario(form) {
        const livre = form.querySelector('.minutos-livre');
        const campoLivre = livre.querySelector('input');
        form.addEventListener('change', () => {
            const outro = form.querySelector('input[name="minutos"]:checked')?.value === 'outro';
            livre.hidden = !outro;
            campoLivre.required = outro;
            if (outro) campoLivre.focus();
        });
        form.addEventListener('submit', () => {
            // Pede permissão de notificação agora, enquanto há um clique do usuário.
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        });
    }

    function iniciarCronometro(painel) {
        const sessaoId = painel.dataset.sessao;
        const fim = Number(painel.dataset.fim);
        // Corrige a diferença entre o relógio do navegador e o do servidor.
        const desvio = Date.now() - Number(painel.dataset.agora);
        const visor = painel.querySelector('[data-cronometro]');
        const tituloOriginal = document.title;
        let concluindo = false;

        painel.querySelector('[data-desistir]').addEventListener('submit', ev => {
            if (!confirm('Desistir agora? Seu neurônio não vai receber esse tempo e a sessão não rende moeda.')) {
                ev.preventDefault();
            }
        });

        const relogio = setInterval(atualizar, 250);
        atualizar();

        function atualizar() {
            const restante = Math.max(0, fim - (Date.now() - desvio));
            const texto = formatar(restante);
            visor.textContent = texto;
            if (restante === 0 && !concluindo) {
                concluindo = true;
                clearInterval(relogio);
                concluir(0);
            }
        }

        async function concluir(tentativa) {
            visor.textContent = '00:00';
            const dados = new FormData();
            dados.append('csrf', window.CSRF);
            dados.append('sessao_id', sessaoId);

            let resposta;
            try {
                const r = await fetch('/foco/concluir', { method: 'POST', body: dados });
                resposta = await r.json();
            } catch (e) {
                // Sem rede: tenta de novo. Se a aba for fechada, o servidor conclui no próximo acesso.
                return setTimeout(() => concluir(tentativa + 1), Math.min(10000, 2000 * (tentativa + 1)));
            }

            if (!resposta.ok && !resposta.repetida && tentativa < 5) {
                // Relógio do navegador um pouco adiantado: o servidor ainda não considera o fim.
                return setTimeout(() => concluir(tentativa + 1), 2000);
            }
            if (resposta.repetida || !resposta.ok) {
                return location.reload();
            }
            mostrarResultado(resposta);
        }

        function mostrarResultado(r) {
            document.title = 'Sessão concluída · ' + tituloOriginal;
            visor.textContent = 'Concluída!';
            visor.classList.add('concluido');
            painel.querySelector('[data-desistir]').remove();
            painel.querySelector('.dica')?.remove();

            painel.querySelector('[data-neuronio-area]').innerHTML = r.neuronio_html;
            document.querySelectorAll('[data-saldo]').forEach(el => { el.textContent = r.saldo; });

            const resultado = painel.querySelector('[data-resultado]');
            resultado.innerHTML = `
                <p class="ganhos"><strong>+${r.xp_ganho} XP</strong> para o neurônio · <strong>+${r.moedas_ganhas}</strong> <i class="moeda pequena"></i></p>
                <div class="linha centro">
                    <a class="botao" href="/foco">Nova sessão</a>
                    <a class="botao secundario" href="/">Ver painel</a>
                </div>`;
            resultado.hidden = false;
            document.querySelector('.carteira')?.classList.add('pulou');

            tocarSom();
            notificar(`+${r.xp_ganho} XP e +1 moeda. Bom trabalho!`);

            if (r.celebracao) {
                document.body.insertAdjacentHTML('beforeend', r.celebracao);
                abrirCelebracao(document.querySelector('dialog[data-celebracao]:last-of-type'));
            }
        }
    }

    function formatar(ms) {
        const total = Math.ceil(ms / 1000);
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        const mmss = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        return h ? h + ':' + mmss : mmss;
    }

    function tocarSom() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [523.25, 659.25, 783.99].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const ganho = ctx.createGain();
                const inicio = ctx.currentTime + i * 0.16;
                osc.frequency.value = freq;
                ganho.gain.setValueAtTime(0.0001, inicio);
                ganho.gain.exponentialRampToValueAtTime(0.25, inicio + 0.02);
                ganho.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.5);
                osc.connect(ganho).connect(ctx.destination);
                osc.start(inicio);
                osc.stop(inicio + 0.55);
            });
        } catch (e) { /* sem áudio disponível */ }
    }

    function notificar(texto) {
        if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
            new Notification('Sessão de foco concluída', { body: texto });
        }
    }
})();
