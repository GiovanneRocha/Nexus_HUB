<?php
// Arquivo: php/conexao.php

$host = 'db_tolltech.mysql.dbaas.com.br';
$dbname = 'db_tolltech';
$user = 'db_tolltech';
$pass = 'vffY#ibW3Nao7T';

try {
    // Cria a conexão e guarda na variável global $pdo
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    
    // Configura o PDO para relatar erros (facilita muito achar bugs)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Se a conexão falhar, ele para tudo e avisa (em formato JSON para não quebrar o Fetch do JS)
    die(json_encode(["sucesso" => false, "erro" => "Falha na conexão com o banco de dados."]));
}
?>