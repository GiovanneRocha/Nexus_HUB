<?php
header("Content-Type: application/json");
require_once 'conexao.php';

$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!isset($dados['empresa_id']) || empty($dados['mensagem'])) {
    echo json_encode(["sucesso" => false, "erro" => "Dados incompletos."]);
    exit;
}

try {
    $sql = "INSERT INTO historico_atividades_empresas (empresa_id, tipo, mensagem, data_registro, usuario) VALUES (?, 'manual', ?, NOW(), ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $dados['empresa_id'],
        $dados['mensagem'],
        $dados['usuario']
    ]);

    echo json_encode(["sucesso" => true]);

} catch(PDOException $e) {
    echo json_encode(["sucesso" => false, "erro" => "Erro ao adicionar nota."]);
}
?>