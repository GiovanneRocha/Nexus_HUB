<?php
require_once __DIR__ . '/../../php/db.php';

$pdo = nexusDb();
$busca = trim((string) ($_GET['busca'] ?? ''));
$empresaId = (int) ($_GET['empresa_id'] ?? 0);
$empresas = $pdo->query('SELECT id, nome FROM empresas_cadastradas ORDER BY nome ASC')->fetchAll();
$where = ['os.status = "finalizada"'];
$params = [];
if ($empresaId > 0) {
    $where[] = 'os.empresa_id = :empresa_id';
    $params['empresa_id'] = $empresaId;
}
if ($busca !== '') {
    $where[] = '(CAST(os.id AS CHAR) LIKE :busca_id OR empresas_cadastradas.nome LIKE :busca_empresa OR EXISTS (SELECT 1 FROM itens_os busca_item WHERE busca_item.os_id = os.id AND (busca_item.placa LIKE :busca_placa OR busca_item.modelo LIKE :busca_modelo)))';
    $termo = '%' . $busca . '%';
    $params += ['busca_id' => $termo, 'busca_empresa' => $termo, 'busca_placa' => $termo, 'busca_modelo' => $termo];
}
$stmt = $pdo->prepare('SELECT os.id, os.data_criacao, os.data_atualizacao, os.valor_total, os.descricao_geral, os.empresa_id, empresas_cadastradas.nome AS empresa_nome, empresas_cadastradas.cnpj, empresas_cadastradas.email, empresas_cadastradas.telefone, empresas_cadastradas.contato FROM ordens_servico AS os INNER JOIN empresas_cadastradas ON empresas_cadastradas.id = os.empresa_id WHERE ' . implode(' AND ', $where) . ' ORDER BY os.data_criacao DESC, os.id DESC');
$stmt->execute($params);
$ordens = $stmt->fetchAll();
$itensPorOS = [];
if ($ordens) {
    $ids = array_map('intval', array_column($ordens, 'id'));
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $itens = $pdo->prepare('SELECT itens_os.*, cod_servicos.nome_servico, pecas.nome AS nome_peca FROM itens_os LEFT JOIN cod_servicos ON cod_servicos.id = itens_os.servico_id LEFT JOIN pecas ON pecas.id = itens_os.peca_id WHERE itens_os.os_id IN (' . $marks . ') ORDER BY itens_os.os_id, itens_os.id');
    $itens->execute($ids);
    foreach ($itens->fetchAll() as $item) $itensPorOS[$item['os_id']][] = $item;
}
function hHistorico($valor): string { return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); }
function dataHistorico($valor): string { return $valor ? date('d/m/Y H:i', strtotime($valor)) : '-'; }
function caminhoesHistorico($itens): array {
    $grupos = [];
    foreach ($itens as $item) {
        $chave = $item['veiculo_id'] ?: ($item['placa'] ?: 'sem-veiculo');
        if (!isset($grupos[$chave])) $grupos[$chave] = ['veiculo' => $item, 'itens' => []];
        if ($item['tipo'] !== 'veiculo') $grupos[$chave]['itens'][] = $item;
    }
    return array_values($grupos);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de OS - Nexus HUB</title>
    <link rel="icon" type="image/png" href="../../assets/images/icon-sistem.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/pages.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .historico-filtros { display:grid; grid-template-columns:minmax(220px,1.5fr) minmax(180px,1fr) auto; gap:12px; align-items:end; margin:20px 0; padding:18px; background:var(--surface-strong); border:1px solid var(--bordar); border-radius:10px; }
        .historico-filtros label { display:block; margin-bottom:7px; color:var(--texto-secundario); font-size:.72rem; font-weight:700; text-transform:uppercase; }
        .historico-filtros input,.historico-filtros select { width:100%; min-height:42px; padding:9px 12px; border:1px solid var(--bordar); border-radius:8px; background:var(--surface); color:var(--texto-principal); }
        .historico-lista { display:grid; gap:16px; }
        .historico-card { overflow:hidden; border:1px solid var(--bordar); border-radius:10px; background:var(--surface-strong); }
        .historico-cabecalho { display:flex; justify-content:space-between; gap:16px; padding:18px 20px; border-bottom:1px solid var(--bordar); }
        .historico-cabecalho h2 { margin:0 0 5px; color:var(--texto-principal); font-size:1.1rem; }
        .historico-cabecalho p,.historico-resumo { color:var(--texto-secundario); }
        .historico-resumo { display:flex; gap:20px; flex-wrap:wrap; padding:13px 20px; font-size:.86rem; }
        .historico-detalhes { padding:0 20px 20px; }
        .historico-detalhes summary { cursor:pointer; color:var(--verde-esmeralda); font-weight:700; }
        .historico-caminhoes { display:grid; gap:10px; margin-top:14px; }
        .historico-caminhao { padding:14px; border:1px solid var(--bordar); border-left:3px solid var(--verde-esmeralda); border-radius:8px; }
        .historico-caminhao h3 { margin:0 0 7px; color:var(--texto-principal); font-size:.92rem; }
        .historico-caminhao p,.historico-caminhao ul { color:var(--texto-secundario); font-size:.84rem; }
        .historico-caminhao ul { margin:8px 0 0; padding-left:18px; }
        .historico-acoes { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        .sem-historico { padding:35px; text-align:center; border:1px dashed var(--bordar); border-radius:10px; color:var(--texto-secundario); }
        .modal-historico { display:none; position:fixed; inset:0; z-index:50; padding:28px; overflow:auto; background:rgba(2,8,23,.78); }
        .relatorio-historico { width:min(100%,820px); margin:0 auto; padding:34px; background:#fff; color:#1f2937; border-radius:8px; }
        .relatorio-historico h2 { color:#166534; }
        .relatorio-conteudo { padding-top: 24px; }
        .relatorio-bloco { margin-top: 22px; padding-top: 16px; border-top: 1px solid #d1d5db; }
        .relatorio-bloco h3 { margin: 0 0 8px; color: #166534; font-size: 17px; }
        .relatorio-bloco p { margin: 4px 0; }
        .relatorio-tabela { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .relatorio-tabela th, .relatorio-tabela td { padding: 8px; border: 1px solid #d1d5db; text-align: left; }
        .relatorio-tabela th { background: #ecfdf5; color: #166534; }
        .relatorio-total { margin-top: 24px; padding: 14px; text-align: right; background: #ecfdf5; color: #166534; font-size: 18px; font-weight: 800; }
        @media(max-width:680px){ .historico-filtros{grid-template-columns:1fr;} .historico-cabecalho{flex-direction:column;} .modal-historico{padding:12px;} .relatorio-historico{padding:20px;} }
    </style>
</head>
<body class="corpo-dashboard">
<div class="layout-erp"><main class="conteudo-principal">
    <section class="titulo-pagina-stack"><h1><i class="bi bi-clock-history"></i> Histórico de OS</h1><p>Consulte as ordens aprovadas e gere o relatório final.</p></section>
    <form class="historico-filtros" method="GET">
        <div><label for="busca">Buscar OS, empresa ou caminhão</label><input id="busca" name="busca" value="<?= hHistorico($busca) ?>" placeholder="Número, empresa, placa ou modelo"></div>
        <div><label for="empresa_id">Empresa</label><select id="empresa_id" name="empresa_id"><option value="0">Todas as empresas</option><?php foreach ($empresas as $empresa): ?><option value="<?= (int) $empresa['id'] ?>" <?= $empresaId === (int) $empresa['id'] ? 'selected' : '' ?>><?= hHistorico($empresa['nome']) ?></option><?php endforeach; ?></select></div>
        <button class="botao-acao" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    </form>
    <?php if (!$ordens): ?><div class="sem-historico"><i class="bi bi-inbox"></i><br>Nenhuma OS aprovada encontrada.</div><?php else: ?><div class="historico-lista">
    <?php foreach ($ordens as $os): $grupos = caminhoesHistorico($itensPorOS[$os['id']] ?? []); ?>
        <article class="historico-card">
            <div class="historico-cabecalho"><div><h2>OS #<?= (int) $os['id'] ?></h2><p><?= hHistorico($os['empresa_nome']) ?> · <?= hHistorico(dataHistorico($os['data_atualizacao'] ?: $os['data_criacao'])) ?></p></div><strong class="status-finalizada">Aprovada</strong></div>
            <div class="historico-resumo"><span><i class="bi bi-truck"></i> <?= count($grupos) ?> caminhão(ões)</span><span><i class="bi bi-cash-stack"></i> R$ <?= number_format((float) $os['valor_total'], 2, ',', '.') ?></span></div>
            <div class="historico-detalhes"><details><summary>Ver detalhes e gerar relatório</summary><?php if ($os['descricao_geral']): ?><p><?= hHistorico($os['descricao_geral']) ?></p><?php endif; ?><div class="historico-caminhoes"><?php foreach ($grupos as $indice => $grupo): $v = $grupo['veiculo']; ?><div class="historico-caminhao"><h3><i class="bi bi-truck-front"></i> Caminhão <?= $indice + 1 ?> · <?= hHistorico($v['placa'] ?: 'Sem placa') ?></h3><p><?= hHistorico($v['modelo'] ?: 'Modelo não informado') ?><?= $v['ano'] ? ' · ' . (int) $v['ano'] : '' ?><?= $v['cor'] ? ' · ' . hHistorico($v['cor']) : '' ?></p><?php if ($grupo['itens']): ?><ul><?php foreach ($grupo['itens'] as $item): ?><li><?= hHistorico($item['tipo'] === 'servico' ? 'Serviço' : 'Peça') ?>: <?= hHistorico($item['nome_servico'] ?: $item['nome_peca'] ?: $item['descricao']) ?> · Qtd. <?= (int) $item['quantidade'] ?> · R$ <?= number_format((float) $item['valor'], 2, ',', '.') ?></li><?php endforeach; ?></ul><?php endif; ?></div><?php endforeach; ?></div><div class="historico-acoes"><button type="button" class="botao-acao" onclick="abrirRelatorio(<?= (int) $os['id'] ?>)"><i class="bi bi-file-earmark-text"></i> Visualizar relatório completo</button></div></details></div>
        </article>
    <?php endforeach; ?></div><?php endif; ?>
</main></div>
<div id="modalHistorico" class="modal-historico"><div class="relatorio-historico" id="relatorioHistorico"><div style="display:flex;justify-content:space-between;border-bottom:3px solid #166534;padding-bottom:15px"><div><h2>Nexus HUB</h2><p>Relatório completo da Ordem de Serviço</p></div><div><button type="button" onclick="baixarPDF()" class="botao-acao"><i class="bi bi-download"></i> Baixar PDF</button><button type="button" onclick="fecharRelatorio()" class="botao-cancelar">Fechar</button></div></div><div id="conteudoRelatorio" class="relatorio-conteudo"></div></div></div>
<script src="../../assets/js/common.js"></script><script src="../../assets/js/pages.js"></script>
<script>
const relatorios = <?= json_encode(array_reduce($ordens, function ($resultado, $os) use ($itensPorOS) { $resultado[$os['id']] = ['id' => $os['id'], 'empresa' => $os['empresa_nome'], 'cnpj' => $os['cnpj'], 'email' => $os['email'], 'telefone' => $os['telefone'], 'contato' => $os['contato'], 'data' => dataHistorico($os['data_atualizacao'] ?: $os['data_criacao']), 'total' => (float) $os['valor_total'], 'descricao' => $os['descricao_geral'], 'itens' => $itensPorOS[$os['id']] ?? []]; return $resultado; }, []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function escaparRelatorio(valor) { const div = document.createElement('div'); div.textContent = valor == null ? '' : String(valor); return div.innerHTML; }
function agruparCaminhoes(itens) { const grupos = {}; (itens || []).forEach(item => { const chave = item.veiculo_id || item.placa || 'sem-veiculo'; if (!grupos[chave]) grupos[chave] = { veiculo: item, itens: [] }; if (item.tipo !== 'veiculo') grupos[chave].itens.push(item); }); return Object.values(grupos); }
function abrirRelatorio(id) {
    const os = relatorios[id];
    if (!os) return;
    const caminhoes = agruparCaminhoes(os.itens);
    const blocos = caminhoes.map((grupo, indice) => {
        const veiculo = grupo.veiculo;
        const itens = grupo.itens.length ? `<table class="relatorio-tabela"><thead><tr><th>Tipo</th><th>Descrição</th><th>Data</th><th>Qtd.</th><th>Valor unitário</th><th>Total</th></tr></thead><tbody>${grupo.itens.map(item => `<tr><td>${escaparRelatorio(item.tipo === 'servico' ? 'Serviço' : 'Peça')}</td><td>${escaparRelatorio(item.nome_servico || item.nome_peca || item.descricao || '-')}</td><td>${escaparRelatorio(item.data_execucao ? new Date(`${item.data_execucao}T00:00:00`).toLocaleDateString('pt-BR') : '-')}</td><td>${Number(item.quantidade || 1)}</td><td>R$ ${Number(item.valor_unitario || item.valor || 0).toFixed(2).replace('.', ',')}</td><td>R$ ${Number(item.valor || 0).toFixed(2).replace('.', ',')}</td></tr>`).join('')}</tbody></table>` : '<p>Nenhum serviço ou peça registrado.</p>';
        return `<section class="relatorio-bloco"><h3>Caminhão ${indice + 1} - ${escaparRelatorio(veiculo.placa || 'Sem placa')}</h3><p><strong>Modelo:</strong> ${escaparRelatorio(veiculo.modelo || '-')} &nbsp; <strong>Ano:</strong> ${escaparRelatorio(veiculo.ano || '-')} &nbsp; <strong>Cor:</strong> ${escaparRelatorio(veiculo.cor || '-')}</p>${itens}</section>`;
    }).join('');
    document.getElementById('conteudoRelatorio').innerHTML = `<div><p><strong>OS #${os.id}</strong> · <strong>Data:</strong> ${escaparRelatorio(os.data)}</p><h3>Empresa</h3><p><strong>Nome:</strong> ${escaparRelatorio(os.empresa)}<br><strong>CNPJ:</strong> ${escaparRelatorio(os.cnpj || '-')}<br><strong>Contato:</strong> ${escaparRelatorio(os.contato || '-')}<br><strong>Telefone:</strong> ${escaparRelatorio(os.telefone || '-')}<br><strong>E-mail:</strong> ${escaparRelatorio(os.email || '-')}</p>${os.descricao ? `<h3>Observações gerais</h3><p>${escaparRelatorio(os.descricao)}</p>` : ''}${blocos}<div class="relatorio-total">Total da OS: R$ ${Number(os.total || 0).toFixed(2).replace('.', ',')}</div></div>`;
    document.getElementById('modalHistorico').style.display = 'block';
}
function fecharRelatorio() { document.getElementById('modalHistorico').style.display = 'none'; }
async function baixarPDF() {
    const pagina = document.getElementById('relatorioHistorico');
    const botoes = pagina.querySelectorAll('button');
    botoes.forEach(botao => botao.style.visibility = 'hidden');
    try {
        const canvas = await html2canvas(pagina, { scale: 2, backgroundColor: '#ffffff', useCORS: true });
        const pdf = new jspdf.jsPDF('portrait', 'pt', 'a4');
        const largura = pdf.internal.pageSize.getWidth();
        const altura = pdf.internal.pageSize.getHeight();
        const proporcao = largura / canvas.width;
        const alturaPagina = altura / proporcao;
        let deslocamento = 0;
        while (deslocamento < canvas.height) {
            const recorte = document.createElement('canvas');
            recorte.width = canvas.width;
            recorte.height = Math.min(alturaPagina, canvas.height - deslocamento);
            recorte.getContext('2d').drawImage(canvas, 0, deslocamento, canvas.width, recorte.height, 0, 0, canvas.width, recorte.height);
            if (deslocamento > 0) pdf.addPage();
            pdf.addImage(recorte.toDataURL('image/png'), 'PNG', 0, 0, largura, recorte.height * proporcao);
            deslocamento += alturaPagina;
        }
        const osAtual = document.querySelector('#conteudoRelatorio strong')?.textContent.replace(/[^0-9]/g, '') || 'os';
        pdf.save(`relatorio-os-${osAtual}.pdf`);
    } finally { botoes.forEach(botao => botao.style.visibility = 'visible'); }
}
document.addEventListener('DOMContentLoaded', () => { document.querySelector('.layout-erp').insertAdjacentHTML('afterbegin', getSidebarHTML('historico')); document.querySelector('main').insertAdjacentHTML('afterbegin', getHeaderHTML()); });
</script>
</body></html>
