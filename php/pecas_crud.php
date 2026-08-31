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

$pdo->exec("CREATE TABLE IF NOT EXISTS pecas (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    categoria VARCHAR(100) DEFAULT NULL,
    marca VARCHAR(100) DEFAULT NULL,
    fornecedor VARCHAR(150) DEFAULT NULL,
    codigo VARCHAR(80) DEFAULT NULL,
    estoque INT DEFAULT 0,
    unidade VARCHAR(40) DEFAULT 'Unidade',
    valor DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'Ativa',
    imagem TEXT DEFAULT NULL,
    descricao TEXT DEFAULT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT * FROM pecas ORDER BY id DESC');
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

    $stmt = $pdo->prepare('DELETE FROM pecas WHERE id = :id');
    $stmt->execute([':id' => $id]);
    nexusJson(['success' => true, 'message' => 'Peça removida com sucesso.']);
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$categoria = trim((string) ($_POST['categoria'] ?? ''));
$marca = trim((string) ($_POST['marca'] ?? ''));
$fornecedor = trim((string) ($_POST['fornecedor'] ?? ''));
$codigo = trim((string) ($_POST['codigo'] ?? ''));
$estoque = (int) ($_POST['estoque'] ?? 0);
$unidade = trim((string) ($_POST['unidade'] ?? 'Unidade'));
$valor = (float) ($_POST['valor'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'Ativa'));
$imagem = trim((string) ($_POST['imagem'] ?? ''));
$descricao = trim((string) ($_POST['descricao'] ?? $_POST['desc'] ?? ''));

if ($nome === '' || $categoria === '' || $marca === '' || $fornecedor === '' || $codigo === '') {
    nexusJson(['success' => false, 'message' => 'Preencha todos os campos obrigatórios da peça.'], 400);
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        nexusJson(['success' => false, 'message' => 'ID inválido para atualização.'], 400);
    }

    $stmt = $pdo->prepare('UPDATE pecas SET nome = :nome, categoria = :categoria, marca = :marca, fornecedor = :fornecedor, codigo = :codigo, estoque = :estoque, unidade = :unidade, valor = :valor, status = :status, imagem = :imagem, descricao = :descricao WHERE id = :id');
    $stmt->execute([
        ':nome' => $nome,
        ':categoria' => $categoria,
        ':marca' => $marca,
        ':fornecedor' => $fornecedor,
        ':codigo' => $codigo,
        ':estoque' => $estoque,
        ':unidade' => $unidade,
        ':valor' => $valor,
        ':status' => $status,
        ':imagem' => $imagem,
        ':descricao' => $descricao,
        ':id' => $id,
    ]);

    nexusJson(['success' => true, 'message' => 'Peça atualizada com sucesso.']);
}

$stmt = $pdo->prepare('INSERT INTO pecas (nome, categoria, marca, fornecedor, codigo, estoque, unidade, valor, status, imagem, descricao) VALUES (:nome, :categoria, :marca, :fornecedor, :codigo, :estoque, :unidade, :valor, :status, :imagem, :descricao)');
$stmt->execute([
    ':nome' => $nome,
    ':categoria' => $categoria,
    ':marca' => $marca,
    ':fornecedor' => $fornecedor,
    ':codigo' => $codigo,
    ':estoque' => $estoque,
    ':unidade' => $unidade,
    ':valor' => $valor,
    ':status' => $status,
    ':imagem' => $imagem,
    ':descricao' => $descricao,
]);

nexusJson(['success' => true, 'message' => 'Peça cadastrada com sucesso.']);
