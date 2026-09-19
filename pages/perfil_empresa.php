<?php
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/../php/historico_helper.php';

$pdo = nexusDb();
nexusGarantirTabelaHistoricoEmpresas($pdo);

$empresaId = (int) ($_GET['id'] ?? 0);

$stmtEmpresa = $pdo->prepare('SELECT * FROM empresas_cadastradas WHERE id = :id LIMIT 1');
$stmtEmpresa->execute([':id' => $empresaId]);
$empresa = $stmtEmpresa->fetch();

if (!$empresa) {
    header('Location: clientes.php');
    exit;
}

$stmtFrota = $pdo->prepare('SELECT * FROM caminhoes WHERE empresa_id = :id ORDER BY id DESC');
$stmtFrota->execute([':id' => $empresaId]);
$veiculos = $stmtFrota->fetchAll();

// A tabela de OS só existe depois que "Novo Atendimento" roda pela primeira vez.
$ordens = [];
$existeTabelaOS = $pdo->query("SHOW TABLES LIKE 'ordens_servico'")->fetch();
if ($existeTabelaOS) {
    $stmtOS = $pdo->prepare('SELECT id, status, valor_total, data_criacao, data_atualizacao, descricao_geral, motivo_reprovacao FROM ordens_servico WHERE empresa_id = :id ORDER BY data_criacao DESC, id DESC LIMIT 50');
    $stmtOS->execute([':id' => $empresaId]);
    $ordens = $stmtOS->fetchAll();
}

$stmtHistorico = $pdo->prepare('SELECT * FROM historico_atividades_empresas WHERE empresa_id = :id ORDER BY data_registro DESC, id DESC LIMIT 200');
$stmtHistorico->execute([':id' => $empresaId]);
$historico = $stmtHistorico->fetchAll();

$totalCaminhoes = count($veiculos);
$totalOS = count($ordens);
$totalAprovadas = 0;
$valorAprovado = 0.0;
foreach ($ordens as $os) {
    if ($os['status'] === 'finalizada') {
        $totalAprovadas++;
        $valorAprovado += (float) $os['valor_total'];
    }
}

function hPerfil($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function dataPerfil($valor): string
{
    return $valor ? date('d/m/Y H:i', strtotime($valor)) : '-';
}

function statusLabelPerfil($status): string
{
    $labels = ['pendente_revisao' => 'Em revisão', 'finalizada' => 'Aprovada', 'reprovada' => 'Reprovada'];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
}

$historicoJson = array_map(static function (array $item): array {
    return [
        'id' => (int) $item['id'],
        'tipo' => $item['tipo'],
        'mensagem' => $item['mensagem'],
        'usuario' => $item['usuario'],
        'data' => dataPerfil($item['data_registro']),
    ];
}, $historico);
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visualizar Empresa - Nexus HUB</title>
<link rel="icon" type="image/png" href="../assets/images/icon-sistem.png">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/pages.css">
<link rel="stylesheet" href="../assets/css/admin-forms.css">
<style>
.perfil-topo{display:flex;flex-direction:column;gap:16px}
.perfil-topo-linha-acoes{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.perfil-topo-acoes-esquerda{display:flex;gap:10px;flex-wrap:wrap}
.perfil-topo-linha-acoes .botao-acao,.perfil-topo-linha-acoes .botao-secundario,.perfil-topo-linha-acoes .botao-cinza{width:auto}
.perfil-topo-linha-acoes a{display:inline-flex;align-items:center;justify-content:center;gap:7px;text-align:center}
.botao-cinza{padding:10px 14px;background:linear-gradient(135deg,#64748b 0%,#475569 100%);color:#fff;border:none;border-radius:9px;cursor:pointer;font-weight:700;letter-spacing:.02em;box-shadow:0 8px 16px rgba(71,85,105,.16);transition:transform .25s ease,opacity .25s ease,box-shadow .25s ease}
.botao-cinza:hover{opacity:.92;transform:translateY(-1px)}
.perfil-grid{display:grid;grid-template-columns:1fr 1.3fr 1fr;gap:22px;margin-top:20px;align-items:start}
@media(max-width:1180px){.perfil-grid{grid-template-columns:1fr 1fr}}
@media(max-width:820px){.perfil-grid{grid-template-columns:1fr}}
.perfil-card{background:var(--surface);border:1px solid var(--bordar);border-radius:14px;padding:24px}
.perfil-card h3{margin:0 0 16px;font-size:1rem;color:var(--texto-principal);display:flex;align-items:center;gap:8px}
.perfil-card h3 i{color:var(--verde-esmeralda)}
.perfil-dados-empresa{text-align:center;margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--bordar)}
.perfil-dados-empresa .icone-empresa-grande{display:inline-grid;place-items:center;width:58px;height:58px;border-radius:14px;background:rgba(16,185,129,.12);color:var(--verde-esmeralda);font-size:1.5rem;margin-bottom:10px}
.perfil-dados-empresa h2{margin:0 0 4px;font-size:1.25rem;color:var(--texto-principal)}
.perfil-dados-empresa p{margin:0;color:var(--texto-secundario);font-size:.82rem}
.perfil-campo{margin-bottom:14px}
.perfil-campo label{display:block;font-size:.68rem;font-weight:800;letter-spacing:.03em;text-transform:uppercase;color:var(--texto-secundario);margin-bottom:5px}
.perfil-campo div{font-size:.92rem;color:var(--texto-principal);word-break:break-word}
.perfil-campo div a{color:var(--verde-esmeralda);text-decoration:none}
.perfil-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px}
.perfil-stat{background:var(--surface-strong);border:1px solid var(--bordar);border-radius:10px;padding:12px;text-align:center}
.perfil-stat strong{display:block;font-size:1.15rem;color:var(--texto-principal)}
.perfil-stat span{font-size:.68rem;color:var(--texto-secundario);text-transform:uppercase;font-weight:700}
.perfil-lista{display:flex;flex-direction:column;gap:10px;max-height:420px;overflow-y:auto;padding-right:4px}
.perfil-item-frota{padding:13px 14px;border:1px solid var(--bordar);border-radius:10px;background:var(--surface-strong);display:flex;justify-content:space-between;align-items:center;gap:8px}
.perfil-item-frota strong{font-size:.9rem;color:var(--texto-principal)}
.perfil-item-frota p{margin:3px 0 0;font-size:.78rem;color:var(--texto-secundario)}
.perfil-item-os{padding:13px 14px;border:1px solid var(--bordar);border-radius:10px;background:var(--surface-strong)}
.perfil-item-os .linha-topo{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px}
.perfil-item-os .linha-topo strong{font-size:.86rem;color:var(--texto-principal)}
.perfil-item-os p{margin:0;font-size:.78rem;color:var(--texto-secundario)}
.perfil-vazio{text-align:center;padding:26px 10px;color:var(--texto-secundario);font-size:.85rem}
.perfil-historico-lista{display:flex;flex-direction:column;gap:12px;max-height:420px;overflow-y:auto;margin-bottom:16px;padding-right:6px}
.perfil-evento{padding:12px 14px;border-radius:10px;background:var(--surface-strong);border-left:4px solid var(--verde-esmeralda)}
.perfil-evento.tipo-manual{border-left-color:#4a90e2}
.perfil-evento.tipo-aprovacao{border-left-color:var(--verde-esmeralda)}
.perfil-evento.tipo-reprovacao{border-left-color:var(--erro)}
.perfil-evento.tipo-frota{border-left-color:#8b5cf6}
.perfil-evento.tipo-relatorio{border-left-color:var(--aviso)}
.perfil-evento p{margin:0 0 5px;font-size:.85rem;color:var(--texto-principal)}
.perfil-evento small{color:var(--texto-secundario);font-size:.72rem;display:flex;justify-content:space-between;gap:8px}
.perfil-chat{display:flex;gap:8px;border-top:1px solid var(--bordar);padding-top:16px}
.perfil-chat input{flex:1;min-height:44px;padding:10px 14px;border:1px solid var(--bordar);border-radius:999px;background:var(--surface);color:var(--texto-principal)}
.perfil-chat input:focus{outline:none;border-color:var(--verde-esmeralda);box-shadow:0 0 0 3px rgba(16,185,129,.12)}
.perfil-chat button{border-radius:999px;padding:0 22px;border:none;background:var(--verde-esmeralda);color:#fff;font-weight:700;cursor:pointer}
.perfil-chat button:disabled{opacity:.6;cursor:not-allowed}
</style>
</head>
<body class="corpo-dashboard"><div class="layout-erp"><main class="conteudo-principal">

<section class="titulo-pagina-stack perfil-topo">
    <div>
        <h1><i class="bi bi-building"></i> <?= hPerfil($empresa['nome']) ?></h1>
        <p>Detalhes cadastrais, frota, relatórios e histórico de atividades da empresa.</p>
    </div>
    <div class="perfil-topo-linha-acoes">
        <div class="perfil-topo-acoes-esquerda">
            <a href="operacoes/novo_atendimento.php" class="botao-acao"><i class="bi bi-plus-circle"></i> Nova OS</a>
            <a href="cadastros/cadastrar_empresa.php?edit=<?= (int) $empresa['id'] ?>" class="botao-cinza"><i class="bi bi-pencil"></i> Editar dados</a>
        </div>
        <a href="clientes.php" class="botao-cinza"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
</section>

<div class="perfil-grid">

    <section class="perfil-card">
        <div class="perfil-dados-empresa">
            <span class="icone-empresa-grande"><i class="bi bi-building"></i></span>
            <h2><?= hPerfil($empresa['nome']) ?></h2>
            <p><?= hPerfil($empresa['cnpj'] ?: 'CNPJ não informado') ?></p>
        </div>
        <div class="perfil-campo"><label>Contato principal</label><div><?= hPerfil($empresa['contato'] ?: '-') ?></div></div>
        <div class="perfil-campo"><label>Telefone / WhatsApp</label><div><?= hPerfil($empresa['telefone'] ?: '-') ?></div></div>
        <div class="perfil-campo"><label>E-mail corporativo</label><div><?= $empresa['email'] ? '<a href="mailto:' . hPerfil($empresa['email']) . '">' . hPerfil($empresa['email']) . '</a>' : '-' ?></div></div>
        <div class="perfil-campo"><label>Site</label><div><?= $empresa['site'] ? '<a href="' . hPerfil($empresa['site']) . '" target="_blank" rel="noopener">' . hPerfil($empresa['site']) . '</a>' : '-' ?></div></div>
        <div class="perfil-campo"><label>Observações</label><div><?= $empresa['observacoes'] ? nl2br(hPerfil($empresa['observacoes'])) : '-' ?></div></div>
    </section>

    <section class="perfil-card">
        <h3><i class="bi bi-activity"></i> Histórico de atividades</h3>
        <div id="perfil-historico-lista" class="perfil-historico-lista">
            <div class="perfil-vazio">Carregando histórico...</div>
        </div>
        <form id="form-nota" class="perfil-chat">
            <input type="text" id="campo-nota" maxlength="1000" placeholder="Ex.: E-mail enviado ao cliente, OS aprovada pelo cliente..." autocomplete="off">
            <button type="submit" id="botao-nota"><i class="bi bi-send"></i> Enviar</button>
        </form>
    </section>

    <div style="display:flex;flex-direction:column;gap:22px">
        <section class="perfil-card">
            <h3><i class="bi bi-file-earmark-text"></i> Relatórios de OS</h3>
            <div class="perfil-stats">
                <div class="perfil-stat"><strong><?= $totalOS ?></strong><span>OS no total</span></div>
                <div class="perfil-stat"><strong><?= $totalAprovadas ?></strong><span>Aprovadas</span></div>
                <div class="perfil-stat"><strong>R$ <?= number_format($valorAprovado, 2, ',', '.') ?></strong><span>Faturado</span></div>
            </div>
            <?php if ($ordens): ?>
                <a href="operacoes/revisao.php?status=todas&empresa_id=<?= (int) $empresa['id'] ?>" class="botao-pequeno" style="display:inline-flex;margin-bottom:12px"><i class="bi bi-list-ul"></i> Ver todas as OS</a>
            <?php endif; ?>
            <div class="perfil-lista">
                <?php if (!$ordens): ?>
                    <div class="perfil-vazio"><i class="bi bi-inbox"></i><br>Nenhuma OS registrada para esta empresa.</div>
                <?php else: foreach ($ordens as $os): ?>
                    <div class="perfil-item-os">
                        <div class="linha-topo">
                            <strong>OS #<?= (int) $os['id'] ?></strong>
                            <span class="status-pill status-<?= hPerfil($os['status']) ?>"><?= hPerfil(statusLabelPerfil($os['status'])) ?></span>
                        </div>
                        <p>R$ <?= number_format((float) $os['valor_total'], 2, ',', '.') ?> · <?= hPerfil(dataPerfil($os['data_criacao'])) ?></p>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <section class="perfil-card">
            <h3><i class="bi bi-truck"></i> Veículos cadastrados</h3>
            <a href="cadastros/cadastro_veiculo.php" class="botao-pequeno" style="display:inline-flex;margin-bottom:12px"><i class="bi bi-plus"></i> Adicionar veículo</a>
            <div class="perfil-lista">
                <?php if (!$veiculos): ?>
                    <div class="perfil-vazio"><i class="bi bi-truck"></i><br>Nenhum veículo cadastrado para esta empresa.</div>
                <?php else: foreach ($veiculos as $veiculo): ?>
                    <div class="perfil-item-frota">
                        <div>
                            <strong><?= hPerfil($veiculo['placa'] ?: 'Sem placa') ?></strong>
                            <p><?= hPerfil($veiculo['modelo'] ?: 'Modelo não informado') ?><?= $veiculo['ano'] ? ' · ' . (int) $veiculo['ano'] : '' ?><?= $veiculo['cor'] ? ' · ' . hPerfil($veiculo['cor']) : '' ?></p>
                        </div>
                        <a href="cadastros/cadastro_veiculo.php?edit=<?= (int) $veiculo['id'] ?>" class="botao-pequeno"><i class="bi bi-pencil"></i></a>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>
    </div>

</div>

</main></div>
<script src="../assets/js/common.js"></script><script src="../assets/js/pages.js"></script>
<script>
const EMPRESA_ID = <?= (int) $empresa['id'] ?>;
let HISTORICO_ATUAL = <?= json_encode($historicoJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function escaparPerfil(valor) {
    const div = document.createElement('div');
    div.textContent = valor == null ? '' : String(valor);
    return div.innerHTML;
}

function renderHistorico() {
    const container = document.getElementById('perfil-historico-lista');
    if (!HISTORICO_ATUAL.length) {
        container.innerHTML = '<div class="perfil-vazio"><i class="bi bi-inbox"></i><br>Nenhuma atividade registrada ainda.</div>';
        return;
    }
    container.innerHTML = HISTORICO_ATUAL.map(item => `
        <div class="perfil-evento tipo-${escaparPerfil(item.tipo)}">
            <p>${escaparPerfil(item.mensagem)}</p>
            <small><span>${escaparPerfil(item.usuario)}</span><span>${escaparPerfil(item.data)}</span></small>
        </div>
    `).join('');
}

async function atualizarHistorico() {
    try {
        const resposta = await fetch(`../php/historico_empresa_crud.php?action=listar&empresa_id=${EMPRESA_ID}`);
        const dados = await resposta.json();
        if (dados.success) {
            HISTORICO_ATUAL = dados.data;
            renderHistorico();
        }
    } catch (erro) {
        console.error('Não foi possível atualizar o histórico:', erro);
    }
}

document.getElementById('form-nota').addEventListener('submit', async function (evento) {
    evento.preventDefault();
    const campo = document.getElementById('campo-nota');
    const botao = document.getElementById('botao-nota');
    const mensagem = campo.value.trim();
    if (!mensagem) return;

    botao.disabled = true;
    try {
        const formData = new FormData();
        formData.append('action', 'nota');
        formData.append('empresa_id', EMPRESA_ID);
        formData.append('mensagem', mensagem);
        formData.append('usuario', getCurrentUserName());

        const resposta = await fetch('../php/historico_empresa_crud.php', { method: 'POST', body: formData });
        const dados = await resposta.json();
        if (dados.success) {
            campo.value = '';
            await atualizarHistorico();
        } else {
            alert(dados.message || 'Não foi possível registrar a nota.');
        }
    } catch (erro) {
        alert('Erro ao enviar a nota. Tente novamente.');
    } finally {
        botao.disabled = false;
        campo.focus();
    }
});

renderHistorico();
setInterval(atualizarHistorico, 15000);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('.layout-erp').insertAdjacentHTML('afterbegin', getSidebarHTML('clientes'));
    document.querySelector('main').insertAdjacentHTML('afterbegin', getHeaderHTML());
});
</script>
</body></html>
