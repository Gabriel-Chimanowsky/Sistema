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
    $stmtConf = $pdo->query("SELECT * FROM configuracoes LIMIT 1");
    $config = $stmtConf->fetch();

    $genero = filter_input(INPUT_POST, 'genero', FILTER_SANITIZE_SPECIAL_CHARS) ?: $config['genero_padrao'];
    $pais = filter_input(INPUT_POST, 'pais', FILTER_SANITIZE_SPECIAL_CHARS) ?: $config['pais_padrao'];
    
    $generoApi = ($genero === 'mulher') ? 'female' : 'male';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://randomuser.me/api/?gender={$generoApi}&nat={$pais}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $json = curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($json, true);
    
    if (!$dados || !isset($dados['results'][0])) {
        echo json_encode(["sucesso" => false, "mensagem" => "Erro ao conectar na API RandomUser."]);
        exit;
    }

    $nome = $dados['results'][0]['name']['first'];
    $sobrenome = $dados['results'][0]['name']['last'];

    $nomeLimpo = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($nome));
    $sobrenomeLimpo = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($sobrenome));
    $username = preg_replace('/[^a-z0-9]/', '', $nomeLimpo) . '-' . preg_replace('/[^a-z0-9]/', '', $sobrenomeLimpo);

    $email = $config['email_prefixo'] . $config['email_contador'] . $config['email_dominio'];
    $senhaPadrao = $config['senha_padrao'];

    $sql = "INSERT INTO contas (nome, sobrenome, username, email, senha, genero, pais, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nome, $sobrenome, $username, $email, $senhaPadrao, $genero, $pais]);

    $pdo->query("UPDATE configuracoes SET email_contador = email_contador + 1");

    echo json_encode([
        "sucesso" => true,
        "dados" => [
            "id" => $pdo->lastInsertId(),
            "username" => $username,
            "email" => $email,
            "senha" => $senhaPadrao,
            "status" => "pendente"
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno: " . $e->getMessage()]);
}
?>