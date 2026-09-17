<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/historico_helper.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = nexusDb();
nexusGarantirTabelaHistoricoEmpresas($pdo);

function historicoFormatado(array $item): array
{
    $timestamp = $item['data_registro'] ? strtotime($item['data_registro']) : false;
    return [
        'id' => (int) $item['id'],
        'tipo' => $item['tipo'],
        'mensagem' => $item['mensagem'],
        'usuario' => $item['usuario'],
        'data' => $timestamp ? date('d/m/Y', $timestamp) . ' às ' . date('H:i', $timestamp) : '-',
        'data_iso' => $item['data_registro'],
    ];
}

$action = $_REQUEST['action'] ?? 'listar';

if ($action === 'listar') {
    $empresaId = (int) ($_GET['empresa_id'] ?? 0);
    if ($empresaId <= 0) {
        nexusJson(['success' => false, 'message' => 'Empresa inválida.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM historico_atividades_empresas WHERE empresa_id = :empresa_id ORDER BY data_registro DESC, id DESC LIMIT 200');
    $stmt->execute([':empresa_id' => $empresaId]);

    nexusJson([
        'success' => true,
        'data' => array_map('historicoFormatado', $stmt->fetchAll()),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nexusJson(['success' => false, 'message' => 'Método inválido.'], 405);
}

if ($action === 'nota') {
    $empresaId = (int) ($_POST['empresa_id'] ?? 0);
    $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
    $usuario = nexusUsuarioAtual($_POST);

    if ($empresaId <= 0) {
        nexusJson(['success' => false, 'message' => 'Empresa inválida.'], 400);
    }
    if ($mensagem === '') {
        nexusJson(['success' => false, 'message' => 'Digite uma mensagem antes de enviar.'], 400);
    }
    if (mb_strlen($mensagem) > 1000) {
        nexusJson(['success' => false, 'message' => 'A mensagem pode ter no máximo 1000 caracteres.'], 400);
    }

    $stmtEmpresa = $pdo->prepare('SELECT id FROM empresas_cadastradas WHERE id = :id LIMIT 1');
    $stmtEmpresa->execute([':id' => $empresaId]);
    if (!$stmtEmpresa->fetch()) {
        nexusJson(['success' => false, 'message' => 'Empresa não encontrada.'], 404);
    }

    nexusRegistrarHistoricoEmpresa($pdo, $empresaId, 'manual', $mensagem, $usuario);

    nexusJson(['success' => true, 'message' => 'Nota adicionada com sucesso.']);
}

nexusJson(['success' => false, 'message' => 'Ação inválida.'], 400);
