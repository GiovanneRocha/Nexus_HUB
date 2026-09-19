// ==========================================================
// VISUALIZADOR / EDITOR DE RELATÓRIO (PDF)
// Componente compartilhado entre novo_atendimento.php (logo após
// registrar uma OS) e historico.php (OS Aprovadas > gerar relatório).
//
// Uso:
//   NexusRelatorio.abrir({
//     osId, nomeOS, logoUrl, salvarUrl, podeSalvar,
//     empresa: { nome, cnpj, contato, email, telefone },
//     dataEmissao,
//     caminhoes: [{ chave, modelo, placa, dataServico, itens:[{tipo,descricao,valor,quantidade}], valorTotal }],
//     valorTotalGeral,
//     relatorioSalvo: { titulo, introducao, condicao_pagamento, validade, comentarios:{chave:texto} } | null,
//     aoFechar: function(){}
//   });
// ==========================================================

window.NexusRelatorio = (function () {

    let configAtual = null;
    let overlayEl = null;

    function escapar(valor) {
        const div = document.createElement('div');
        div.textContent = valor == null ? '' : String(valor);
        return div.innerHTML;
    }

    function formatarMoeda(valor) {
        return 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function garantirOverlay() {
        if (overlayEl) return overlayEl;
        overlayEl = document.createElement('div');
        overlayEl.className = 'rv-overlay';
        overlayEl.id = 'rvOverlay';
        document.body.appendChild(overlayEl);
        return overlayEl;
    }

    function textoIntroducaoPadrao(caminhao) {
        const descricoes = (caminhao.itens || [])
            .filter(item => item.tipo === 'servico' && item.descricao && item.descricao.trim() !== '')
            .map(item => item.descricao.trim());
        if (!descricoes.length) return '';
        // Remove duplicadas mantendo a ordem
        const unicas = [...new Set(descricoes)];
        return unicas.join('. ') + (unicas[unicas.length - 1].endsWith('.') ? '' : '.');
    }

    function renderTabelaItens(itens) {
        if (!itens || !itens.length) {
            return '<p style="color:#6b7280;font-size:.88rem;margin-top:16px;">Nenhum serviço ou peça registrado para este caminhão.</p>';
        }
        const linhas = itens.map(item => {
            const rotulo = item.tipo === 'peca'
                ? `Peça: ${escapar(item.descricao)}${item.quantidade > 1 ? ' (x' + item.quantidade + ')' : ''}`
                : escapar(item.descricao || 'Serviço');
            return `<tr><td>${rotulo}</td><td class="rv-col-valor">${formatarMoeda(item.valor)}</td></tr>`;
        }).join('');
        const total = itens.reduce((soma, item) => soma + Number(item.valor || 0), 0);
        return `
            <table class="rv-tabela">
                <thead><tr><th>Serviço</th><th class="rv-col-valor">Valor do Serviço</th></tr></thead>
                <tbody>${linhas}<tr><td>Total</td><td class="rv-col-valor">${formatarMoeda(total)}</td></tr></tbody>
            </table>`;
    }

    function renderPaginaCapa(cfg) {
        const salvo = cfg.relatorioSalvo || {};
        const titulo = salvo.titulo || (cfg.nomeOS || 'Relatório de Serviço');
        const introducao = salvo.introducao != null ? salvo.introducao : '';
        const condicao = salvo.condicao_pagamento != null ? salvo.condicao_pagamento : '';
        const validade = salvo.validade != null ? salvo.validade : '';

        const linhasCaminhoes = cfg.caminhoes.map(c => `
            <tr>
                <td>${escapar(c.modelo || 'Modelo não informado')}${c.placa ? ' · ' + escapar(c.placa) : ''}</td>
                <td class="rv-col-valor">${formatarMoeda(c.valorTotal)}</td>
            </tr>`).join('');

        return `
        <section class="rv-pagina" data-pagina="capa">
            <img class="rv-marca-dagua" src="${cfg.logoUrl}" alt="">
            <div class="rv-cabecalho-marca"><img src="${cfg.logoUrl}" alt="Logo"><span>Nexus HUB</span></div>
            <div class="rv-conteudo">
                <h1 class="rv-titulo-editavel rv-editavel" contenteditable="true" data-campo="titulo" data-placeholder="Título do relatório">${escapar(titulo)}</h1>

                <p class="rv-linha-dado"><span class="rv-label">Empresa Cliente:</span> ${escapar(cfg.empresa.nome)}</p>
                <p class="rv-linha-dado"><span class="rv-label">Data:</span> ${escapar(cfg.dataEmissao)}</p>
                <p class="rv-linha-dado"><span class="rv-label">Nome do contato da Empresa:</span> ${escapar(cfg.empresa.contato || '-')}</p>
                <p class="rv-linha-dado"><span class="rv-label">E-mail | Telefone:</span> ${escapar(cfg.empresa.email || '-')} ${cfg.empresa.telefone ? '| ' + escapar(cfg.empresa.telefone) : ''}</p>

                <div class="rv-texto-editavel rv-editavel" contenteditable="true" data-campo="introducao" data-placeholder="Escreva aqui a introdução deste relatório para o cliente...">${escapar(introducao)}</div>

                <table class="rv-tabela">
                    <thead><tr><th>Caminhão</th><th class="rv-col-valor">Valor Total do Caminhão</th></tr></thead>
                    <tbody>
                        ${linhasCaminhoes}
                        <tr><td>Total</td><td class="rv-col-valor">${formatarMoeda(cfg.valorTotalGeral)}</td></tr>
                    </tbody>
                </table>

                <div class="rv-rodape-pagamento">
                    <div><span class="rv-label">Condição de Pagamento:</span> <span class="rv-editavel" contenteditable="true" data-campo="condicao_pagamento" data-placeholder="Ex.: à vista, 30 dias...">${escapar(condicao)}</span></div>
                    <div><span class="rv-label">Validade:</span> <span class="rv-editavel" contenteditable="true" data-campo="validade" data-placeholder="Ex.: 30 dias a partir da emissão">${escapar(validade)}</span></div>
                </div>
            </div>
            <div class="rv-rodape-assinatura">
                <strong>Nexus HUB</strong><br>
                ${escapar(cfg.empresa.contato || '')}<br>
                ${escapar(cfg.empresa.email || '')}${cfg.empresa.telefone ? ' / ' + escapar(cfg.empresa.telefone) : ''}
            </div>
        </section>`;
    }

    function renderPaginaCaminhao(cfg, caminhao, indice) {
        const salvo = (cfg.relatorioSalvo && cfg.relatorioSalvo.comentarios) || {};
        const comentarioSalvo = salvo[caminhao.chave] != null ? salvo[caminhao.chave] : '';
        const introSalva = (cfg.relatorioSalvo && cfg.relatorioSalvo.intros && cfg.relatorioSalvo.intros[caminhao.chave] != null)
            ? cfg.relatorioSalvo.intros[caminhao.chave]
            : textoIntroducaoPadrao(caminhao);

        return `
        <section class="rv-pagina" data-pagina="caminhao" data-chave="${escapar(caminhao.chave)}">
            <img class="rv-marca-dagua" src="${cfg.logoUrl}" alt="">
            <div class="rv-cabecalho-marca"><img src="${cfg.logoUrl}" alt="Logo"><span>Nexus HUB</span></div>
            <div class="rv-conteudo">
                <h2 class="rv-titulo-editavel" style="font-size:1.35rem;">${escapar(caminhao.modelo || 'Modelo não informado')}${caminhao.placa ? ' · ' + escapar(caminhao.placa) : ''}</h2>
                <p class="rv-subtitulo-editavel" style="font-size:.95rem;">Data do Serviço: ${escapar(caminhao.dataServico || '-')}</p>

                <div class="rv-texto-editavel rv-editavel" contenteditable="true" data-campo="intro_veiculo" data-chave="${escapar(caminhao.chave)}" data-placeholder="Descreva os serviços realizados neste caminhão...">${escapar(introSalva)}</div>

                ${renderTabelaItens(caminhao.itens)}

                <p class="rv-comentarios-label">Comentários que achar necessário</p>
                <div class="rv-texto-editavel rv-editavel" contenteditable="true" data-campo="comentario" data-chave="${escapar(caminhao.chave)}" data-placeholder="Observações adicionais sobre este caminhão (opcional)...">${escapar(comentarioSalvo)}</div>
            </div>
            <div class="rv-rodape-assinatura">
                <strong>Nexus HUB</strong><br>
                ${escapar(cfg.empresa.contato || '')}<br>
                ${escapar(cfg.empresa.email || '')}${cfg.empresa.telefone ? ' / ' + escapar(cfg.empresa.telefone) : ''}
            </div>
        </section>
        <p style="text-align:center;color:#94a3b8;font-size:.72rem;margin:-14px 0 0;">Página ${indice + 2} de ${cfg.caminhoes.length + 1}</p>`;
    }

    function render(cfg) {
        const overlay = garantirOverlay();
        const paginasHtml = [renderPaginaCapa(cfg)]
            .concat(cfg.caminhoes.map((c, i) => renderPaginaCaminhao(cfg, c, i)))
            .join('');

        overlay.innerHTML = `
            <div class="rv-topo">
                <div>
                    <h2><i class="bi bi-file-earmark-text"></i> Pré-visualização do Relatório</h2>
                    <p>Clique em qualquer texto para editar. As alterações só valem para o PDF gerado${cfg.podeSalvar ? ' (ou "Salvar" para manter para a próxima vez)' : ''}.</p>
                </div>
                <div class="rv-acoes">
                    ${cfg.podeSalvar ? '<button type="button" class="rv-btn rv-btn-salvar" id="rvBtnSalvar"><i class="bi bi-save"></i> Salvar alterações</button>' : ''}
                    <button type="button" class="rv-btn rv-btn-baixar" id="rvBtnBaixar"><i class="bi bi-download"></i> Baixar PDF</button>
                    <button type="button" class="rv-btn rv-btn-fechar" id="rvBtnFechar"><i class="bi bi-x-circle"></i> Fechar</button>
                </div>
            </div>
            <div class="rv-paginas" id="rvPaginas">${paginasHtml}</div>
        `;

        document.getElementById('rvBtnFechar').addEventListener('click', fechar);
        document.getElementById('rvBtnBaixar').addEventListener('click', baixarPDF);
        const btnSalvar = document.getElementById('rvBtnSalvar');
        if (btnSalvar) btnSalvar.addEventListener('click', salvar);

        overlay.classList.add('aberto');
        document.body.style.overflow = 'hidden';
    }

    function coletarDados() {
        const paginas = document.querySelectorAll('#rvPaginas .rv-pagina');
        const dados = { titulo: '', introducao: '', condicao_pagamento: '', validade: '', intros: {}, comentarios: {} };
        paginas.forEach(pagina => {
            pagina.querySelectorAll('[data-campo]').forEach(campo => {
                const nome = campo.dataset.campo;
                const chave = campo.dataset.chave;
                const texto = campo.innerText.trim();
                if (nome === 'intro_veiculo' && chave) dados.intros[chave] = texto;
                else if (nome === 'comentario' && chave) dados.comentarios[chave] = texto;
                else dados[nome] = texto;
            });
        });
        return dados;
    }

    async function salvar() {
        if (!configAtual || !configAtual.salvarUrl) return;
        const botao = document.getElementById('rvBtnSalvar');
        const rotuloOriginal = botao.innerHTML;
        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Salvando...';
        try {
            const dados = coletarDados();
            const formData = new FormData();
            formData.append('action', 'salvar');
            formData.append('os_id', configAtual.osId);
            formData.append('relatorio_dados', JSON.stringify(dados));
            const resposta = await fetch(configAtual.salvarUrl, { method: 'POST', body: formData });
            const json = await resposta.json();
            if (!json.sucesso) throw new Error(json.mensagem || 'Falha ao salvar.');
            botao.innerHTML = '<i class="bi bi-check-circle"></i> Salvo!';
            setTimeout(() => { botao.innerHTML = rotuloOriginal; botao.disabled = false; }, 1800);
        } catch (erro) {
            alert('Não foi possível salvar as alterações: ' + erro.message);
            botao.innerHTML = rotuloOriginal;
            botao.disabled = false;
        }
    }

    async function baixarPDF() {
        const botao = document.getElementById('rvBtnBaixar');
        const rotuloOriginal = botao.innerHTML;
        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Gerando PDF...';

        // Remove o foco de qualquer campo editável para não capturar o cursor/seleção.
        if (document.activeElement) document.activeElement.blur();

        try {
            const paginas = document.querySelectorAll('#rvPaginas .rv-pagina');
            const pdf = new jspdf.jsPDF('portrait', 'pt', 'a4');
            const larguraPdf = pdf.internal.pageSize.getWidth();
            const alturaPdf = pdf.internal.pageSize.getHeight();

            for (let i = 0; i < paginas.length; i++) {
                const canvas = await html2canvas(paginas[i], { scale: 2, backgroundColor: '#ffffff', useCORS: true });
                const proporcao = larguraPdf / canvas.width;
                const alturaImagem = canvas.height * proporcao;
                if (i > 0) pdf.addPage();
                // Se o conteúdo for mais alto que a página, ajusta a escala para caber (evita corte).
                const alturaFinal = Math.min(alturaImagem, alturaPdf);
                const larguraFinal = alturaImagem > alturaPdf ? (alturaPdf / canvas.height) * canvas.width : larguraPdf;
                pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, larguraFinal, alturaFinal);
            }

            const nomeArquivo = `relatorio-os-${configAtual.osId}.pdf`;
            pdf.save(nomeArquivo);
        } catch (erro) {
            console.error(erro);
            alert('Não foi possível gerar o PDF. Tente novamente.');
        } finally {
            botao.disabled = false;
            botao.innerHTML = rotuloOriginal;
        }
    }

    function fechar() {
        if (overlayEl) overlayEl.classList.remove('aberto');
        document.body.style.overflow = '';
        if (configAtual && typeof configAtual.aoFechar === 'function') configAtual.aoFechar();
    }

    function abrir(config) {
        configAtual = Object.assign({ podeSalvar: true, logoUrl: '', nomeOS: '' }, config);
        render(configAtual);
    }

    return { abrir, fechar };
})();
