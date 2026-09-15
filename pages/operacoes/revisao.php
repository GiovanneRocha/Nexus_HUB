<?php
require_once __DIR__ . '/../../php/db.php';

$pdo = nexusDb();
$statusFiltro = $_GET['status'] ?? 'pendente_revisao';
$busca = trim((string) ($_GET['busca'] ?? ''));
$empresaFiltro = (int) ($_GET['empresa_id'] ?? 0);
$statusPermitidos = ['pendente_revisao', 'finalizada', 'reprovada', 'todas'];
if (!in_array($statusFiltro, $statusPermitidos, true)) {
    $statusFiltro = 'pendente_revisao';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $osId = (int) ($_POST['os_id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    $novoStatus = $acao === 'aprovar' ? 'finalizada' : ($acao === 'reprovar' ? 'reprovada' : '');
    if ($osId > 0 && $novoStatus !== '') {
        $stmt = $pdo->prepare('UPDATE ordens_servico SET status = :status WHERE id = :id AND status = "pendente_revisao"');
        $stmt->execute(['status' => $novoStatus, 'id' => $osId]);
    }
    header('Location: revisao.php?' . http_build_query(['status' => $statusFiltro, 'busca' => $busca, 'empresa_id' => $empresaFiltro, 'atualizado' => 1]));
    exit;
}

$empresas = $pdo->query('SELECT id, nome FROM empresas_cadastradas ORDER BY nome ASC')->fetchAll();
$where = [];
$params = [];
if ($statusFiltro !== 'todas') {
    $where[] = 'os.status = :status';
    $params['status'] = $statusFiltro;
}
if ($empresaFiltro > 0) {
    $where[] = 'os.empresa_id = :empresa_id';
    $params['empresa_id'] = $empresaFiltro;
}
if ($busca !== '') {
    $where[] = '(CAST(os.id AS CHAR) LIKE :busca_id OR empresas_cadastradas.nome LIKE :busca_empresa OR EXISTS (SELECT 1 FROM itens_os busca_item WHERE busca_item.os_id = os.id AND (busca_item.placa LIKE :busca_placa OR busca_item.modelo LIKE :busca_modelo)))';
    $termoBusca = '%' . $busca . '%';
    $params['busca_id'] = $termoBusca;
    $params['busca_empresa'] = $termoBusca;
    $params['busca_placa'] = $termoBusca;
    $params['busca_modelo'] = $termoBusca;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmtOS = $pdo->prepare('SELECT os.id, os.data_criacao, os.status, os.valor_total, os.descricao_geral, empresas_cadastradas.nome AS empresa_nome FROM ordens_servico AS os INNER JOIN empresas_cadastradas ON empresas_cadastradas.id = os.empresa_id ' . $whereSql . ' ORDER BY os.data_criacao DESC, os.id DESC');
$stmtOS->execute($params);
$ordens = $stmtOS->fetchAll();

$itensPorOS = [];
if ($ordens) {
    $ids = array_map('intval', array_column($ordens, 'id'));
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtItens = $pdo->prepare('SELECT itens_os.*, cod_servicos.nome_servico, pecas.nome AS nome_peca FROM itens_os LEFT JOIN cod_servicos ON cod_servicos.id = itens_os.servico_id LEFT JOIN pecas ON pecas.id = itens_os.peca_id WHERE itens_os.os_id IN (' . $marcadores . ') ORDER BY itens_os.os_id, itens_os.id');
    $stmtItens->execute($ids);
    foreach ($stmtItens->fetchAll() as $item) {
        $itensPorOS[$item['os_id']][] = $item;
    }
}

function escaparRevisao($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function statusLabel($status): string
{
    $labels = ['pendente_revisao' => 'Em revisão', 'finalizada' => 'Aprovada', 'reprovada' => 'Reprovada'];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function dataRevisao($data): string
{
    return $data ? date('d/m/Y H:i', strtotime($data)) : '-';
}

function itensPorCaminhao($itens): array
{
    $caminhoes = [];
    foreach ($itens as $item) {
        $chave = $item['veiculo_id'] ?: ($item['placa'] ?: 'sem-veiculo');
        if (!isset($caminhoes[$chave])) {
            $caminhoes[$chave] = ['veiculo' => $item, 'itens' => []];
        }
        if ($item['tipo'] !== 'veiculo') {
            $caminhoes[$chave]['itens'][] = $item;
        }
    }
    return array_values($caminhoes);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisão de OS - Nexus HUB</title>
    <link rel="icon" type="image/png" href="../../assets/images/icon-sistem.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/pages.css">
    <style>
        .revisao-filtros { display: grid; grid-template-columns: minmax(220px, 1.5fr) minmax(180px, 1fr) auto; gap: 12px; align-items: end; margin: 20px 0; padding: 18px; background: var(--surface-strong); border: 1px solid var(--bordar); border-radius: 10px; }
        .revisao-filtros label { display: block; margin-bottom: 7px; color: var(--texto-secundario); font-size: .72rem; font-weight: 700; text-transform: uppercase; }
        .revisao-filtros input, .revisao-filtros select { width: 100%; min-height: 42px; padding: 9px 12px; border: 1px solid var(--bordar); border-radius: 8px; background: var(--surface); color: var(--texto-principal); }
        .revisao-filtros input:focus, .revisao-filtros select:focus { outline: none; border-color: var(--verde-esmeralda); box-shadow: 0 0 0 3px rgba(16, 185, 129, .12); }
        .abas-revisao { display: flex; gap: 8px; flex-wrap: wrap; margin: 18px 0; }
        .aba-revisao { display: inline-flex; align-items: center; gap: 7px; padding: 10px 14px; border: 1px solid var(--bordar); border-radius: 8px; color: var(--texto-secundario); text-decoration: none; font-weight: 700; }
        .aba-revisao:hover, .aba-revisao.ativa { border-color: var(--verde-esmeralda); color: var(--verde-esmeralda); background: rgba(16, 185, 129, .08); }
        .lista-os-revisao { display: grid; gap: 16px; }
        .card-os-revisao { overflow: hidden; border: 1px solid var(--bordar); border-radius: 10px; background: var(--surface-strong); box-shadow: var(--sombra-sm); }
        .cabecalho-os-revisao { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; padding: 18px 20px; border-bottom: 1px solid var(--bordar); }
        .cabecalho-os-revisao h2 { margin: 0 0 5px; color: var(--texto-principal); font-size: 1.1rem; }
        .cabecalho-os-revisao p { margin: 0; color: var(--texto-secundario); }
        .os-resumo { display: flex; gap: 18px; flex-wrap: wrap; padding: 13px 20px; color: var(--texto-secundario); font-size: .86rem; }
        .os-resumo strong { color: var(--texto-principal); }
        .status-revisao { display: inline-flex; padding: 6px 9px; border-radius: 6px; font-size: .72rem; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
        .status-revisao.pendente_revisao { background: rgba(245, 158, 11, .14); color: #f59e0b; }
        .status-revisao.finalizada { background: rgba(16, 185, 129, .14); color: var(--verde-esmeralda); }
        .status-revisao.reprovada { background: rgba(239, 68, 68, .14); color: var(--erro); }
        .detalhes-os-revisao { padding: 0 20px 20px; }
        .detalhes-os-revisao summary { cursor: pointer; color: var(--verde-esmeralda); font-weight: 700; }
        .blocos-caminhoes-revisao { display: grid; gap: 10px; margin-top: 14px; }
        .bloco-caminhao-revisao { padding: 14px; border: 1px solid var(--bordar); border-left: 3px solid var(--verde-esmeralda); border-radius: 8px; background: rgba(148, 163, 184, .04); }
        .bloco-caminhao-revisao h3 { margin: 0 0 9px; color: var(--texto-principal); font-size: .92rem; }
        .bloco-caminhao-revisao p { margin: 4px 0; color: var(--texto-secundario); font-size: .84rem; }
        .lista-itens-revisao { margin: 9px 0 0; padding-left: 18px; color: var(--texto-secundario); font-size: .84rem; }
        .acoes-revisao { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--bordar); }
        .acoes-revisao form { margin: 0; }
        .acoes-revisao button { min-height: 40px; padding: 0 14px; }
        .sem-resultados-revisao { padding: 34px 20px; text-align: center; color: var(--texto-secundario); border: 1px dashed var(--bordar); border-radius: 10px; }
        @media (max-width: 680px) { .revisao-filtros { grid-template-columns: 1fr; } .cabecalho-os-revisao { flex-direction: column; } .abas-revisao { display: grid; grid-template-columns: 1fr 1fr; } .aba-revisao { justify-content: center; } }
    </style>
</head>
<body class="corpo-dashboard">
    <div class="layout-erp">
        <main class="conteudo-principal">
            <section class="titulo-pagina-stack">
                <h1><i class="bi bi-check-square"></i> Revisão de Ordens de Serviço</h1>
                <p>Confira os dados relacionados antes de aprovar ou reprovar cada OS.</p>
            </section>
            <?php if (isset($_GET['atualizado'])): ?><div class="alerta sucesso"><i class="bi bi-check-circle"></i> Ação realizada com sucesso.</div><?php endif; ?>
            <div class="abas-revisao">
                <?php foreach (['pendente_revisao' => 'Em revisão', 'todas' => 'Todas as OS', 'finalizada' => 'Aprovadas', 'reprovada' => 'Reprovadas'] as $valor => $label): ?>
                    <a class="aba-revisao <?= $statusFiltro === $valor ? 'ativa' : '' ?>" href="?<?= http_build_query(['status' => $valor, 'busca' => $busca, 'empresa_id' => $empresaFiltro]) ?>"><i class="bi <?= $valor === 'pendente_revisao' ? 'bi-hourglass-split' : ($valor === 'todas' ? 'bi-list-ul' : ($valor === 'finalizada' ? 'bi-check-circle' : 'bi-x-circle')) ?>"></i> <?= escaparRevisao($label) ?></a>
                <?php endforeach; ?>
            </div>
            <form class="revisao-filtros" method="GET">
                <input type="hidden" name="status" value="<?= escaparRevisao($statusFiltro) ?>">
                <div><label for="busca">Buscar OS, empresa ou caminhão</label><input id="busca" name="busca" value="<?= escaparRevisao($busca) ?>" placeholder="Número, empresa, placa ou modelo"></div>
                <div><label for="empresa_id">Empresa</label><select id="empresa_id" name="empresa_id"><option value="0">Todas as empresas</option><?php foreach ($empresas as $empresa): ?><option value="<?= (int) $empresa['id'] ?>" <?= $empresaFiltro === (int) $empresa['id'] ? 'selected' : '' ?>><?= escaparRevisao($empresa['nome']) ?></option><?php endforeach; ?></select></div>
                <button type="submit" class="botao-acao"><i class="bi bi-funnel"></i> Filtrar</button>
            </form>
            <?php if (!$ordens): ?>
                <div class="sem-resultados-revisao"><i class="bi bi-inbox"></i><br>Nenhuma OS encontrada para os filtros selecionados.</div>
            <?php else: ?>
                <div class="lista-os-revisao">
                <?php foreach ($ordens as $os): $itens = $itensPorOS[$os['id']] ?? []; $caminhoes = itensPorCaminhao($itens); ?>
                    <article class="card-os-revisao">
                        <div class="cabecalho-os-revisao"><div><h2>OS #<?= (int) $os['id'] ?></h2><p><?= escaparRevisao($os['empresa_nome']) ?> · registrada em <?= escaparRevisao(dataRevisao($os['data_criacao'])) ?></p></div><span class="status-revisao <?= escaparRevisao($os['status']) ?>"><?= escaparRevisao(statusLabel($os['status'])) ?></span></div>
                        <div class="os-resumo"><span><i class="bi bi-truck"></i> <strong><?= count($caminhoes) ?></strong> caminhão(ões)</span><span><i class="bi bi-cash-stack"></i> <strong>R$ <?= number_format((float) $os['valor_total'], 2, ',', '.') ?></strong></span></div>
                        <div class="detalhes-os-revisao"><details><summary>Ver detalhes dos caminhões, serviços e peças</summary>
                            <?php if ($os['descricao_geral']): ?><p><?= escaparRevisao($os['descricao_geral']) ?></p><?php endif; ?>
                            <div class="blocos-caminhoes-revisao">
                            <?php foreach ($caminhoes as $indice => $caminhao): $veiculo = $caminhao['veiculo']; ?><div class="bloco-caminhao-revisao"><h3><i class="bi bi-truck-front"></i> Caminhão <?= $indice + 1 ?> · <?= escaparRevisao($veiculo['placa'] ?: 'Sem placa') ?></h3><p><?= escaparRevisao($veiculo['modelo'] ?: 'Modelo não informado') ?><?= $veiculo['ano'] ? ' · ' . (int) $veiculo['ano'] : '' ?><?= $veiculo['cor'] ? ' · ' . escaparRevisao($veiculo['cor']) : '' ?></p><?php if ($caminhao['itens']): ?><ul class="lista-itens-revisao"><?php foreach ($caminhao['itens'] as $item): ?><li><strong><?= escaparRevisao($item['tipo'] === 'servico' ? 'Serviço' : 'Peça') ?>:</strong> <?= escaparRevisao($item['nome_servico'] ?: $item['nome_peca'] ?: $item['descricao']) ?><?php if ((int) $item['quantidade'] > 1): ?> · Qtd. <?= (int) $item['quantidade'] ?><?php endif; ?> · R$ <?= number_format((float) $item['valor'], 2, ',', '.') ?></li><?php endforeach; ?></ul><?php else: ?><p>Nenhum serviço ou peça informado.</p><?php endif; ?></div><?php endforeach; ?>
                            </div>
                            <?php if ($os['status'] === 'pendente_revisao'): ?><div class="acoes-revisao"><form method="POST" onsubmit="return confirm('Aprovar esta OS?');"><input type="hidden" name="os_id" value="<?= (int) $os['id'] ?>"><input type="hidden" name="acao" value="aprovar"><button type="submit" class="botao-acao"><i class="bi bi-check-circle"></i> Aprovar OS</button></form><form method="POST" onsubmit="return confirm('Reprovar esta OS?');"><input type="hidden" name="os_id" value="<?= (int) $os['id'] ?>"><input type="hidden" name="acao" value="reprovar"><button type="submit" class="botao-cancelar"><i class="bi bi-x-circle"></i> Reprovar OS</button></form></div><?php endif; ?>
                        </details></div>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="../../assets/js/common.js"></script><script src="../../assets/js/pages.js"></script>
    <script>document.addEventListener('DOMContentLoaded', function () { document.querySelector('.layout-erp').insertAdjacentHTML('afterbegin', getSidebarHTML('revisao')); document.querySelector('main').insertAdjacentHTML('afterbegin', getHeaderHTML()); });</script>
</body>
</html>
