<?php
header("Content-Type: application/json");
require_once 'conexao.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    echo json_encode(["sucesso" => false, "erro" => "ID inválido."]);
    exit;
}

try {
    // Busca os dados da empresa
    $stmtEmp = $pdo->prepare("SELECT * FROM empresas_cadastradas WHERE id = ?");
    $stmtEmp->execute([$id]);
    $empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        echo json_encode(["sucesso" => false, "erro" => "Empresa não encontrada."]);
        exit;
    }

    // Busca o histórico ordenado do mais recente para o mais antigo
    $stmtHist = $pdo->prepare("SELECT tipo, mensagem, usuario, DATE_FORMAT(data_registro, '%d/%m/%Y %H:%i') as data_formatada FROM historico_atividades_empresas WHERE empresa_id = ? ORDER BY id DESC");
    $stmtHist->execute([$id]);
    $historico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "sucesso" => true, 
        "empresa" => $empresa,
        "historico" => $historico
    ]);

} catch(PDOException $e) {
    echo json_encode(["sucesso" => false, "erro" => "Erro no banco de dados."]);
}
?>