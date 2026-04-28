<?php
// Define que a resposta será no formato JSON
header("Content-Type: application/json");

// Puxa a conexão limpa e centralizada
require_once 'conexao.php';

try {
    // Monta a query para buscar as empresas (ordenado pelas mais recentes primeiro)
    $sql = "SELECT id, nome, cnpj, contato, telefone, email FROM empresas_cadastradas ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // Extrai todos os resultados como um array associativo
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Devolve o sucesso e o array de empresas para o frontend
    echo json_encode([
        "sucesso" => true,
        "empresas" => $empresas
    ]);

} catch(PDOException $e) {
    // Em caso de erro, avisa o frontend
    echo json_encode([
        "sucesso" => false, 
        "erro" => "Erro ao consultar o banco de dados."
    ]);
}
?>