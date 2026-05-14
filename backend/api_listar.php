<?php
header('Content-Type: application/json');
require_once '../conexao.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($token !== API_TOKEN) {
    echo json_encode(["sucesso" => false, "mensagem" => "Token inválido."]);
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM contas ORDER BY id DESC");
    $contas = $stmt->fetchAll();
    
    $stmtPessoas = $pdo->query("SELECT * FROM pessoas ORDER BY nome ASC");
    $pessoas = $stmtPessoas->fetchAll();

    echo json_encode(["sucesso" => true, "contas" => $contas, "pessoas" => $pessoas]);
} catch (Exception $e) {
    echo json_encode(["sucesso" => false, "mensagem" => "Erro ao buscar dados."]);
}
?>