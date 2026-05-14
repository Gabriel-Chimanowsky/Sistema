<?php
header('Content-Type: application/json');
require_once '../conexao.php';

$token = $_POST['token'] ?? '';

if ($token !== API_TOKEN) {
    echo json_encode(["sucesso" => false, "mensagem" => "Token inválido."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["sucesso" => false, "mensagem" => "Método não permitido."]);
    exit;
}

try {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if (!$id || !$acao) {
        echo json_encode(["sucesso" => false, "mensagem" => "Dados inválidos."]);
        exit;
    }

    if ($acao === 'marcar_criada') {
        $sql = "UPDATE contas SET status = 'criada', data_criacao = NOW() WHERE id = ?";
        $pdo->prepare($sql)->execute([$id]);
    } elseif ($acao === 'marcar_autenticada') {
        $sql = "UPDATE contas SET status = 'autenticada', data_autenticacao = NOW() WHERE id = ?";
        $pdo->prepare($sql)->execute([$id]);
    } elseif ($acao === 'vincular_pessoa') {
        $pessoa_id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT) ?: null;
        $sql = "UPDATE contas SET destinada_a = ? WHERE id = ?";
        $pdo->prepare($sql)->execute([$pessoa_id, $id]);
    }

    echo json_encode(["sucesso" => true]);
} catch (Exception $e) {
    echo json_encode(["sucesso" => false, "mensagem" => "Erro ao atualizar: " . $e->getMessage()]);
}
?>