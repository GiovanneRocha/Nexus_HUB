<?php
header("Content-Type: application/json");
require_once 'conexao.php';

$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!isset($dados['id'])) {
    echo json_encode(["sucesso" => false, "erro" => "ID não fornecido."]);
    exit;
}

try {
    $sql = "UPDATE empresas_cadastradas SET site = ?, contato = ?, telefone = ?, email = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $dados['site'],
        $dados['contato'],
        $dados['telefone'],
        $dados['email'],
        $dados['id']
    ]);

    echo json_encode(["sucesso" => true]);

} catch(PDOException $e) {
    echo json_encode(["sucesso" => false, "erro" => "Erro ao atualizar dados."]);
}
?>