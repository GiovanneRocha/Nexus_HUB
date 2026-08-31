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

$pdo->exec("CREATE TABLE IF NOT EXISTS empresas_cadastradas (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    cnpj VARCHAR(20) DEFAULT NULL,
    site VARCHAR(255) DEFAULT NULL,
    contato VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    telefone VARCHAR(50) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_REQUEST['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM empresas_cadastradas ORDER BY id DESC');
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

    $stmt = $pdo->prepare('DELETE FROM empresas_cadastradas WHERE id = :id');
    $stmt->execute([':id' => $id]);

    nexusJson(['success' => true, 'message' => 'Empresa removida com sucesso.']);
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$cnpj = trim((string) ($_POST['cnpj'] ?? ''));
$site = trim((string) ($_POST['site'] ?? ''));
$contato = trim((string) ($_POST['contato'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$observacoes = trim((string) ($_POST['observacoes'] ?? $_POST['obs'] ?? ''));

if ($nome === '' || $cnpj === '' || $email === '' || $telefone === '' || $contato === '') {
    nexusJson(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    nexusJson(['success' => false, 'message' => 'Informe um e-mail válido.'], 400);
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        nexusJson(['success' => false, 'message' => 'ID inválido para atualização.'], 400);
    }

    $stmt = $pdo->prepare('UPDATE empresas_cadastradas SET nome = :nome, cnpj = :cnpj, site = :site, contato = :contato, email = :email, telefone = :telefone, observacoes = :observacoes WHERE id = :id');
    $stmt->execute([
        ':nome' => $nome,
        ':cnpj' => $cnpj,
        ':site' => $site,
        ':contato' => $contato,
        ':email' => $email,
        ':telefone' => $telefone,
        ':observacoes' => $observacoes,
        ':id' => $id,
    ]);

    nexusJson(['success' => true, 'message' => 'Empresa atualizada com sucesso.']);
}

$stmt = $pdo->prepare('INSERT INTO empresas_cadastradas (nome, cnpj, site, contato, email, telefone, observacoes) VALUES (:nome, :cnpj, :site, :contato, :email, :telefone, :observacoes)');
$stmt->execute([
    ':nome' => $nome,
    ':cnpj' => $cnpj,
    ':site' => $site,
    ':contato' => $contato,
    ':email' => $email,
    ':telefone' => $telefone,
    ':observacoes' => $observacoes,
]);

nexusJson(['success' => true, 'message' => 'Empresa cadastrada com sucesso.']);
