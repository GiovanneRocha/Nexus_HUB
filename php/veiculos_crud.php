<?php
require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = nexusDb();

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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT * FROM caminhoes ORDER BY id DESC');
    nexusJson(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nexusJson(['success' => false, 'message' => 'Método inválido.'], 405);
}

$action = $_POST['action'] ?? 'create';

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        nexusJson(['success' => false, 'message' => 'ID inválido.'], 400);
    }

    $stmt = $pdo->prepare('DELETE FROM caminhoes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    nexusJson(['success' => true, 'message' => 'Veículo removido com sucesso.']);
}

$empresaId = (int) ($_POST['empresa_id'] ?? 0);
$modelo = trim((string) ($_POST['modelo'] ?? ''));
$placa = trim(strtoupper((string) ($_POST['placa'] ?? '')));
$ano = (int) ($_POST['ano'] ?? 0);
$cor = trim((string) ($_POST['cor'] ?? ''));
$stmtEmpresa = $pdo->prepare('SELECT id, nome FROM empresas_cadastradas WHERE id = :id LIMIT 1');
$stmtEmpresa->execute([':id' => $empresaId]);
$empresa = $stmtEmpresa->fetch();
$nomeCaminhao = $empresa['nome'] ?? '';

if (!$empresa || $modelo === '' || $placa === '' || $ano < 1900 || $ano > (int) date('Y') || $cor === '') {
    nexusJson(['success' => false, 'message' => 'Empresa, modelo, placa, ano e cor são obrigatórios.'], 400);
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        nexusJson(['success' => false, 'message' => 'ID inválido para atualização.'], 400);
    }

    $stmt = $pdo->prepare('UPDATE caminhoes SET empresa_id = :empresa_id, nome_caminhao = :nome_caminhao, modelo = :modelo, placa = :placa, ano = :ano, cor = :cor WHERE id = :id');
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':nome_caminhao' => $nomeCaminhao,
        ':modelo' => $modelo,
        ':placa' => $placa,
        ':ano' => $ano,
        ':cor' => $cor,
        ':id' => $id,
    ]);

    nexusJson(['success' => true, 'message' => 'Veículo atualizado com sucesso.']);
}

$stmt = $pdo->prepare('INSERT INTO caminhoes (empresa_id, nome_caminhao, modelo, placa, ano, cor) VALUES (:empresa_id, :nome_caminhao, :modelo, :placa, :ano, :cor)');
$stmt->execute([
    ':empresa_id' => $empresaId,
    ':nome_caminhao' => $nomeCaminhao,
    ':modelo' => $modelo,
    ':placa' => $placa,
    ':ano' => $ano,
    ':cor' => $cor,
]);

nexusJson(['success' => true, 'message' => 'Veículo cadastrado com sucesso.']);
