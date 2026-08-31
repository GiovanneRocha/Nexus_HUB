<?php
require_once __DIR__ . '/../../php/db.php';

$pdo = nexusDb();

// Criar tabela de OS se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS ordens_servico (
    id INT NOT NULL AUTO_INCREMENT,
    empresa_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'pendente_revisao',
    valor_total DECIMAL(12,2) DEFAULT 0.00,
    descricao_geral TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (empresa_id) REFERENCES empresas_cadastradas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS itens_os (
    id INT NOT NULL AUTO_INCREMENT,
    os_id INT NOT NULL,
    tipo ENUM('servico', 'peca', 'veiculo') NOT NULL,
    placa VARCHAR(20) DEFAULT NULL,
    modelo VARCHAR(100) DEFAULT NULL,
    ano INT DEFAULT NULL,
    cor VARCHAR(50) DEFAULT NULL,
    descricao TEXT DEFAULT NULL,
    valor DECIMAL(12,2) DEFAULT 0.00,
    quantidade INT DEFAULT 1,
    PRIMARY KEY (id),
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Processar submissão do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'salvar_os') {
    try {
        $empresaId = (int) ($_POST['empresa_id'] ?? 0);
        $valorTotal = (float) ($_POST['valor_total'] ?? 0);
        $descricaoGeral = trim((string) ($_POST['descricao_geral'] ?? ''));

        if ($empresaId <= 0 || $valorTotal < 0) {
            throw new Exception('Dados inválidos');
        }

        // Iniciar transação
        $pdo->beginTransaction();

        // Salvar OS principal
        $stmtOS = $pdo->prepare('INSERT INTO ordens_servico (empresa_id, valor_total, descricao_geral, status) VALUES (:empresa_id, :valor_total, :descricao_geral, "pendente_revisao")');
        $stmtOS->execute([
            ':empresa_id' => $empresaId,
            ':valor_total' => $valorTotal,
            ':descricao_geral' => $descricaoGeral,
        ]);

        $osId = $pdo->lastInsertId();

        // Processar veículos (enviados como JSON)
        $frotaJSON = $_POST['frota_json'] ?? '[]';
        $frota = json_decode($frotaJSON, true) ?? [];

        $stmtItem = $pdo->prepare('INSERT INTO itens_os (os_id, tipo, placa, modelo, ano, cor, descricao, valor, quantidade) VALUES (:os_id, :tipo, :placa, :modelo, :ano, :cor, :descricao, :valor, :quantidade)');

        foreach ($frota as $veiculo) {
            // Salvar dados do veículo
            $stmtItem->execute([
                ':os_id' => $osId,
                ':tipo' => 'veiculo',
                ':placa' => $veiculo['dadosVeiculo']['placa'] ?? null,
                ':modelo' => $veiculo['dadosVeiculo']['modelo'] ?? null,
                ':ano' => $veiculo['dadosVeiculo']['ano'] ?? null,
                ':cor' => $veiculo['dadosVeiculo']['cor'] ?? null,
                ':descricao' => null,
                ':valor' => 0,
                ':quantidade' => 1,
            ]);

            // Salvar serviços do veículo
            foreach ($veiculo['servicos'] ?? [] as $servico) {
                $stmtItem->execute([
                    ':os_id' => $osId,
                    ':tipo' => 'servico',
                    ':placa' => $veiculo['dadosVeiculo']['placa'] ?? null,
                    ':modelo' => null,
                    ':ano' => null,
                    ':cor' => null,
                    ':descricao' => $servico['descricao'] ?? null,
                    ':valor' => (float) ($servico['valor'] ?? 0),
                    ':quantidade' => 1,
                ]);
            }

            // Salvar peças do veículo
            foreach ($veiculo['pecas'] ?? [] as $peca) {
                $stmtItem->execute([
                    ':os_id' => $osId,
                    ':tipo' => 'peca',
                    ':placa' => $veiculo['dadosVeiculo']['placa'] ?? null,
                    ':modelo' => null,
                    ':ano' => null,
                    ':cor' => null,
                    ':descricao' => $peca['nome'] ?? null,
                    ':valor' => (float) ($peca['valorTotal'] ?? 0),
                    ':quantidade' => (int) ($peca['quantidade'] ?? 1),
                ]);
            }
        }

        $pdo->commit();

        // Retornar sucesso
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'OS criada com sucesso!',
            'os_id' => $osId,
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao salvar OS: ' . $e->getMessage(),
        ]);
        exit;
    }
}

// Buscar empresas para o select
$empresas = $pdo->query('SELECT id, nome, cnpj FROM empresas_cadastradas ORDER BY nome ASC')->fetchAll();

// Buscar serviços para popular JavaScript
$servicos = $pdo->query('SELECT id, nome_servico as nome, preco as valor, descricao FROM cod_servicos ORDER BY nome_servico ASC')->fetchAll();

// Buscar peças para popular JavaScript
$pecas = $pdo->query('SELECT id, nome, valor, unidade, estoque FROM pecas WHERE status = "Ativa" ORDER BY nome ASC')->fetchAll();

// Buscar veículos cadastrados para referência
$veiculos = $pdo->query('SELECT id, nome_caminhao as empresa, placa, modelo FROM caminhoes ORDER BY nome_caminhao ASC')->fetchAll();

// Converter para JSON para JavaScript
$empresasJSON = json_encode($empresas, JSON_UNESCAPED_UNICODE);
$servicosJSON = json_encode($servicos, JSON_UNESCAPED_UNICODE);
$pecasJSON = json_encode($pecas, JSON_UNESCAPED_UNICODE);
$veiculosJSON = json_encode($veiculos, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova OS - Nexus HUB</title>
    <link rel="icon" type="image/png" href="../../assets/images/icon-sistem.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/pages.css">
    <link rel="stylesheet" href="../../assets/css/admin-forms.css">
    <script src="../../assets/js/common.js"></script>
    <script src="../../assets/js/pages.js"></script>
</head>
<body class="corpo-dashboard">
    <div class="layout-erp">
        <main class="conteudo-principal">
            <!-- Success Message -->
            <div id="successMessage" style="display: none; position: fixed; top: 20px; right: 20px; background: #d4edda; color: #155724; padding: 15px 20px; border: 1px solid #c3e6cb; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; font-weight: 500; animation: slideIn 0.3s ease-out; max-width: 300px;">
                <i class="bi bi-check-circle-fill"></i> OS registrada com sucesso! <button onclick="window.location.href='revisao.html'" style="background: none; border: none; color: #155724; text-decoration: underline; cursor: pointer; margin-left: 10px;">Ver em Revisão</button>
            </div>

            <section class="titulo-pagina-stack">
                <h1><i class="bi bi-plus-circle"></i> Registrar Nova OS</h1>
                <p>Preencha os dados do cliente e serviços. O sistema valida duplicatas automaticamente.</p>
            </section>

            <form id="formNovoAtendimento">
                <div class="container-formulario-novo-atendimento">
                    <div class="secao-formulario-novo">
                        <h3><i class="bi bi-building"></i> Informações da Empresa</h3>
                        <div class="linha-campos">
                            <div class="bloco-campo-novo">
                                <label><i class="bi bi-shop"></i> Selecionar Empresa</label>
                                <select id="selecao-empresa" required onchange="preencherDadosEmpresa()">
                                    <option value="">Escolha uma empresa cadastrada...</option>
                                    <?php foreach ($empresas as $emp): ?>
                                        <option value="<?= (int)$emp['id'] ?>" data-cnpj="<?= htmlspecialchars($emp['cnpj'] ?? '') ?>">
                                            <?= htmlspecialchars($emp['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="bloco-campo-novo">
                                <label><i class="bi bi-card-text"></i> CNPJ</label>
                                <input type="text" id="display-cnpj" readonly>
                            </div>
                        </div>
                    </div>

                    <div id="container-veiculos"></div>

                    <div class="secao-formulario-novo">
                        <button type="button" onclick="adicionarCamposVeiculo()" style="width: 100%; padding: 16px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%); border: 2px dashed var(--verde-esmeralda); color: var(--verde-esmeralda); border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                            <i class="bi bi-plus-lg"></i> Adicionar Veículo
                        </button>
                    </div>

                    <div class="secao-formulario-novo">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px; border-bottom: 1px solid var(--bordar);">
                            <h3 style="margin: 0;"><i class="bi bi-calculator"></i> Total da OS</h3>
                            <h2 id="total-geral-display" style="color: var(--verde-esmeralda); font-size: 2rem; margin: 0;">R$ 0,00</h2>
                        </div>
                        
                        <div class="area-botoes" style="margin-top: 24px;">
                            <button type="submit" class="botao-acao" style="flex: 1; background: linear-gradient(135deg, var(--verde-esmeralda) 0%, var(--verde-escuro) 100%); display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="bi bi-check-circle"></i> Registrar Atendimento
                            </button>
                            <button type="button" onclick="limparFormulario()" class="botao-cancelar" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="bi bi-trash"></i> Limpar
                            </button>
                            <button type="button" onclick="window.location.href='menu.html'" class="botao-cancelar" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="bi bi-arrow-left"></i> Voltar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Campo oculto para enviar dados JSON -->
                <input type="hidden" name="action" value="salvar_os">
                <input type="hidden" name="empresa_id" id="empresa-id-hidden" value="">
                <input type="hidden" name="valor_total" id="valor-total-hidden" value="0">
                <input type="hidden" name="descricao_geral" id="descricao-geral-hidden" value="">
                <input type="hidden" name="frota_json" id="frota-json-hidden" value="[]">
            </form>
        </main>
    </div>

    <script>
        // Dados do banco de dados (passados via PHP)
        const EMPRESAS_BANCO = <?= $empresasJSON ?>;
        const SERVICOS_BANCO = <?= $servicosJSON ?>;
        const PECAS_BANCO = <?= $pecasJSON ?>;
        const VEICULOS_BANCO = <?= $veiculosJSON ?>;

        // Manter compatibilidade com lógica antiga (CONFIG_SERVICOS vazio, dados do banco acima)
        const CONFIG_SERVICOS = {};

        let contagemVeiculos = 0;

        function adicionarCamposVeiculo() {
            contagemVeiculos++;
            const container = document.getElementById('container-veiculos');
            const div = document.createElement('div');
            div.className = 'bloco-veiculo';
            div.style = 'margin-bottom: 24px;';
            
            div.innerHTML = `
                <div class="secao-formulario-novo" style="position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <h3 style="margin: 0;"><i class="bi bi-car-front"></i> Veículo ${contagemVeiculos}</h3>
                        <button type="button" class="botao-secundario" onclick="mostrarVeiculosCadastrados(this)" style="padding: 10px 16px; font-size: 0.95rem; white-space: nowrap;">Buscar veículo cadastrado</button>
                    </div>
                    <div class="painel-veiculos-existentes" style="display: none; margin-top: 16px;"></div>
                    <div class="linha-campos">
                        <div class="bloco-campo-novo">
                            <label><i class="bi bi-tag"></i> Placa</label>
                            <input type="text" class="v-placa" placeholder="ABC-1234" required>
                        </div>
                        <div class="bloco-campo-novo">
                            <label><i class="bi bi-type"></i> Modelo</label>
                            <input type="text" class="v-modelo" placeholder="Modelo do veículo" required>
                        </div>
                        <div class="bloco-campo-novo">
                            <label><i class="bi bi-calendar-event"></i> Ano</label>
                            <input type="number" class="v-ano" placeholder="2024" min="1900" max="2025" step="1" required oninput="validarAnoVeiculo(this)">
                            <small class="aviso-ano" style="display:none; color:#ef4444; font-size:0.75rem; margin-top:4px;"></small>
                        </div>
                        <div class="bloco-campo-novo">
                            <label><i class="bi bi-palette"></i> Cor</label>
                            <input type="text" class="v-cor" placeholder="Branco" required>
                        </div>
                    </div>
                    
                    <div class="container-servicos-veiculo" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--bordar);"></div>
                    
                    <div style="display: grid; gap: 12px; margin-top: 16px;">
                        <button type="button" onclick="adicionarServicoAoVeiculo(this)" style="width: 100%; padding: 12px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px dashed var(--verde-esmeralda); color: var(--verde-esmeralda); border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                            <i class="bi bi-plus"></i> Adicionar Serviço
                        </button>
                        <button type="button" onclick="adicionarPecaAoVeiculo(this)" style="width: 100%; padding: 12px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px dashed var(--verde-esmeralda); color: var(--verde-esmeralda); border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                            <i class="bi bi-box-seam"></i> Adicionar Peça
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(div);
            adicionarServicoAoVeiculo(div.querySelector('button[onclick*="adicionarServicoAoVeiculo"]'));
        }

        function mostrarVeiculosCadastrados(botao) {
            const empresaSelecionada = document.getElementById('selecao-empresa').value;
            if (!empresaSelecionada) {
                alert('Selecione primeiro uma empresa para buscar veículos cadastrados.');
                return;
            }

            // Filtrar veículos da empresa selecionada
            const veiculosDaEmpresa = VEICULOS_BANCO.filter(v => v.empresa === EMPRESAS_BANCO.find(e => e.id == empresaSelecionada)?.nome);
            
            if (veiculosDaEmpresa.length === 0) {
                alert('Nenhum veículo cadastrado encontrado para esta empresa.');
                return;
            }

            const blocoVeiculo = botao.closest('.bloco-veiculo');
            const painel = blocoVeiculo.querySelector('.painel-veiculos-existentes');
            if (!painel) return;

            if (painel.style.display === 'block') {
                painel.style.display = 'none';
                painel.innerHTML = '';
                return;
            }

            painel.style.display = 'block';
            painel.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <strong style="font-size: 0.95rem;">Veículos já cadastrados</strong>
                    <button type="button" onclick="this.closest('.painel-veiculos-existentes').style.display='none'" style="background:none; border:none; color: var(--texto-secundario); cursor:pointer; font-size: 1rem;"><i class="bi bi-x-lg"></i></button>
                </div>
                <select class="selecao-veiculo-existente" style="width:100%; padding: 10px; border: 1px solid var(--bordar); border-radius: 8px; margin-bottom: 12px;">
                    <option value="">Selecione um veículo...</option>
                    ${veiculosDaEmpresa.map(v => `<option value="${encodeURIComponent(JSON.stringify(v))}">${v.placa} — ${v.modelo}</option>`).join('')}
                </select>
                <button type="button" onclick="preencherVeiculoExistente(this)" style="width: 100%; padding: 12px; background: var(--azul-escuro); color: #fff; border:none; border-radius: 8px; cursor:pointer;">Preencher dados</button>
            `;
        }

        function preencherVeiculoExistente(botao) {
            const blocoVeiculo = botao.closest('.bloco-veiculo');
            const select = blocoVeiculo.querySelector('.selecao-veiculo-existente');
            const opcaoSelecionada = select ? select.selectedOptions[0] : null;
            if (!opcaoSelecionada || !opcaoSelecionada.value) {
                alert('Selecione um veículo antes de preencher os dados.');
                return;
            }

            let veiculo = null;
            try {
                veiculo = JSON.parse(decodeURIComponent(opcaoSelecionada.value));
            } catch (err) {
                console.error('Erro ao ler veículo selecionado:', err);
            }

            if (!veiculo) {
                alert('Veículo não encontrado.');
                return;
            }

            blocoVeiculo.querySelector('.v-placa').value = veiculo.placa || '';
            blocoVeiculo.querySelector('.v-modelo').value = veiculo.modelo || '';
            blocoVeiculo.querySelector('.v-ano').value = veiculo.ano || '';
            blocoVeiculo.querySelector('.v-cor').value = veiculo.cor || '';
        }

        function adicionarServicoAoVeiculo(botao) {
            const blocoVeiculo = botao.closest('.bloco-veiculo');
            const container = blocoVeiculo.querySelector('.container-servicos-veiculo');
            const servicosJaUsados = Array.from(container.querySelectorAll('.s-tipo')).map(s => s.value);
            
            if (servicosJaUsados.length >= SERVICOS_BANCO.length) {
                alert("Todos os serviços disponíveis já foram adicionados para este veículo.");
                return;
            }

            const div = document.createElement('div');
            div.className = 'item-servico';
            div.style = 'position: relative; margin-bottom: 16px; padding: 16px; background: var(--surface-strong); border: 1px solid var(--bordar); border-radius: 8px; border-left: 4px solid var(--verde-esmeralda);';
            
            let optionsHTML = '<option value="">Selecione um serviço...</option>';
            
            // Adicionar serviços do banco
            SERVICOS_BANCO.forEach((servico, index) => {
                if (!servicosJaUsados.includes(`banco_${servico.id}`)) {
                    optionsHTML += `<option value="banco_${servico.id}" data-valor="${parseFloat(servico.valor || 0).toFixed(2)}" data-nome="${servico.nome}" data-descricao="${servico.descricao || ''}">${servico.nome}</option>`;
                }
            });

            const hoje = new Date().toISOString().split('T')[0];
            div.innerHTML = `
                ${container.children.length > 0 ? '<button type="button" onclick="this.parentElement.remove(); calcularTotalGeral();" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: var(--erro); cursor: pointer; font-weight: 600;"><i class="bi bi-x-circle"></i></button>' : ''}
                <div class="linha-campos">
                    <div class="bloco-campo-novo">
                        <label><i class="bi bi-tools"></i> Tipo de Serviço</label>
                        <select class="s-tipo" required onchange="aplicarServicoSelecionado(this)">
                            ${optionsHTML}
                        </select>
                    </div>
                    <div class="bloco-campo-novo">
                        <label><i class="bi bi-calendar2-date"></i> Data</label>
                        <input type="date" class="s-data" value="${hoje}" required>
                    </div>
                </div>
                <div class="bloco-campo-novo">
                    <label><i class="bi bi-chat-left-text"></i> Descrição</label>
                    <textarea class="s-desc" placeholder="Descreva o serviço realizado..." rows="2" style="width: 100%;"></textarea>
                </div>
                <div class="bloco-campo-novo">
                    <label><i class="bi bi-currency-dollar"></i> Valor (R$)</label>
                    <input type="number" class="s-valor" placeholder="0,00" step="0.01" required oninput="calcularTotalGeral()">
                </div>
            `;
            container.appendChild(div);
        }

        function adicionarPecaAoVeiculo(botao) {
            const blocoVeiculo = botao.closest('.bloco-veiculo');
            
            let container = blocoVeiculo.querySelector('.container-pecas-veiculo');
            if (!container) {
                container = document.createElement('div');
                container.className = 'container-pecas-veiculo';
                container.style = 'margin-top: 16px; padding-top: 16px; border-top: 1px dashed var(--bordar);';
                const botoesAcao = blocoVeiculo.querySelector('div[style*="display: grid"]');
                if (botoesAcao) {
                    botoesAcao.parentElement.insertBefore(container, botoesAcao);
                } else {
                    blocoVeiculo.querySelector('.secao-formulario-novo').appendChild(container);
                }
            }

            if (PECAS_BANCO.length === 0) {
                alert('Nenhuma peça cadastrada. Acesse Gestão de Peças para cadastrar.');
                return;
            }

            const pecasEmEstoque = PECAS_BANCO.filter(peca => parseInt(peca.estoque) > 0);

            if (pecasEmEstoque.length === 0) {
                alert('Nenhuma peça disponível em estoque no momento.');
                return;
            }

            const div = document.createElement('div');
            div.className = 'item-peca';
            div.style = 'position: relative; margin-bottom: 16px; padding: 16px; background: var(--surface-strong); border: 1px solid var(--bordar); border-radius: 8px; border-left: 4px solid #3b82f6;';

            const optionsHTML = pecasEmEstoque.map(peca => `
                <option value="peca_${peca.id}" data-estoque="${peca.estoque}" data-valor="${parseFloat(peca.valor || 0).toFixed(2)}" data-nome="${peca.nome}" data-id="${peca.id}">
                    ${peca.nome} — R$ ${parseFloat(peca.valor || 0).toFixed(2)} (Estoque: ${peca.estoque} ${peca.unidade || 'un.'})
                </option>
            `).join('');

            div.innerHTML = `
                <button type="button" onclick="this.parentElement.remove(); calcularTotalGeral();" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: var(--erro, #ef4444); cursor: pointer; font-size: 1.1rem;"><i class="bi bi-x-circle"></i></button>
                <div class="linha-campos">
                    <div class="bloco-campo-novo">
                        <label><i class="bi bi-box-seam"></i> Peça</label>
                        <select class="p-peca" required onchange="aplicarPecaSelecionada(this)">
                            <option value="">Selecione uma peça...</option>
                            ${optionsHTML}
                        </select>
                    </div>
                    <div class="bloco-campo-novo">
                        <label><i class="bi bi-sort-numeric-up"></i> Quantidade</label>
                        <input type="number" class="p-qtde" min="1" value="" placeholder="0" required oninput="validarQuantidadePeca(this); calcularTotalGeral();">
                        <small class="aviso-estoque" style="color: #f59e0b; font-size: 0.78rem; display: none;"></small>
                    </div>
                </div>
                <div class="linha-campos">
                    <div class="bloco-campo-novo">
                        <label><i class="bi bi-currency-dollar"></i> Valor Unitário (R$)</label>
                        <input type="number" class="p-valor-unitario" placeholder="0,00" step="0.01" readonly style="background: var(--surface); opacity: 0.8;">
                    </div>
                    <div class="bloco-campo-novo">
                        <label><i class="bi bi-calculator"></i> Total da Peça</label>
                        <input type="text" class="p-total" value="R$ 0,00" readonly style="background: var(--surface); font-weight: 700; color: var(--verde-esmeralda, #10b981);">
                    </div>
                </div>
                <input type="hidden" class="p-nome">
                <input type="hidden" class="p-peca-id" value="">
            `;

            div.querySelector('.p-qtde').addEventListener('input', atualizarTotalPeca);
            container.appendChild(div);
            calcularTotalGeral();
        }

        function atualizarTotalPeca(event) {
            const item = event.target.closest('.item-peca');
            const qtde = parseFloat(item.querySelector('.p-qtde').value) || 0;
            const valorUnitario = parseFloat(item.querySelector('.p-valor-unitario').value) || 0;
            item.querySelector('.p-total').value = `R$ ${(qtde * valorUnitario).toFixed(2)}`;
            calcularTotalGeral();
        }

        function aplicarServicoSelecionado(selectElement) {
            const valor = selectElement.value;
            const item = selectElement.closest('.item-servico');
            
            if (valor.startsWith('banco_')) {
                const opcao = selectElement.selectedOptions[0];
                const dataValor = opcao.dataset.valor || '0';
                const dataDescricao = opcao.dataset.descricao || '';
                
                item.querySelector('.s-valor').value = parseFloat(dataValor).toFixed(2);
                item.querySelector('.s-desc').value = dataDescricao;
            }
            
            calcularTotalGeral();
        }

        function aplicarPecaSelecionada(selectElement) {
            const opcao = selectElement.selectedOptions[0];
            const item = selectElement.closest('.item-peca');

            if (!opcao || !opcao.value) return;

            const estoqueDisponivel = parseInt(opcao.dataset.estoque) || 0;
            const valorUnitario = parseFloat(opcao.dataset.valor) || 0;
            const nomePeca = opcao.dataset.nome || '';
            const pecaId = opcao.dataset.id || '';

            item.querySelector('.p-valor-unitario').value = valorUnitario.toFixed(2);
            item.querySelector('.p-nome').value = nomePeca;
            item.querySelector('.p-peca-id').value = pecaId;

            const inputQtde = item.querySelector('.p-qtde');
            inputQtde.max = estoqueDisponivel;
            inputQtde.value = "";
            inputQtde.dataset.estoqueDisponivel = estoqueDisponivel;

            const aviso = item.querySelector('.aviso-estoque');
            aviso.textContent = `Disponível em estoque: ${estoqueDisponivel}`;
            aviso.style.display = 'block';
            aviso.style.color = '#10b981';

            atualizarTotalPeca({ target: inputQtde });
            calcularTotalGeral();
        }

        function validarQuantidadePeca(inputEl) {
            const estoqueDisponivel = parseInt(inputEl.dataset.estoqueDisponivel) || 0;
            const qtde = parseInt(inputEl.value) || 0;
            const item = inputEl.closest('.item-peca');
            const aviso = item.querySelector('.aviso-estoque');

            if (estoqueDisponivel === 0) return;

            if (qtde > estoqueDisponivel) {
                inputEl.value = estoqueDisponivel;
                aviso.textContent = `⚠ Máximo disponível: ${estoqueDisponivel}`;
                aviso.style.color = '#f59e0b';
            } else if (inputEl.value !== '' && qtde < 1) {
                inputEl.value = '';
            } else {
                aviso.textContent = `Disponível em estoque: ${estoqueDisponivel}`;
                aviso.style.color = '#10b981';
            }
            aviso.style.display = 'block';
        }

        function validarAnoVeiculo(input) {
            input.value = input.value.replace(/[^0-9]/g, '');
            const ano = parseInt(input.value);
            const aviso = input.parentElement.querySelector('.aviso-ano');
            if (!aviso) return;

            if (!input.value) {
                aviso.style.display = 'none';
                input.style.border = '';
                return;
            }

            if (isNaN(ano) || ano < 1900 || ano > 2025) {
                aviso.textContent = 'Ano inválido. Informe entre 1900 e 2025.';
                aviso.style.display = 'block';
                input.style.border = '2px solid #ef4444';
            } else {
                aviso.style.display = 'none';
                input.style.border = '2px solid #10b981';
            }
        }

        function calcularTotalGeral() {
            let total = 0;
            document.querySelectorAll('.s-valor').forEach(c => total += parseFloat(c.value) || 0);
            document.querySelectorAll('.item-peca').forEach(item => {
                const qtde = parseFloat(item.querySelector('.p-qtde').value) || 0;
                const valorUnitario = parseFloat(item.querySelector('.p-valor-unitario').value) || 0;
                total += qtde * valorUnitario;
            });
            document.getElementById('total-geral-display').innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
            document.getElementById('valor-total-hidden').value = total.toFixed(2);
        }

        document.getElementById('formNovoAtendimento').addEventListener('submit', function(e) {
            e.preventDefault();

            let errosEncontrados = [];

            if (!document.getElementById('selecao-empresa').value) {
                errosEncontrados.push('Selecione uma empresa.');
            }

            const blocosVeiculosValidacao = document.querySelectorAll('.bloco-veiculo');
            if (blocosVeiculosValidacao.length === 0) {
                errosEncontrados.push('Adicione ao menos um veículo.');
            }

            blocosVeiculosValidacao.forEach((bloco, idx) => {
                const numVeiculo = idx + 1;
                const placa  = bloco.querySelector('.v-placa').value.trim();
                const modelo = bloco.querySelector('.v-modelo').value.trim();
                const corEl  = bloco.querySelector('.v-cor').value.trim();
                const anoEl  = bloco.querySelector('.v-ano');
                const ano    = parseInt(anoEl.value);

                if (!placa)  errosEncontrados.push(`Veículo ${numVeiculo}: informe a placa.`);
                if (!modelo) errosEncontrados.push(`Veículo ${numVeiculo}: informe o modelo.`);
                if (!corEl)  errosEncontrados.push(`Veículo ${numVeiculo}: informe a cor.`);

                if (!anoEl.value.trim()) {
                    errosEncontrados.push(`Veículo ${numVeiculo}: informe o ano.`);
                } else if (isNaN(ano) || ano < 1900 || ano > 2025) {
                    errosEncontrados.push(`Veículo ${numVeiculo}: ano inválido (deve ser entre 1900 e 2025).`);
                }

                bloco.querySelectorAll('.item-servico').forEach((srv, si) => {
                    if (!srv.querySelector('.s-tipo').value)
                        errosEncontrados.push(`Veículo ${numVeiculo}, Serviço ${si+1}: selecione o tipo de serviço.`);
                    if (!srv.querySelector('.s-data').value)
                        errosEncontrados.push(`Veículo ${numVeiculo}, Serviço ${si+1}: informe a data.`);
                    if (!srv.querySelector('.s-valor').value || parseFloat(srv.querySelector('.s-valor').value) <= 0)
                        errosEncontrados.push(`Veículo ${numVeiculo}, Serviço ${si+1}: informe o valor do serviço.`);
                });

                bloco.querySelectorAll('.item-peca').forEach((peca, pi) => {
                    if (!peca.querySelector('.p-peca').value)
                        errosEncontrados.push(`Veículo ${numVeiculo}, Peça ${pi+1}: selecione a peça.`);
                    const qtde = parseInt(peca.querySelector('.p-qtde').value);
                    if (!peca.querySelector('.p-qtde').value || isNaN(qtde) || qtde < 1)
                        errosEncontrados.push(`Veículo ${numVeiculo}, Peça ${pi+1}: informe a quantidade (mínimo 1).`);
                });
            });

            if (errosEncontrados.length > 0) {
                alert('⚠ Corrija os seguintes erros antes de salvar:\n\n• ' + errosEncontrados.join('\n• '));
                return;
            }

            const blocosVeiculos = document.querySelectorAll('.bloco-veiculo');
            const frotaCompleta = [];
            
            blocosVeiculos.forEach(bloco => {
                const servicosDoVeiculo = [];
                const blocosServicos = bloco.querySelectorAll('.item-servico');
                const blocosPecas = bloco.querySelectorAll('.item-peca');
                const pecasDoVeiculo = [];
                
                blocosServicos.forEach(bs => {
                    const valor = parseFloat(bs.querySelector('.s-valor').value) || 0;
                    servicosDoVeiculo.push({
                        tipo: bs.querySelector('.s-tipo').value,
                        data: bs.querySelector('.s-data').value,
                        descricao: bs.querySelector('.s-desc').value,
                        valor: valor
                    });
                });

                blocosPecas.forEach(bp => {
                    const qtde = parseFloat(bp.querySelector('.p-qtde').value) || 0;
                    const valorUnitario = parseFloat(bp.querySelector('.p-valor-unitario').value) || 0;
                    const valorTotal = qtde * valorUnitario;
                    const pecaId = bp.querySelector('.p-peca-id').value;
                    pecasDoVeiculo.push({
                        nome: bp.querySelector('.p-nome').value || bp.querySelector('.p-peca').selectedOptions[0].text,
                        pecaId: pecaId,
                        quantidade: qtde,
                        valorUnitario: valorUnitario,
                        valorTotal: valorTotal
                    });
                });

                frotaCompleta.push({
                    dadosVeiculo: {
                        placa: bloco.querySelector('.v-placa').value,
                        modelo: bloco.querySelector('.v-modelo').value,
                        ano: bloco.querySelector('.v-ano').value,
                        cor: bloco.querySelector('.v-cor').value
                    },
                    servicos: servicosDoVeiculo,
                    pecas: pecasDoVeiculo
                });
            });

            // Preencher campos ocultos
            document.getElementById('empresa-id-hidden').value = document.getElementById('selecao-empresa').value;
            document.getElementById('frota-json-hidden').value = JSON.stringify(frotaCompleta);

            // Enviar via AJAX
            const formData = new FormData(this);
            
            fetch('novo_atendimento.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    const successDiv = document.getElementById('successMessage');
                    successDiv.innerHTML = '<i class="bi bi-check-circle-fill"></i> OS registrada com sucesso! <button onclick="window.location.href=\'revisao.html\'" style="background: none; border: none; color: #155724; text-decoration: underline; cursor: pointer; margin-left: 10px;">Ver em Revisão</button>';
                    successDiv.style.display = 'block';
                    limparFormularioCompleto();
                } else {
                    alert('Erro: ' + data.mensagem);
                }
            })
            .catch(err => {
                console.error('Erro:', err);
                alert('Erro ao salvar OS. Tente novamente.');
            });
        });

        function limparFormulario() {
            if(confirm("Deseja realmente limpar todos os dados?")) {
                location.reload();
            }
        }

        function limparFormularioCompleto() {
            document.getElementById('formNovoAtendimento').reset();
            document.getElementById('container-veiculos').innerHTML = '';
            contagemVeiculos = 0;
            document.getElementById('total-geral-display').innerText = 'R$ 0,00';
            adicionarCamposVeiculo();
        }

        function preencherDadosEmpresa() {
            const select = document.getElementById('selecao-empresa');
            const option = select.options[select.selectedIndex];
            document.getElementById('display-cnpj').value = option ? (option.dataset.cnpj || '') : '';
        }

        window.onload = () => {
            adicionarCamposVeiculo();
        };
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.layout-erp').insertAdjacentHTML('afterbegin', getSidebarHTML('novo_atendimento'));
            document.querySelector('.conteudo-principal').insertAdjacentHTML('afterbegin', getHeaderHTML());
        });
    </script>
</body>
</html>
