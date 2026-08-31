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
    nome_caminhao VARCHAR(100) DEFAULT NULL,
    modelo VARCHAR(100) DEFAULT NULL,
    placa VARCHAR(10) NOT NULL,
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

$nomeCaminhao = trim((string) ($_POST['nome_caminhao'] ?? $_POST['empresa'] ?? ''));
$modelo = trim((string) ($_POST['modelo'] ?? ''));
$placa = trim(strtoupper((string) ($_POST['placa'] ?? '')));

if ($nomeCaminhao === '' || $modelo === '' || $placa === '') {
    nexusJson(['success' => false, 'message' => 'Empresa, modelo e placa são obrigatórios.'], 400);
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        nexusJson(['success' => false, 'message' => 'ID inválido para atualização.'], 400);
    }

    $stmt = $pdo->prepare('UPDATE caminhoes SET nome_caminhao = :nome_caminhao, modelo = :modelo, placa = :placa WHERE id = :id');
    $stmt->execute([
        ':nome_caminhao' => $nomeCaminhao,
        ':modelo' => $modelo,
        ':placa' => $placa,
        ':id' => $id,
    ]);

    nexusJson(['success' => true, 'message' => 'Veículo atualizado com sucesso.']);
}

$stmt = $pdo->prepare('INSERT INTO caminhoes (nome_caminhao, modelo, placa) VALUES (:nome_caminhao, :modelo, :placa)');
$stmt->execute([
    ':nome_caminhao' => $nomeCaminhao,
    ':modelo' => $modelo,
    ':placa' => $placa,
]);

nexusJson(['success' => true, 'message' => 'Veículo cadastrado com sucesso.']);
