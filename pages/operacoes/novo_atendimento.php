<?php
require_once __DIR__ . '/../../php/db.php';
require_once __DIR__ . '/../../php/validacoes.php';
require_once __DIR__ . '/../../php/historico_helper.php';

$pdo = nexusDb();

// IMPORTANTE: garantir a tabela de histórico ANTES de qualquer transação.
// nexusRegistrarHistoricoEmpresa() é chamada de dentro da transação (ex: ao
// cadastrar um veículo novo durante a OS) e ela roda "CREATE TABLE IF NOT
// EXISTS" internamente - um DDL causa COMMIT IMPLÍCITO no MySQL mesmo quando
// a tabela já existe, encerrando a transação sem avisar e quebrando o
// $pdo->commit() final ("There is no active transaction"). Chamando aqui,
// fora de qualquer transação, o flag estático da função evita que o CREATE
// TABLE rode de novo mais tarde.
nexusGarantirTabelaHistoricoEmpresas($pdo);

// Criar tabela de OS se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS ordens_servico (
    id INT NOT NULL AUTO_INCREMENT,
    empresa_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'pendente_revisao',
    valor_total DECIMAL(12,2) DEFAULT 0.00,
    descricao_geral TEXT DEFAULT NULL,
    nome_os VARCHAR(150) DEFAULT NULL,
    motivo_reprovacao TEXT DEFAULT NULL,
    relatorio_dados MEDIUMTEXT DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (empresa_id) REFERENCES empresas_cadastradas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS caminhoes (
    id INT NOT NULL AUTO_INCREMENT,
    empresa_id INT DEFAULT NULL,
    nome_caminhao VARCHAR(100) DEFAULT NULL,
    modelo VARCHAR(100) DEFAULT NULL,
    placa VARCHAR(10) NOT NULL,
    ano INT DEFAULT NULL,
    cor VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (id)
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
    valor_unitario DECIMAL(12,2) DEFAULT 0.00,
    empresa_id INT DEFAULT NULL,
    veiculo_id INT DEFAULT NULL,
    servico_id INT DEFAULT NULL,
    peca_id INT DEFAULT NULL,
    data_execucao DATE DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Atualiza instalações que já possuem as tabelas sem apagar dados existentes.
$colunas = [
    'ordens_servico' => [
        'nome_os' => 'VARCHAR(150) DEFAULT NULL',
        'motivo_reprovacao' => 'TEXT DEFAULT NULL',
        'relatorio_dados' => 'MEDIUMTEXT DEFAULT NULL',
    ],
    'caminhoes' => [
        'empresa_id' => 'INT DEFAULT NULL',
        'ano' => 'INT DEFAULT NULL',
        'cor' => 'VARCHAR(50) DEFAULT NULL',
    ],
    'itens_os' => [
        'valor_unitario' => 'DECIMAL(12,2) DEFAULT 0.00',
        'empresa_id' => 'INT DEFAULT NULL',
        'veiculo_id' => 'INT DEFAULT NULL',
        'servico_id' => 'INT DEFAULT NULL',
        'peca_id' => 'INT DEFAULT NULL',
        'data_execucao' => 'DATE DEFAULT NULL',
    ],
];
$stmtColuna = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND COLUMN_NAME = :coluna');
foreach ($colunas as $tabela => $campos) {
    foreach ($campos as $coluna => $definicao) {
        $stmtColuna->execute(['tabela' => $tabela, 'coluna' => $coluna]);
        if (!(int) $stmtColuna->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
        }
    }
}

$relacoes = [
    ['itens_os', 'fk_itens_os_empresa', 'empresa_id', 'empresas_cadastradas', 'id'],
    ['itens_os', 'fk_itens_os_veiculo', 'veiculo_id', 'caminhoes', 'id'],
    ['itens_os', 'fk_itens_os_servico', 'servico_id', 'cod_servicos', 'id'],
    ['itens_os', 'fk_itens_os_peca', 'peca_id', 'pecas', 'id'],
    ['caminhoes', 'fk_caminhoes_empresa', 'empresa_id', 'empresas_cadastradas', 'id'],
];
$stmtRelacao = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND CONSTRAINT_NAME = :relacao');
foreach ($relacoes as [$tabela, $relacao, $coluna, $tabelaReferenciada, $colunaReferenciada]) {
    $stmtRelacao->execute(['tabela' => $tabela, 'relacao' => $relacao]);
    if (!(int) $stmtRelacao->fetchColumn()) {
        $pdo->exec("ALTER TABLE `{$tabela}` ADD CONSTRAINT `{$relacao}` FOREIGN KEY (`{$coluna}`) REFERENCES `{$tabelaReferenciada}` (`{$colunaReferenciada}`)");
    }
}

// Processar submissão do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['salvar_os', 'atualizar_os'], true)) {
    try {
        $action = $_POST['action'];
        $empresaId = (int) ($_POST['empresa_id'] ?? 0);
        $descricaoGeral = trim((string) ($_POST['descricao_geral'] ?? ''));
        $nomeOS = trim((string) ($_POST['nome_os'] ?? ''));
        $osIdExistente = (int) ($_POST['os_id'] ?? 0);
        $usuarioAtual = nexusUsuarioAtual($_POST);

        if ($empresaId <= 0) {
            throw new Exception('Selecione uma empresa válida.');
        }

        // Iniciar transação
        $pdo->beginTransaction();

        $stmtEmpresa = $pdo->prepare('SELECT id, nome FROM empresas_cadastradas WHERE id = :id LIMIT 1');
        $stmtEmpresa->execute(['id' => $empresaId]);
        $empresa = $stmtEmpresa->fetch();
        if (!$empresa) {
            throw new Exception('A empresa selecionada não foi encontrada.');
        }

        if ($action === 'atualizar_os') {
            if ($osIdExistente <= 0) {
                throw new Exception('OS inválida para edição.');
            }

            $stmtOsAtual = $pdo->prepare('SELECT id, status FROM ordens_servico WHERE id = :id LIMIT 1');
            $stmtOsAtual->execute(['id' => $osIdExistente]);
            $osAtual = $stmtOsAtual->fetch();
            if (!$osAtual) {
                throw new Exception('OS não encontrada.');
            }
            if ($osAtual['status'] !== 'pendente_revisao') {
                throw new Exception('Só é possível editar OS que ainda estejam em revisão.');
            }

            // Devolve ao estoque as peças já reservadas por esta OS antes de reconstruir os itens,
            // para então descontar de novo com as quantidades atualizadas mais abaixo.
            $stmtPecasAntigas = $pdo->prepare('SELECT peca_id, quantidade FROM itens_os WHERE os_id = :os_id AND tipo = "peca" AND peca_id IS NOT NULL');
            $stmtPecasAntigas->execute(['os_id' => $osIdExistente]);
            $stmtRestaurarEstoque = $pdo->prepare('UPDATE pecas SET estoque = estoque + :quantidade WHERE id = :id');
            foreach ($stmtPecasAntigas->fetchAll() as $itemAntigo) {
                $stmtRestaurarEstoque->execute(['quantidade' => $itemAntigo['quantidade'], 'id' => $itemAntigo['peca_id']]);
            }

            // Remove os itens antigos - serão reconstruídos com os dados enviados no formulário.
            $pdo->prepare('DELETE FROM itens_os WHERE os_id = :os_id')->execute(['os_id' => $osIdExistente]);

            $pdo->prepare('UPDATE ordens_servico SET empresa_id = :empresa_id, descricao_geral = :descricao_geral, nome_os = :nome_os WHERE id = :id')
                ->execute([':empresa_id' => $empresaId, ':descricao_geral' => $descricaoGeral, ':nome_os' => ($nomeOS ?: null), ':id' => $osIdExistente]);

            $osId = $osIdExistente;
        } else {
            $stmtOS = $pdo->prepare('INSERT INTO ordens_servico (empresa_id, valor_total, descricao_geral, nome_os, status) VALUES (:empresa_id, 0, :descricao_geral, :nome_os, "pendente_revisao")');
            $stmtOS->execute([
                ':empresa_id' => $empresaId,
                ':descricao_geral' => $descricaoGeral,
                ':nome_os' => ($nomeOS ?: null),
            ]);
            $osId = $pdo->lastInsertId();
        }

        // Processar veículos (enviados como JSON)
        $frota = json_decode($_POST['frota_json'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($frota) || count($frota) === 0) {
            throw new Exception('Adicione ao menos um veículo.');
        }

        $stmtVeiculo = $pdo->prepare('SELECT id, placa, modelo, ano, cor FROM caminhoes WHERE id = :id AND (empresa_id = :empresa_id OR (empresa_id IS NULL AND nome_caminhao = :nome_empresa)) LIMIT 1');
        $stmtNovoVeiculo = $pdo->prepare('INSERT INTO caminhoes (empresa_id, nome_caminhao, modelo, placa, ano, cor) VALUES (:empresa_id, :nome_caminhao, :modelo, :placa, :ano, :cor)');
        $stmtItem = $pdo->prepare('INSERT INTO itens_os (os_id, tipo, empresa_id, veiculo_id, servico_id, peca_id, placa, modelo, ano, cor, data_execucao, descricao, valor, valor_unitario, quantidade) VALUES (:os_id, :tipo, :empresa_id, :veiculo_id, :servico_id, :peca_id, :placa, :modelo, :ano, :cor, :data_execucao, :descricao, :valor, :valor_unitario, :quantidade)');
        $totalCalculado = 0.0;

        // Validação server-side de toda a frota (o JS pode ser burlado).
        $limitesAno = nexusLimitesAnoVeiculo();
        foreach ($frota as $indiceVeiculo => $veiculoValidacao) {
            $numero = $indiceVeiculo + 1;
            $dados = $veiculoValidacao['dadosVeiculo'] ?? [];

            if (!nexusValidarPlaca((string) ($dados['placa'] ?? ''))) {
                throw new Exception("Veículo {$numero}: placa inválida (use ABC-1234 ou ABC1D23).");
            }
            if (!nexusTextoValido((string) ($dados['modelo'] ?? ''), 2)) {
                throw new Exception("Veículo {$numero}: informe o modelo.");
            }
            if (!nexusTextoValido((string) ($dados['cor'] ?? ''), 3)) {
                throw new Exception("Veículo {$numero}: informe a cor.");
            }
            if (!nexusValidarAnoVeiculo($dados['ano'] ?? null)) {
                throw new Exception("Veículo {$numero}: ano inválido (entre {$limitesAno['min']} e {$limitesAno['max']}).");
            }

            foreach ($veiculoValidacao['servicos'] ?? [] as $indiceServico => $servicoValidacao) {
                $numeroServico = $indiceServico + 1;
                if (!nexusValidarData((string) ($servicoValidacao['data'] ?? ''))) {
                    throw new Exception("Veículo {$numero}, serviço {$numeroServico}: informe uma data válida.");
                }
            }

            foreach ($veiculoValidacao['pecas'] ?? [] as $indicePeca => $pecaValidacao) {
                $numeroPeca = $indicePeca + 1;
                $qtde = $pecaValidacao['quantidade'] ?? null;
                if (!nexusValidarInteiroNaoNegativo($qtde) || (int) $qtde < 1) {
                    throw new Exception("Veículo {$numero}, peça {$numeroPeca}: quantidade inválida (mínimo 1).");
                }
            }
        }

        foreach ($frota as $veiculo) {
            $dadosVeiculo = $veiculo['dadosVeiculo'] ?? [];
            $veiculoId = (int) ($dadosVeiculo['id'] ?? 0);

            // Valores enviados pelo formulário (já validados acima).
            $placaEnviada = nexusNormalizarPlaca((string) ($dadosVeiculo['placa'] ?? ''));
            $modeloEnviado = trim((string) ($dadosVeiculo['modelo'] ?? ''));
            $anoEnviado = (int) ($dadosVeiculo['ano'] ?? 0) ?: null;
            $corEnviada = trim((string) ($dadosVeiculo['cor'] ?? ''));

            $stmtVeiculo->execute(['id' => $veiculoId, 'empresa_id' => $empresaId, 'nome_empresa' => $empresa['nome']]);
            $veiculoBanco = $veiculoId > 0 ? $stmtVeiculo->fetch() : false;
            if ($veiculoId > 0 && !$veiculoBanco) {
                throw new Exception('O veículo selecionado não pertence à empresa informada.');
            }

            if (!$veiculoBanco) {
                // Veículo novo (não veio da frota cadastrada): cria o registro.
                $stmtNovoVeiculo->execute([
                    'empresa_id' => $empresaId,
                    'nome_caminhao' => $empresa['nome'],
                    'modelo' => $modeloEnviado,
                    'placa' => $placaEnviada,
                    'ano' => $anoEnviado,
                    'cor' => $corEnviada,
                ]);
                $veiculoId = (int) $pdo->lastInsertId();
                nexusRegistrarHistoricoEmpresa($pdo, $empresaId, 'frota', "Veículo adicionado à frota: {$placaEnviada} ({$modeloEnviado}).", $usuarioAtual);
            }
            // Veículo já cadastrado: o cadastro da frota NÃO é alterado aqui.
            // Alterações no cadastro são feitas apenas em "Gestão de Veículos".

            // A OS guarda o que foi enviado no formulário (snapshot próprio da OS).
            $veiculoBanco = [
                'id' => $veiculoId,
                'placa' => $placaEnviada,
                'modelo' => $modeloEnviado,
                'ano' => $anoEnviado,
                'cor' => $corEnviada,
            ];

            $stmtItem->execute([
                ':os_id' => $osId,
                ':tipo' => 'veiculo',
                ':empresa_id' => $empresaId,
                ':veiculo_id' => $veiculoId,
                ':servico_id' => null,
                ':peca_id' => null,
                ':placa' => $veiculoBanco['placa'],
                ':modelo' => $veiculoBanco['modelo'],
                ':ano' => $veiculoBanco['ano'],
                ':cor' => $veiculoBanco['cor'],
                ':data_execucao' => null,
                ':descricao' => null,
                ':valor' => 0,
                ':valor_unitario' => 0,
                ':quantidade' => 1,
            ]);

            // Salvar serviços do veículo
            foreach ($veiculo['servicos'] ?? [] as $servico) {
                $servicoId = (int) str_replace('banco_', '', (string) ($servico['tipo'] ?? ''));
                $stmtServico = $pdo->prepare('SELECT id, nome_servico, descricao, preco FROM cod_servicos WHERE id = :id LIMIT 1');
                $stmtServico->execute(['id' => $servicoId]);
                $servicoBanco = $stmtServico->fetch();
                if (!$servicoBanco) {
                    throw new Exception('Um dos serviços selecionados não foi encontrado.');
                }

                // O valor e a descrição são editáveis na tela da OS (o catálogo serve
                // apenas como valor padrão). Sem isto, o total salvo divergiria do
                // total exibido para o usuário.
                $valorServico = isset($servico['valor']) && $servico['valor'] !== ''
                    ? (float) $servico['valor']
                    : (float) ($servicoBanco['preco'] ?? 0);

                if (!nexusValidarValorMonetario($valorServico) || $valorServico <= 0) {
                    throw new Exception('Informe um valor válido para o serviço "' . $servicoBanco['nome_servico'] . '".');
                }

                $descricaoServico = trim((string) ($servico['descricao'] ?? ''));
                if ($descricaoServico === '') {
                    $descricaoServico = (string) $servicoBanco['descricao'];
                }

                $totalCalculado += $valorServico;
                $stmtItem->execute([
                    ':os_id' => $osId,
                    ':tipo' => 'servico',
                    ':empresa_id' => $empresaId,
                    ':veiculo_id' => $veiculoId,
                    ':servico_id' => $servicoBanco['id'],
                    ':peca_id' => null,
                    ':placa' => $veiculoBanco['placa'],
                    ':modelo' => null,
                    ':ano' => null,
                    ':cor' => null,
                    ':data_execucao' => $servico['data'] ?? null,
                    ':descricao' => $descricaoServico,
                    ':valor' => $valorServico,
                    ':valor_unitario' => $valorServico,
                    ':quantidade' => 1,
                ]);
            }

            // Salvar peças do veículo
            foreach ($veiculo['pecas'] ?? [] as $peca) {
                $pecaId = (int) ($peca['pecaId'] ?? 0);
                $quantidade = (int) ($peca['quantidade'] ?? 0);
                $stmtPeca = $pdo->prepare('SELECT id, nome, valor, estoque FROM pecas WHERE id = :id AND status = "Ativa" LIMIT 1');
                $stmtPeca->execute(['id' => $pecaId]);
                $pecaBanco = $stmtPeca->fetch();
                if (!$pecaBanco || $quantidade < 1 || $quantidade > (int) $pecaBanco['estoque']) {
                    throw new Exception('Peça inválida ou quantidade maior que o estoque disponível.');
                }
                $valorUnitario = (float) $pecaBanco['valor'];
                $valorPeca = $valorUnitario * $quantidade;
                $totalCalculado += $valorPeca;
                $stmtItem->execute([
                    ':os_id' => $osId,
                    ':tipo' => 'peca',
                    ':empresa_id' => $empresaId,
                    ':veiculo_id' => $veiculoId,
                    ':servico_id' => null,
                    ':peca_id' => $pecaBanco['id'],
                    ':placa' => $veiculoBanco['placa'],
                    ':modelo' => null,
                    ':ano' => null,
                    ':cor' => null,
                    ':data_execucao' => null,
                    ':descricao' => $pecaBanco['nome'],
                    ':valor' => $valorPeca,
                    ':valor_unitario' => $valorUnitario,
                    ':quantidade' => $quantidade,
                ]);
                $pdo->prepare('UPDATE pecas SET estoque = estoque - :quantidade WHERE id = :id')->execute(['quantidade' => $quantidade, 'id' => $pecaBanco['id']]);
            }
        }

        $stmtOS = $pdo->prepare('UPDATE ordens_servico SET valor_total = :valor_total WHERE id = :id');
        $stmtOS->execute([
            ':valor_total' => $totalCalculado,
            ':id' => $osId,
        ]);

        $pdo->commit();

        $valorFormatado = number_format($totalCalculado, 2, ',', '.');
        $mensagemHistorico = $action === 'atualizar_os'
            ? "Relatório de serviço (OS #{$osId}) atualizado. Novo valor total: R$ {$valorFormatado}."
            : "Novo relatório de serviço (OS #{$osId}) criado. Valor total: R$ {$valorFormatado}.";
        nexusRegistrarHistoricoEmpresa($pdo, $empresaId, 'relatorio', $mensagemHistorico, $usuarioAtual);

        // Retornar sucesso
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => true,
            'mensagem' => $action === 'atualizar_os' ? 'OS atualizada com sucesso!' : 'OS criada com sucesso!',
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
$empresas = $pdo->query('SELECT id, nome, cnpj, contato, email, telefone FROM empresas_cadastradas ORDER BY nome ASC')->fetchAll();

// Buscar serviços para popular JavaScript
$servicos = $pdo->query('SELECT id, nome_servico as nome, preco as valor, descricao FROM cod_servicos ORDER BY nome_servico ASC')->fetchAll();

// Buscar peças para popular JavaScript
$pecas = $pdo->query('SELECT id, nome, valor, unidade, estoque FROM pecas WHERE status = "Ativa" ORDER BY nome ASC')->fetchAll();

// Buscar veículos cadastrados para referência
$veiculos = $pdo->query('SELECT id, empresa_id, nome_caminhao as empresa, placa, modelo, ano, cor FROM caminhoes ORDER BY nome_caminhao ASC')->fetchAll();

// --------------------------------------------------------------
// MODO EDIÇÃO: carrega uma OS existente (?editar_os=ID) para pré-preencher o formulário.
// Só é permitido editar OS que ainda estejam "pendente_revisao" (mesma regra do botão
// Editar em revisao.php).
// --------------------------------------------------------------
$osEditando = null;
$frotaExistente = [];
if (isset($_GET['editar_os'])) {
    $osEditandoId = (int) $_GET['editar_os'];
    $stmtOsEdit = $pdo->prepare('SELECT * FROM ordens_servico WHERE id = :id LIMIT 1');
    $stmtOsEdit->execute(['id' => $osEditandoId]);
    $osEditando = $stmtOsEdit->fetch();

    if (!$osEditando) {
        header('Location: revisao.php?erro=os_nao_encontrada');
        exit;
    }
    if ($osEditando['status'] !== 'pendente_revisao') {
        header('Location: revisao.php?erro=os_bloqueada');
        exit;
    }

    $stmtItensEdit = $pdo->prepare('SELECT * FROM itens_os WHERE os_id = :os_id ORDER BY id ASC');
    $stmtItensEdit->execute(['os_id' => $osEditandoId]);
    $itensEditando = $stmtItensEdit->fetchAll();

    $veiculosAgrupados = [];
    foreach ($itensEditando as $item) {
        if ($item['tipo'] === 'veiculo') {
            $chave = (int) $item['veiculo_id'];
            $veiculosAgrupados[$chave] = [
                'dadosVeiculo' => [
                    'id' => $item['veiculo_id'],
                    'placa' => $item['placa'],
                    'modelo' => $item['modelo'],
                    'ano' => $item['ano'],
                    'cor' => $item['cor'],
                ],
                'servicos' => [],
                'pecas' => [],
            ];
        }
    }
    foreach ($itensEditando as $item) {
        $chave = (int) $item['veiculo_id'];
        if ($item['tipo'] === 'veiculo' || !isset($veiculosAgrupados[$chave])) {
            continue;
        }

        if ($item['tipo'] === 'servico' && $item['servico_id']) {
            $veiculosAgrupados[$chave]['servicos'][] = [
                'tipo' => 'banco_' . $item['servico_id'],
                'data' => $item['data_execucao'],
                'descricao' => $item['descricao'],
                'valor' => (float) $item['valor'],
            ];
        } elseif ($item['tipo'] === 'peca' && $item['peca_id']) {
            $veiculosAgrupados[$chave]['pecas'][] = [
                'pecaId' => (int) $item['peca_id'],
                'nome' => $item['descricao'],
                'quantidade' => (int) $item['quantidade'],
                'valorUnitario' => (float) $item['valor_unitario'],
                'valorTotal' => (float) $item['valor'],
            ];

            // Devolve, só para exibição no formulário, a quantidade que esta própria OS já
            // reservou - assim o técnico consegue manter (ou reduzir) a quantidade atual.
            // O banco só é alterado de verdade dentro da transação do POST (atualizar_os).
            foreach ($pecas as &$pecaRef) {
                if ((int) $pecaRef['id'] === (int) $item['peca_id']) {
                    $pecaRef['estoque'] = (int) $pecaRef['estoque'] + (int) $item['quantidade'];
                }
            }
            unset($pecaRef);
        }
    }
    $frotaExistente = array_values($veiculosAgrupados);
}

// Converter para JSON para JavaScript
$empresasJSON = json_encode($empresas, JSON_UNESCAPED_UNICODE);
$servicosJSON = json_encode($servicos, JSON_UNESCAPED_UNICODE);
$pecasJSON = json_encode($pecas, JSON_UNESCAPED_UNICODE);
$veiculosJSON = json_encode($veiculos, JSON_UNESCAPED_UNICODE);
$frotaExistenteJSON = json_encode($frotaExistente, JSON_UNESCAPED_UNICODE);
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
    <link rel="stylesheet" href="../../assets/css/relatorio-visualizador.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="../../assets/js/relatorio-visualizador.js"></script>
    <script src="../../assets/js/common.js"></script>
    <script src="../../assets/js/pages.js"></script>
</head>
<body class="corpo-dashboard">
    <div class="layout-erp">
        <main class="conteudo-principal">
            <!-- Success Message -->
            <div id="successMessage" style="display: none; position: fixed; top: 20px; right: 20px; background: #d4edda; color: #155724; padding: 15px 20px; border: 1px solid #c3e6cb; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; font-weight: 500; animation: slideIn 0.3s ease-out; max-width: 300px;">
                <i class="bi bi-check-circle-fill"></i> <span id="successMessageTexto">OS registrada com sucesso!</span> <button onclick="window.location.href='revisao.php'" style="background: none; border: none; color: #155724; text-decoration: underline; cursor: pointer; margin-left: 10px;">Ver em Revisão</button>
            </div>

            <section class="titulo-pagina-stack">
                <?php if ($osEditando): ?>
                    <h1><i class="bi bi-pencil-square"></i> Editar OS #<?= (int) $osEditando['id'] ?></h1>
                    <p>Corrija os dados desta ordem de serviço antes de aprovar ou reprovar.</p>
                <?php else: ?>
                    <h1><i class="bi bi-plus-circle"></i> Registrar Nova OS</h1>
                    <p>Preencha os dados do cliente e serviços. O sistema valida duplicatas automaticamente.</p>
                <?php endif; ?>
            </section>

            <form id="formNovoAtendimento">
                <div class="container-formulario-novo-atendimento">
                    <div class="secao-formulario-novo">
                        <h3><i class="bi bi-building"></i> Informações da Empresa</h3>
                        <div class="linha-campos">
                            <div class="bloco-campo-novo">
                                <label><i class="bi bi-shop"></i> Selecionar Empresa</label>
                                <select id="selecao-empresa" required onchange="preencherDadosEmpresa()" style="min-height:48px; padding:12px 14px; font-size:0.95rem; box-sizing:border-box;">
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
                                <input type="text" id="display-cnpj" readonly style="min-height:48px; padding:12px 14px; font-size:0.95rem; box-sizing:border-box;">
                            </div>
                        </div>
                        <div class="linha-campos">
                            <div class="bloco-campo-novo" style="grid-column: 1 / -1;">
                                <label><i class="bi bi-tag"></i> Nome da OS <span style="text-transform:none; font-weight:400; opacity:.7;">(opcional, para identificar facilmente esta ordem depois)</span></label>
                                <input type="text" id="nome-os" name="nome_os" maxlength="150" placeholder="Ex.: Manutenção preventiva - Frota Sul" style="min-height:48px; padding:12px 14px; font-size:0.95rem; box-sizing:border-box;" value="<?= $osEditando ? htmlspecialchars($osEditando['nome_os'] ?? '') : '' ?>">
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
                                <i class="bi bi-check-circle"></i> <?= $osEditando ? 'Salvar Alterações' : 'Registrar Atendimento' ?>
                            </button>
                            <button type="button" onclick="limparFormulario()" class="botao-cancelar" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="bi bi-trash"></i> Limpar
                            </button>
                            <button type="button" onclick="window.location.href='<?= $osEditando ? 'revisao.php' : 'menu.php' ?>'" class="botao-cancelar" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="bi bi-arrow-left"></i> Voltar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Campo oculto para enviar dados JSON -->
                <input type="hidden" name="action" value="<?= $osEditando ? 'atualizar_os' : 'salvar_os' ?>">
                <input type="hidden" name="os_id" value="<?= $osEditando ? (int) $osEditando['id'] : '' ?>">
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

        // Dados da OS em edição (null quando estamos criando uma OS nova)
        const OS_EDITANDO_ID = <?= $osEditando ? (int) $osEditando['id'] : 'null' ?>;
        const EMPRESA_OS_EDITANDO = <?= $osEditando ? (int) $osEditando['empresa_id'] : 'null' ?>;
        const FROTA_EXISTENTE = <?= $frotaExistenteJSON ?>;

        // Limites/regras vindos do PHP para o JS usar as MESMAS regras do servidor.
        const ANO_VEICULO_MIN = <?= nexusLimitesAnoVeiculo()['min'] ?>;
        const ANO_VEICULO_MAX = <?= nexusLimitesAnoVeiculo()['max'] ?>;

        // Placa: formato antigo (ABC1234) ou Mercosul (ABC1D23)
        function validarPlacaJS(valor) {
            const limpa = String(valor || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            return /^[A-Z]{3}(\d{4}|\d[A-Z]\d{2})$/.test(limpa);
        }

        function aplicarMascaraPlaca(input) {
            let v = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
            if (v.length > 3) v = v.slice(0, 3) + '-' + v.slice(3);
            input.value = v;
        }

        // Manter compatibilidade com lógica antiga (CONFIG_SERVICOS vazio, dados do banco acima)
        const CONFIG_SERVICOS = {};

        let contagemVeiculos = 0;

        function adicionarCamposVeiculo(dadosPreenchidos) {
            contagemVeiculos++;
            const container = document.getElementById('container-veiculos');
            const div = document.createElement('div');
            div.className = 'bloco-veiculo';
            
            div.innerHTML = `
                <div class="secao-formulario-novo painel-veiculo" style="position: relative;">
                    <div class="cabecalho-veiculo">
                        <div class="identificacao-veiculo">
                            <span class="numero-veiculo">CAMINHÃO ${contagemVeiculos}</span>
                            <h3><i class="bi bi-truck-front"></i> Dados do veículo</h3>
                        </div>
                        <div class="acoes-veiculo">
                            <button type="button" class="botao-secundario" onclick="mostrarVeiculosCadastrados(this)" style="padding: 10px 16px; font-size: 0.95rem; white-space: nowrap;"><i class="bi bi-search"></i> Buscar veículo</button>
                            <button type="button" class="botao-remover-veiculo" onclick="removerCamposVeiculo(this)" title="Remover este caminhão"><i class="bi bi-trash3"></i> Remover</button>
                        </div>
                    </div>
                    <div class="painel-veiculos-existentes" style="display: none; margin-top: 16px;"></div>
                    <div class="linha-campos">
                        <input type="hidden" class="v-veiculo-id" value="">
                        <div class="bloco-campo-novo">
                            <label><i class="bi bi-tag"></i> Placa</label>
                            <input type="text" class="v-placa" placeholder="ABC-1234 ou ABC1D23" maxlength="8" required oninput="aplicarMascaraPlaca(this)">
                        </div>
                        <div class="bloco-campo-novo">
                            <label><i class="bi bi-type"></i> Modelo</label>
                            <input type="text" class="v-modelo" placeholder="Modelo do veículo" required>
                        </div>
                        <div class="bloco-campo-novo">
                            <label><i class="bi bi-calendar-event"></i> Ano</label>
                            <input type="number" class="v-ano" placeholder="2024" min="<?= nexusLimitesAnoVeiculo()['min'] ?>" max="<?= nexusLimitesAnoVeiculo()['max'] ?>" step="1" required oninput="validarAnoVeiculo(this)">
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
            if (dadosPreenchidos) {
                preencherBlocoVeiculo(div, dadosPreenchidos);
            } else {
                adicionarServicoAoVeiculo(div.querySelector('button[onclick*="adicionarServicoAoVeiculo"]'));
            }
        }

        // Preenche um bloco de veículo recém-criado com dados de uma OS já existente
        // (usado no modo de edição, ?editar_os=ID).
        function preencherBlocoVeiculo(div, veiculoData) {
            const dv = veiculoData.dadosVeiculo || {};
            div.querySelector('.v-veiculo-id').value = dv.id || '';
            div.querySelector('.v-placa').value = dv.placa || '';
            div.querySelector('.v-modelo').value = dv.modelo || '';
            div.querySelector('.v-ano').value = dv.ano || '';
            div.querySelector('.v-cor').value = dv.cor || '';

            const servicos = veiculoData.servicos || [];
            if (servicos.length === 0) {
                adicionarServicoAoVeiculo(div.querySelector('button[onclick*="adicionarServicoAoVeiculo"]'));
            } else {
                servicos.forEach(servico => {
                    adicionarServicoAoVeiculo(div.querySelector('button[onclick*="adicionarServicoAoVeiculo"]'));
                    const blocos = div.querySelectorAll('.item-servico');
                    const novoBloco = blocos[blocos.length - 1];
                    const selectTipo = novoBloco.querySelector('.s-tipo');
                    if (![...selectTipo.options].some(o => o.value === servico.tipo)) {
                        const opt = document.createElement('option');
                        opt.value = servico.tipo;
                        opt.textContent = servico.descricao || servico.tipo;
                        selectTipo.appendChild(opt);
                    }
                    selectTipo.value = servico.tipo;
                    novoBloco.querySelector('.s-data').value = servico.data || '';
                    novoBloco.querySelector('.s-desc').value = servico.descricao || '';
                    novoBloco.querySelector('.s-valor').value = servico.valor || 0;
                });
            }

            (veiculoData.pecas || []).forEach(peca => {
                adicionarPecaAoVeiculo(div.querySelector('button[onclick*="adicionarPecaAoVeiculo"]'));
                const blocosPeca = div.querySelectorAll('.item-peca');
                const novoBlocoPeca = blocosPeca[blocosPeca.length - 1];
                const selectPeca = novoBlocoPeca.querySelector('.p-peca');
                const valorFmt = parseFloat(peca.valorUnitario || 0).toFixed(2);
                const valorOpcao = `peca_${peca.pecaId}`;
                if (![...selectPeca.options].some(o => o.value === valorOpcao)) {
                    const opt = document.createElement('option');
                    opt.value = valorOpcao;
                    opt.dataset.id = peca.pecaId;
                    opt.dataset.valor = valorFmt;
                    opt.dataset.nome = peca.nome;
                    opt.textContent = `${peca.nome} — R$ ${valorFmt}`;
                    selectPeca.appendChild(opt);
                }
                selectPeca.value = valorOpcao;
                novoBlocoPeca.querySelector('.p-qtde').value = peca.quantidade || 1;
                novoBlocoPeca.querySelector('.p-valor-unitario').value = valorFmt;
                novoBlocoPeca.querySelector('.p-total').value = 'R$ ' + parseFloat(peca.valorTotal || 0).toFixed(2).replace('.', ',');
                novoBlocoPeca.querySelector('.p-nome').value = peca.nome || '';
                novoBlocoPeca.querySelector('.p-peca-id').value = peca.pecaId || '';
            });

            calcularTotalGeral();
        }

        function removerCamposVeiculo(botao) {
            const blocoVeiculo = botao.closest('.bloco-veiculo');
            if (!blocoVeiculo) return;

            blocoVeiculo.remove();
            calcularTotalGeral();
        }

        function mostrarVeiculosCadastrados(botao) {
            const empresaSelecionada = document.getElementById('selecao-empresa').value;
            if (!empresaSelecionada) {
                alert('Selecione primeiro uma empresa para buscar veículos cadastrados.');
                return;
            }

            // Prioriza a relação por empresa_id e mantém compatibilidade com veículos antigos.
            const nomeEmpresa = EMPRESAS_BANCO.find(e => e.id == empresaSelecionada)?.nome;
            const veiculosDaEmpresa = VEICULOS_BANCO.filter(v =>
                String(v.empresa_id || '') === String(empresaSelecionada) ||
                (!v.empresa_id && v.empresa === nomeEmpresa)
            );
            
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

            blocoVeiculo.querySelector('.v-veiculo-id').value = veiculo.id || '';
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

            if (isNaN(ano) || ano < ANO_VEICULO_MIN || ano > ANO_VEICULO_MAX) {
                aviso.textContent = `Ano inválido. Informe entre ${ANO_VEICULO_MIN} e ${ANO_VEICULO_MAX}.`;
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

                if (!placa) {
                    errosEncontrados.push(`Veículo ${numVeiculo}: informe a placa.`);
                } else if (!validarPlacaJS(placa)) {
                    errosEncontrados.push(`Veículo ${numVeiculo}: placa inválida (use ABC-1234 ou ABC1D23).`);
                }
                if (!modelo) errosEncontrados.push(`Veículo ${numVeiculo}: informe o modelo.`);
                if (!corEl)  errosEncontrados.push(`Veículo ${numVeiculo}: informe a cor.`);

                if (!anoEl.value.trim()) {
                    errosEncontrados.push(`Veículo ${numVeiculo}: informe o ano.`);
                } else if (isNaN(ano) || ano < ANO_VEICULO_MIN || ano > ANO_VEICULO_MAX) {
                    errosEncontrados.push(`Veículo ${numVeiculo}: ano inválido (deve ser entre ${ANO_VEICULO_MIN} e ${ANO_VEICULO_MAX}).`);
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
                        id: bloco.querySelector('.v-veiculo-id')?.value || '',
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
            formData.append('usuario', getCurrentUserName());

            fetch('novo_atendimento.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    if (OS_EDITANDO_ID) {
                        // Em modo edição, não faz sentido "limpar e recomeçar" - volta para a revisão.
                        window.location.href = 'revisao.php?atualizado=1';
                        return;
                    }

                    abrirPreVisualizacaoRelatorio(data.os_id, frotaCompleta);

                    const successDiv = document.getElementById('successMessage');
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

        function abrirPreVisualizacaoRelatorio(osId, frota) {
            const empresaId = document.getElementById('selecao-empresa').value;
            const empresaDados = EMPRESAS_BANCO.find(e => String(e.id) === String(empresaId)) || {};

            const caminhoes = frota.map(veiculo => {
                const dv = veiculo.dadosVeiculo;
                const itens = [
                    ...veiculo.servicos.map(s => ({ tipo: 'servico', descricao: s.descricao, valor: Number(s.valor || 0), quantidade: 1, data: s.data })),
                    ...veiculo.pecas.map(p => ({ tipo: 'peca', descricao: p.nome, valor: Number(p.valorTotal || 0), quantidade: Number(p.quantidade || 1) }))
                ];
                const valorTotal = itens.reduce((soma, item) => soma + item.valor, 0);
                const primeiraData = veiculo.servicos.length ? veiculo.servicos[0].data : '';
                return {
                    chave: dv.placa || ('veiculo-' + Math.random().toString(36).slice(2)),
                    modelo: dv.modelo,
                    placa: dv.placa,
                    dataServico: primeiraData ? new Date(primeiraData + 'T00:00:00').toLocaleDateString('pt-BR') : '-',
                    itens: itens,
                    valorTotal: valorTotal
                };
            });

            const valorTotalGeral = caminhoes.reduce((soma, c) => soma + c.valorTotal, 0);

            NexusRelatorio.abrir({
                osId: osId,
                nomeOS: document.getElementById('nome-os').value.trim(),
                logoUrl: '../../assets/images/icon-sistem.png',
                salvarUrl: '../../php/relatorio_crud.php',
                podeSalvar: true,
                empresa: {
                    nome: empresaDados.nome || '',
                    cnpj: empresaDados.cnpj || '',
                    contato: empresaDados.contato || '',
                    email: empresaDados.email || '',
                    telefone: empresaDados.telefone || ''
                },
                dataEmissao: new Date().toLocaleDateString('pt-BR'),
                caminhoes: caminhoes,
                valorTotalGeral: valorTotalGeral,
                relatorioSalvo: null,
                aoFechar: function () { window.location.href = 'revisao.php'; }
            });
        }

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
            if (OS_EDITANDO_ID && Array.isArray(FROTA_EXISTENTE) && FROTA_EXISTENTE.length > 0) {
                document.getElementById('selecao-empresa').value = EMPRESA_OS_EDITANDO;
                preencherDadosEmpresa();
                FROTA_EXISTENTE.forEach(veiculoData => adicionarCamposVeiculo(veiculoData));
            } else {
                adicionarCamposVeiculo();
            }
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
