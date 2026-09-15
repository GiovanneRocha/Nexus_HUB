<?php
require_once __DIR__ . '/../../php/db.php';

$pdo = nexusDb();
$status = $_GET['status'] ?? 'todos';
$periodo = $_GET['periodo'] ?? 'mes_atual';
$statusPermitidos = ['todos', 'pendente_revisao', 'finalizada', 'reprovada'];
$periodosPermitidos = ['mes_atual', '30_dias', 'ano_atual', 'todos'];
if (!in_array($status, $statusPermitidos, true)) $status = 'todos';
if (!in_array($periodo, $periodosPermitidos, true)) $periodo = 'mes_atual';

$agora = new DateTimeImmutable('now');
$inicio = match ($periodo) {
    '30_dias' => $agora->modify('-30 days')->setTime(0, 0),
    'ano_atual' => $agora->setDate((int) $agora->format('Y'), 1, 1)->setTime(0, 0),
    'todos' => null,
    default => $agora->modify('first day of this month')->setTime(0, 0),
};
$fim = $agora->modify('+1 day')->setTime(0, 0);
$where = [];
$params = [];
if ($status !== 'todos') { $where[] = 'os.status = :status'; $params['status'] = $status; }
if ($inicio) { $where[] = 'os.data_criacao >= :inicio'; $params['inicio'] = $inicio->format('Y-m-d H:i:s'); $where[] = 'os.data_criacao < :fim'; $params['fim'] = $fim->format('Y-m-d H:i:s'); }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare('SELECT os.id, os.status, os.valor_total, os.data_criacao, empresas_cadastradas.nome AS empresa_nome FROM ordens_servico os INNER JOIN empresas_cadastradas ON empresas_cadastradas.id = os.empresa_id ' . $whereSql . ' ORDER BY os.data_criacao DESC');
$stmt->execute($params);
$ordens = $stmt->fetchAll();

$totalOS = count($ordens);
$pendentes = count(array_filter($ordens, static fn($os) => $os['status'] === 'pendente_revisao'));
$aprovadas = count(array_filter($ordens, static fn($os) => $os['status'] === 'finalizada'));
$reprovadas = count(array_filter($ordens, static fn($os) => $os['status'] === 'reprovada'));
$receita = array_sum(array_map(static fn($os) => $os['status'] === 'finalizada' ? (float) $os['valor_total'] : 0, $ordens));
$ticket = $aprovadas ? $receita / $aprovadas : 0;
$avaliadas = $aprovadas + $reprovadas;
$taxa = $avaliadas ? ($aprovadas / $avaliadas) * 100 : 0;
$totalEmpresas = (int) $pdo->query('SELECT COUNT(*) FROM empresas_cadastradas')->fetchColumn();
$totalCaminhoes = (int) $pdo->query('SELECT COUNT(*) FROM caminhoes')->fetchColumn();
$totalPecas = (int) $pdo->query('SELECT COUNT(*) FROM pecas')->fetchColumn();
$estoqueBaixo = (int) $pdo->query('SELECT COUNT(*) FROM pecas WHERE estoque <= 5 AND status = "Ativa"')->fetchColumn();

$meses = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = $agora->modify("first day of -{$i} months");
    $chave = $mes->format('Y-m');
    $meses[$chave] = ['label' => $mes->format('M/Y'), 'quantidade' => 0, 'receita' => 0];
}
$stmtMensal = $pdo->query('SELECT DATE_FORMAT(data_criacao, "%Y-%m") AS mes, COUNT(*) AS quantidade, COALESCE(SUM(CASE WHEN status = "finalizada" THEN valor_total ELSE 0 END), 0) AS receita FROM ordens_servico WHERE data_criacao >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(data_criacao, "%Y-%m") ORDER BY mes');
foreach ($stmtMensal->fetchAll() as $linha) if (isset($meses[$linha['mes']])) { $meses[$linha['mes']]['quantidade'] = (int) $linha['quantidade']; $meses[$linha['mes']]['receita'] = (float) $linha['receita']; }
$maxMensal = max(1, ...array_column($meses, 'quantidade'));

function hPainel($valor): string { return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); }
function moedaPainel($valor): string { return 'R$ ' . number_format((float) $valor, 2, ',', '.'); }
function dataPainel($valor): string { return $valor ? date('d/m/Y H:i', strtotime($valor)) : '-'; }
function statusPainel($valor): string { return ['pendente_revisao' => 'Em revisão', 'finalizada' => 'Aprovada', 'reprovada' => 'Reprovada'][$valor] ?? $valor; }
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Painel de Controle - Nexus HUB</title>
<link rel="icon" type="image/png" href="../../assets/images/icon-sistem.png"><link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/pages.css">
<style>
.painel-filtros-php{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;margin:18px 0;padding:18px;background:var(--surface);border:1px solid var(--bordar);border-radius:10px}.painel-filtros-php label{display:block;margin-bottom:7px;color:var(--texto-secundario);font-size:.72rem;font-weight:700;text-transform:uppercase}.painel-filtros-php select{width:100%;min-height:42px;padding:9px 12px;border:1px solid var(--bordar);border-radius:8px;background:var(--surface-light);color:var(--texto-principal)}.bi-grid-painel{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.bi-metrica{min-width:0;padding:18px;border:1px solid var(--bordar);border-radius:10px;background:var(--surface);box-shadow:var(--sombra-sm)}.bi-metrica p{margin:0;color:var(--texto-secundario);font-size:.76rem;font-weight:700;text-transform:uppercase}.bi-metrica h2{margin:10px 0 4px;color:var(--texto-principal);font-size:1.45rem;overflow-wrap:anywhere}.bi-metrica span{color:var(--texto-secundario);font-size:.78rem}.bi-grid-conteudo{display:grid;grid-template-columns:1.4fr .8fr;gap:16px;margin-top:16px}.bi-painel{padding:20px;border:1px solid var(--bordar);border-radius:10px;background:var(--surface)}.bi-painel h3{margin:0 0 5px;color:var(--texto-principal);font-size:1rem}.bi-painel>p{margin:0 0 18px;color:var(--texto-secundario);font-size:.82rem}.bi-barras{display:grid;grid-template-columns:repeat(6,1fr);align-items:end;gap:12px;height:190px}.bi-barra-coluna{display:flex;height:100%;flex-direction:column;justify-content:end;align-items:center;gap:7px}.bi-barra{width:min(42px,70%);min-height:4px;border-radius:5px 5px 0 0;background:linear-gradient(180deg,var(--verde-esmeralda-claro),var(--verde-escuro))}.bi-barra-coluna small{color:var(--texto-secundario);font-size:.7rem}.bi-status-lista{display:grid;gap:12px}.bi-status-linha{display:grid;grid-template-columns:100px 1fr 34px;gap:8px;align-items:center;color:var(--texto-secundario);font-size:.8rem}.bi-status-trilho{height:8px;border-radius:8px;background:var(--surface-light);overflow:hidden}.bi-status-fill{height:100%;background:var(--verde-esmeralda)}.bi-status-fill.reprovada{background:var(--erro)}.bi-status-fill.pendente_revisao{background:var(--aviso)}.bi-tabela{margin-top:16px;overflow-x:auto}.bi-tabela table{min-width:650px}.bi-alerta{color:var(--aviso)!important}.bi-acoes{display:flex;gap:10px;flex-wrap:wrap}.bi-acoes a{white-space:nowrap}@media(max-width:1100px){.bi-grid-painel{grid-template-columns:repeat(3,1fr)}}@media(max-width:760px){.painel-filtros-php,.bi-grid-conteudo{grid-template-columns:1fr}.bi-grid-painel{grid-template-columns:repeat(2,1fr)}}@media(max-width:480px){.bi-grid-painel{grid-template-columns:1fr}.bi-painel{padding:16px}.bi-barras{gap:6px}.bi-status-linha{grid-template-columns:82px 1fr 28px}}
@media(max-width:480px){.bi-grid-conteudo,.bi-grid-painel{min-width:0}.bi-painel,.bi-metrica{max-width:100%;min-width:0}.conteudo-principal{overflow-x:hidden}}
</style>
</head>
<body class="corpo-dashboard"><div class="layout-erp"><main class="conteudo-principal">
<section class="titulo-pagina-stack"><h1><i class="bi bi-speedometer2"></i> Painel de Controle</h1><p>Visão operacional das empresas, ordens de serviço, frota e estoque.</p></section>
<form class="painel-filtros-php" method="GET"><div><label for="status">Status das OS</label><select id="status" name="status"><option value="todos" <?= $status === 'todos' ? 'selected' : '' ?>>Todos os status</option><option value="pendente_revisao" <?= $status === 'pendente_revisao' ? 'selected' : '' ?>>Em revisão</option><option value="finalizada" <?= $status === 'finalizada' ? 'selected' : '' ?>>Aprovadas</option><option value="reprovada" <?= $status === 'reprovada' ? 'selected' : '' ?>>Reprovadas</option></select></div><div><label for="periodo">Período</label><select id="periodo" name="periodo"><option value="mes_atual" <?= $periodo === 'mes_atual' ? 'selected' : '' ?>>Mês atual</option><option value="30_dias" <?= $periodo === '30_dias' ? 'selected' : '' ?>>Últimos 30 dias</option><option value="ano_atual" <?= $periodo === 'ano_atual' ? 'selected' : '' ?>>Ano atual</option><option value="todos" <?= $periodo === 'todos' ? 'selected' : '' ?>>Todo período</option></select></div><button class="botao-acao" type="submit"><i class="bi bi-funnel"></i> Aplicar filtros</button></form>
<div class="bi-grid-painel"><div class="bi-metrica"><p><i class="bi bi-hourglass-split"></i> Em revisão</p><h2><?= $pendentes ?></h2><span>OS pendentes</span></div><div class="bi-metrica"><p><i class="bi bi-check-circle"></i> Aprovadas</p><h2><?= $aprovadas ?></h2><span>Dentro do filtro</span></div><div class="bi-metrica"><p><i class="bi bi-currency-dollar"></i> Receita</p><h2><?= moedaPainel($receita) ?></h2><span>OS aprovadas</span></div><div class="bi-metrica"><p><i class="bi bi-wallet2"></i> Ticket médio</p><h2><?= moedaPainel($ticket) ?></h2><span>Por OS aprovada</span></div><div class="bi-metrica"><p><i class="bi bi-building"></i> Empresas</p><h2><?= $totalEmpresas ?></h2><span><?= $totalCaminhoes ?> caminhões</span></div><div class="bi-metrica"><p><i class="bi bi-percent"></i> Aprovação</p><h2><?= number_format($taxa, 1, ',', '.') ?>%</h2><span><?= $reprovadas ?> reprovada(s)</span></div></div>
<div class="bi-grid-conteudo"><section class="bi-painel"><h3><i class="bi bi-bar-chart"></i> Volume de OS por mês</h3><p>Quantidade registrada nos últimos seis meses</p><div class="bi-barras"><?php foreach ($meses as $mes): ?><div class="bi-barra-coluna" title="<?= (int) $mes['quantidade'] ?> OS"><strong><?= (int) $mes['quantidade'] ?></strong><div class="bi-barra" style="height:<?= max(4, ($mes['quantidade'] / $maxMensal) * 145) ?>px"></div><small><?= hPainel($mes['label']) ?></small></div><?php endforeach; ?></div></section><section class="bi-painel"><h3><i class="bi bi-pie-chart"></i> Status da operação</h3><p>Distribuição no período selecionado</p><div class="bi-status-lista"><?php foreach (['finalizada' => ['Aprovadas', $aprovadas], 'pendente_revisao' => ['Em revisão', $pendentes], 'reprovada' => ['Reprovadas', $reprovadas]] as $chave => [$label, $quantidade]): ?><div class="bi-status-linha"><span><?= $label ?></span><div class="bi-status-trilho"><div class="bi-status-fill <?= $chave ?>" style="width:<?= $totalOS ? ($quantidade / $totalOS) * 100 : 0 ?>%"></div></div><strong><?= $quantidade ?></strong></div><?php endforeach; ?></div><p style="margin-top:22px">Estoque baixo: <strong class="bi-alerta"><?= $estoqueBaixo ?></strong> peça(s) com até 5 unidades.</p></section></div>
<section class="bi-painel bi-tabela"><div class="bi-acoes"><div><h3><i class="bi bi-clock-history"></i> Últimas ordens de serviço</h3><p>Registros mais recentes encontrados no banco.</p></div><a class="botao-secundario" href="historico.php">Ver histórico</a><a class="botao-secundario" href="revisao.php">Revisar OS</a></div><table class="tabela-historico"><thead><tr><th>OS</th><th>Empresa</th><th>Status</th><th>Data</th><th>Total</th></tr></thead><tbody><?php if (!$ordens): ?><tr><td colspan="5" class="sem-dados">Nenhuma OS encontrada para os filtros.</td></tr><?php else: foreach (array_slice($ordens, 0, 8) as $os): ?><tr><td><strong>#<?= (int) $os['id'] ?></strong></td><td><?= hPainel($os['empresa_nome']) ?></td><td><?= hPainel(statusPainel($os['status'])) ?></td><td><?= hPainel(dataPainel($os['data_criacao'])) ?></td><td><?= moedaPainel($os['valor_total']) ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
</main></div><script src="../../assets/js/common.js"></script><script src="../../assets/js/pages.js"></script><script>document.addEventListener('DOMContentLoaded',()=>{document.querySelector('.layout-erp').insertAdjacentHTML('afterbegin',getSidebarHTML('menu'));document.querySelector('main').insertAdjacentHTML('afterbegin',getHeaderHTML())})</script></body></html>
