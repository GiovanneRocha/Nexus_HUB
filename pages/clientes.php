<?php
require_once __DIR__ . '/../php/db.php';

$pdo = nexusDb();
$busca = trim((string) ($_GET['busca'] ?? ''));
$where = '';
$params = [];
if ($busca !== '') {
    $where = 'WHERE empresas.nome LIKE :busca_nome OR empresas.cnpj LIKE :busca_cnpj OR empresas.contato LIKE :busca_contato OR empresas.email LIKE :busca_email';
    $termoBusca = '%' . $busca . '%';
    $params = ['busca_nome' => $termoBusca, 'busca_cnpj' => $termoBusca, 'busca_contato' => $termoBusca, 'busca_email' => $termoBusca];
}
$stmt = $pdo->prepare('SELECT empresas.*, (SELECT COUNT(*) FROM caminhoes WHERE caminhoes.empresa_id = empresas.id) AS total_caminhoes FROM empresas_cadastradas empresas ' . $where . ' ORDER BY empresas.nome ASC');
$stmt->execute($params);
$empresas = $stmt->fetchAll();
function hClientes($valor): string { return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Clientes - Nexus HUB</title>
<link rel="icon" type="image/png" href="../assets/images/icon-sistem.png"><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/pages.css"><link rel="stylesheet" href="../assets/css/admin-forms.css">
<style>
.clientes-toolbar{display:flex;justify-content:space-between;align-items:end;gap:14px;flex-wrap:wrap;margin:18px 0}.clientes-busca{display:flex;gap:8px;flex:1;max-width:620px}.clientes-busca input{width:100%;min-height:42px;padding:10px 12px;border:1px solid var(--bordar);border-radius:8px;background:var(--surface);color:var(--texto-principal)}.clientes-busca input:focus{outline:none;border-color:var(--verde-esmeralda);box-shadow:0 0 0 3px rgba(16,185,129,.12)}.clientes-resumo{margin-bottom:12px;color:var(--texto-secundario);font-size:.85rem}.empresa-celula{display:flex;align-items:center;gap:10px}.empresa-icone{display:grid;place-items:center;width:34px;height:34px;border-radius:8px;background:rgba(16,185,129,.12);color:var(--verde-esmeralda);flex:none}.clientes-tabela{overflow-x:auto}.clientes-tabela table{min-width:760px}.clientes-acoes{display:flex;gap:7px;flex-wrap:wrap}.clientes-vazio{text-align:center;padding:36px;color:var(--texto-secundario)}@media(max-width:680px){.clientes-toolbar{align-items:stretch}.clientes-busca{max-width:none;flex-direction:column}.clientes-toolbar>.botao-acao{width:100%}}
</style>
</head>
<body class="corpo-dashboard"><div class="layout-erp"><main class="conteudo-principal">
<section class="titulo-pagina-stack"><h1><i class="bi bi-people"></i> Gestão de Clientes</h1><p>Visualize e gerencie as empresas parceiras do sistema.</p></section>
<div class="clientes-toolbar"><form class="clientes-busca" method="GET"><input type="search" name="busca" value="<?= hClientes($busca) ?>" placeholder="Buscar por empresa, CNPJ, contato ou e-mail"><button class="botao-acao" type="submit"><i class="bi bi-search"></i> Buscar</button></form><a href="cadastros/cadastrar_empresa.php" class="botao-acao"><i class="bi bi-plus"></i> Nova Empresa</a></div>
<div class="clientes-resumo"><?= count($empresas) ?> empresa(s) encontrada(s)<?= $busca !== '' ? ' para a busca “' . hClientes($busca) . '”' : '' ?>.</div>
<div class="card-tabela clientes-tabela"><table class="tabela-dados"><thead><tr><th>EMPRESA</th><th>CNPJ</th><th>CONTATO</th><th>TELEFONE</th><th>E-MAIL</th><th>CAMINHÕES</th><th style="text-align:center">AÇÕES</th></tr></thead><tbody><?php if (!$empresas): ?><tr><td colspan="7" class="clientes-vazio"><i class="bi bi-inbox"></i><br>Nenhuma empresa cadastrada.</td></tr><?php else: foreach ($empresas as $empresa): ?><tr><td><div class="empresa-celula"><span class="empresa-icone"><i class="bi bi-building"></i></span><strong><?= hClientes($empresa['nome']) ?></strong></div></td><td><?= hClientes($empresa['cnpj'] ?: '-') ?></td><td><?= hClientes($empresa['contato'] ?: '-') ?></td><td><?= hClientes($empresa['telefone'] ?: '-') ?></td><td><?= hClientes($empresa['email'] ?: '-') ?></td><td><?= (int) $empresa['total_caminhoes'] ?></td><td><div class="clientes-acoes"><a href="perfil_empresa.php?id=<?= (int) $empresa['id'] ?>" class="botao-pequeno btn-pequeno-acoes"><i class="bi bi-eye"></i> Exibir</a><a href="cadastros/cadastrar_empresa.php?edit=<?= (int) $empresa['id'] ?>" class="botao-pequeno"><i class="bi bi-pencil"></i> Editar</a><a href="cadastros/cadastro_veiculo.php?empresa_id=<?= (int) $empresa['id'] ?>" class="botao-pequeno"><i class="bi bi-truck"></i> Frota</a></div></td></tr><?php endforeach; endif; ?></tbody></table></div>
</main></div><script src="../assets/js/common.js"></script><script src="../assets/js/pages.js"></script><script>document.addEventListener('DOMContentLoaded',()=>{document.querySelector('.layout-erp').insertAdjacentHTML('afterbegin',getSidebarHTML('clientes'));document.querySelector('main').insertAdjacentHTML('afterbegin',getHeaderHTML())})</script>
</body></html>
