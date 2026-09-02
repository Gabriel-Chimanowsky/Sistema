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
        if ($pessoa_id) {
            $stmtConf = $pdo->query("SELECT preco_perfil, preco_bm, preco_pagina FROM configuracoes LIMIT 1");
            $cfg = $stmtConf->fetch();
            $preco_perfil = (float)($cfg['preco_perfil'] ?? 20.00);
            $preco_bm = (float)($cfg['preco_bm'] ?? 30.00);
            $preco_pagina = (float)($cfg['preco_pagina'] ?? 10.00);

            $sql = "UPDATE contas SET 
                    destinada_a = ?, 
                    data_vinculo = IF(destinada_a IS NULL OR destinada_a != ?, NOW(), data_vinculo),
                    valor_perfil = COALESCE(valor_perfil, ?),
                    valor_bm = IF(bm_criada = 1, COALESCE(valor_bm, ?), valor_bm),
                    valor_pagina = IF(pagina_criada = 1, COALESCE(valor_pagina, ?), valor_pagina)
                    WHERE id = ?";
            $pdo->prepare($sql)->execute([$pessoa_id, $pessoa_id, $preco_perfil, $preco_bm, $preco_pagina, $id]);
        } else {
            $sql = "UPDATE contas SET destinada_a = NULL, data_vinculo = NULL, valor_perfil = NULL, valor_bm = NULL, valor_pagina = NULL WHERE id = ?";
            $pdo->prepare($sql)->execute([$id]);
        }
        
        require_once '../cloudflare_helper.php';
        sincronizarRedirecionamentoConta($id, $pdo);
    }

    echo json_encode(["sucesso" => true]);
} catch (Exception $e) {
    echo json_encode(["sucesso" => false, "mensagem" => "Erro ao atualizar: " . $e->getMessage()]);
}
?>