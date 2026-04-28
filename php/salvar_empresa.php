<?php
// Define que a resposta será JSON
header("Content-Type: application/json");

// ========================================================
// 1. CHAMA A CONEXÃO (A mágica acontece aqui)
// Como este arquivo e o conexao.php estão na mesma pasta (php/), o caminho é direto.
require_once 'conexao.php';
// A partir desta linha, a variável $pdo já existe e está conectada!
// ========================================================

// 2. Recebe os dados do JavaScript
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados) {
    echo json_encode(["sucesso" => false, "erro" => "Nenhum dado recebido."]);
    exit;
}

try {
    // Repare que não tem mais criação de PDO aqui. Já vamos direto para a ação!
    $pdo->beginTransaction();

    // Insere a empresa
    $sqlEmpresa = "INSERT INTO empresas_cadastradas (nome, cnpj, site, contato, email, telefone, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtEmpresa = $pdo->prepare($sqlEmpresa);
    $stmtEmpresa->execute([
        $dados['nome'], $dados['cnpj'], $dados['site'], $dados['contato'], 
        $dados['email'], $dados['telefone'], $dados['observacoes']
    ]);

    $empresa_id = $pdo->lastInsertId();

    // Insere o histórico
    $historico = $dados['historico_atividades'][0];
    $sqlHistorico = "INSERT INTO historico_atividades_empresas (empresa_id, tipo, mensagem, data_registro, usuario) VALUES (?, ?, ?, NOW(), ?)";
    $stmtHistorico = $pdo->prepare($sqlHistorico);
    $stmtHistorico->execute([
        $empresa_id, $historico['tipo'], $historico['mensagem'], $historico['usuario']
    ]);

    $pdo->commit();
    echo json_encode(["sucesso" => true]);

} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    if ($e->getCode() == 23000) {
        echo json_encode(["sucesso" => false, "erro" => "Este CNPJ ou E-mail já está cadastrado."]);
    } else {
        echo json_encode(["sucesso" => false, "erro" => "Erro no banco de dados."]);
    }
}
?>