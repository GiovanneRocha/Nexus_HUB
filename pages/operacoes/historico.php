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
$stmt = $pdo->prepare('SELECT os.id, os.data_criacao, os.data_atualizacao, os.valor_total, os.descricao_geral, os.empresa_id, os.nome_os, os.relatorio_dados, empresas_cadastradas.nome AS empresa_nome, empresas_cadastradas.cnpj, empresas_cadastradas.email, empresas_cadastradas.telefone, empresas_cadastradas.contato FROM ordens_servico AS os INNER JOIN empresas_cadastradas ON empresas_cadastradas.id = os.empresa_id WHERE ' . implode(' AND ', $where) . ' ORDER BY os.data_criacao DESC, os.id DESC');
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
    <title>OS Aprovadas - Nexus HUB</title>
    <link rel="icon" type="image/png" href="../../assets/images/icon-sistem.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/pages.css">
    <link rel="stylesheet" href="../../assets/css/relatorio-visualizador.css">
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
        .sem-historico { padding:35px; text-align:center; border:1px dashed var(--bordar); border-radius:10px; color:var(--texto-secundario); }
        .badge-status { display:inline-flex; align-items:center; gap:6px; padding:7px 16px; border-radius:999px; font-size:.8rem; font-weight:700; white-space:nowrap; height:fit-content; }
        .badge-aprovada { background:rgba(16,185,129,.15); color:#10b981; border:1px solid rgba(16,185,129,.35); }
        @media(max-width:680px){ .historico-filtros{grid-template-columns:1fr;} .historico-cabecalho{flex-direction:column;} }
    </style>
</head>
<body class="corpo-dashboard">
<div class="layout-erp"><main class="conteudo-principal">
    <section class="titulo-pagina-stack"><h1><i class="bi bi-clock-history"></i> OS Aprovadas</h1><p>Consulte as ordens aprovadas e gere o relatório final.</p></section>
    <form class="historico-filtros" method="GET">
        <div><label for="busca">Buscar OS, empresa ou caminhão</label><input id="busca" name="busca" value="<?= hHistorico($busca) ?>" placeholder="Número, empresa, placa ou modelo"></div>
        <div><label for="empresa_id">Empresa</label><select id="empresa_id" name="empresa_id"><option value="0">Todas as empresas</option><?php foreach ($empresas as $empresa): ?><option value="<?= (int) $empresa['id'] ?>" <?= $empresaId === (int) $empresa['id'] ? 'selected' : '' ?>><?= hHistorico($empresa['nome']) ?></option><?php endforeach; ?></select></div>
        <button class="botao-acao" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    </form>
    <?php if (!$ordens): ?><div class="sem-historico"><i class="bi bi-inbox"></i><br>Nenhuma OS aprovada encontrada.</div><?php else: ?><div class="historico-lista">
    <?php foreach ($ordens as $os): $grupos = caminhoesHistorico($itensPorOS[$os['id']] ?? []); ?>
        <article class="historico-card">
            <div class="historico-cabecalho"><div><h2>OS #<?= (int) $os['id'] ?><?= $os['nome_os'] ? ' · ' . hHistorico($os['nome_os']) : '' ?></h2><p><?= hHistorico($os['empresa_nome']) ?> · <?= hHistorico(dataHistorico($os['data_atualizacao'] ?: $os['data_criacao'])) ?></p></div><span class="badge-status badge-aprovada"><i class="bi bi-check-circle-fill"></i> Aprovada</span></div>
            <div class="historico-resumo"><span><i class="bi bi-truck"></i> <?= count($grupos) ?> caminhão(ões)</span><span><i class="bi bi-cash-stack"></i> R$ <?= number_format((float) $os['valor_total'], 2, ',', '.') ?></span></div>
            <div class="historico-detalhes"><details><summary>Ver detalhes e gerar relatório</summary><?php if ($os['descricao_geral']): ?><p><?= hHistorico($os['descricao_geral']) ?></p><?php endif; ?><div class="historico-caminhoes"><?php foreach ($grupos as $indice => $grupo): $v = $grupo['veiculo']; ?><div class="historico-caminhao"><h3><i class="bi bi-truck-front"></i> Caminhão <?= $indice + 1 ?> · <?= hHistorico($v['placa'] ?: 'Sem placa') ?></h3><p><?= hHistorico($v['modelo'] ?: 'Modelo não informado') ?><?= $v['ano'] ? ' · ' . (int) $v['ano'] : '' ?><?= $v['cor'] ? ' · ' . hHistorico($v['cor']) : '' ?></p><?php if ($grupo['itens']): ?><ul><?php foreach ($grupo['itens'] as $item): ?><li><?= hHistorico($item['tipo'] === 'servico' ? 'Serviço' : 'Peça') ?>: <?= hHistorico($item['nome_servico'] ?: $item['nome_peca'] ?: $item['descricao']) ?> · Qtd. <?= (int) $item['quantidade'] ?> · R$ <?= number_format((float) $item['valor'], 2, ',', '.') ?></li><?php endforeach; ?></ul><?php endif; ?></div><?php endforeach; ?></div><div class="historico-acoes"><button type="button" class="botao-acao" onclick="abrirRelatorio(<?= (int) $os['id'] ?>)"><i class="bi bi-file-earmark-text"></i> Visualizar relatório completo</button></div></details></div>
        </article>
    <?php endforeach; ?></div><?php endif; ?>
</main></div>
<script src="../../assets/js/relatorio-visualizador.js"></script>
<script src="../../assets/js/common.js"></script><script src="../../assets/js/pages.js"></script>
<script>
const relatorios = <?= json_encode(array_reduce($ordens, function ($resultado, $os) use ($itensPorOS) {
    $resultado[$os['id']] = [
        'id' => $os['id'],
        'nomeOS' => $os['nome_os'],
        'empresa' => $os['empresa_nome'],
        'cnpj' => $os['cnpj'],
        'email' => $os['email'],
        'telefone' => $os['telefone'],
        'contato' => $os['contato'],
        'data' => dataHistorico($os['data_atualizacao'] ?: $os['data_criacao']),
        'total' => (float) $os['valor_total'],
        'itens' => $itensPorOS[$os['id']] ?? [],
        'relatorioSalvo' => $os['relatorio_dados'] ? json_decode($os['relatorio_dados'], true) : null,
    ];
    return $resultado;
}, []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function abrirRelatorio(id) {
    const os = relatorios[id];
    if (!os) return;

    const grupos = agruparCaminhoesHistorico(os.itens);
    const caminhoes = grupos.map(grupo => {
        const v = grupo.veiculo;
        const itens = grupo.itens.map(item => ({
            tipo: item.tipo,
            descricao: item.nome_servico || item.nome_peca || item.descricao || (item.tipo === 'servico' ? 'Serviço' : 'Peça'),
            valor: Number(item.valor || 0),
            quantidade: Number(item.quantidade || 1)
        }));
        const valorTotal = itens.reduce((soma, item) => soma + item.valor, 0);
        const dataServico = grupo.itens.find(i => i.data_execucao)?.data_execucao;
        return {
            chave: v.placa || ('veiculo-' + (v.veiculo_id || Math.random())),
            modelo: v.modelo,
            placa: v.placa,
            dataServico: dataServico ? new Date(`${dataServico}T00:00:00`).toLocaleDateString('pt-BR') : '-',
            itens: itens,
            valorTotal: valorTotal
        };
    });

    NexusRelatorio.abrir({
        osId: os.id,
        nomeOS: os.nomeOS || '',
        logoUrl: '../../assets/images/icon-sistem.png',
        salvarUrl: '../../php/relatorio_crud.php',
        podeSalvar: true,
        empresa: { nome: os.empresa, cnpj: os.cnpj, contato: os.contato, email: os.email, telefone: os.telefone },
        dataEmissao: os.data,
        caminhoes: caminhoes,
        valorTotalGeral: os.total,
        relatorioSalvo: os.relatorioSalvo
    });
}

function agruparCaminhoesHistorico(itens) {
    const grupos = {};
    (itens || []).forEach(item => {
        const chave = item.veiculo_id || item.placa || 'sem-veiculo';
        if (!grupos[chave]) grupos[chave] = { veiculo: item, itens: [] };
        if (item.tipo !== 'veiculo') grupos[chave].itens.push(item);
    });
    return Object.values(grupos);
}

document.addEventListener('DOMContentLoaded', () => { document.querySelector('.layout-erp').insertAdjacentHTML('afterbegin', getSidebarHTML('historico')); document.querySelector('main').insertAdjacentHTML('afterbegin', getHeaderHTML()); });
</script>
</body></html>
