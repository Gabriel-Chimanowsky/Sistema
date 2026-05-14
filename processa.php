<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_SPECIAL_CHARS);
$voltar_para = $_SERVER['HTTP_REFERER'] ?? 'index.php';

/**
 * Gera um nome aleatório usando a API randomuser.me
 */
function gerarNomeAleatorio($genero, $pais) {
    $generoApi = ($genero === 'mulher') ? 'female' : 'male';
    $url = "https://randomuser.me/api/?gender={$generoApi}&nat={$pais}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $json = curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($json, true);
    if (!$dados || !isset($dados['results'][0])) {
        return null;
    }

    $nome = $dados['results'][0]['name']['first'];
    $sobrenome = $dados['results'][0]['name']['last'];
    
    // Gerar username amigável
    $nomeLimpo = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($nome));
    $sobrenomeLimpo = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($sobrenome));
    $username = preg_replace('/[^a-z0-9]/', '', $nomeLimpo) . '-' . preg_replace('/[^a-z0-9]/', '', $sobrenomeLimpo);

    return ['nome' => $nome, 'sobrenome' => $sobrenome, 'username' => $username];
}

// Lógica de ações
switch ($acao) {
    case 'exportar_csv':
        $ids = $_POST['ids'] ?? '';
        
        if (!empty($ids)) {
            $idsArray = array_filter(array_map('intval', explode(',', $ids)));
            $in = str_repeat('?,', count($idsArray) - 1) . '?';
            $stmt = $pdo->prepare("SELECT * FROM contas WHERE id IN ($in) ORDER BY id ASC");
            $stmt->execute($idsArray);
        } else {
            $stmt = $pdo->query("SELECT * FROM contas WHERE status != 'exportado' ORDER BY id ASC");
        }
        
        $contasExport = $stmt->fetchAll();

        require_once 'SimpleXLSXGen.php';

        $planilha = [];
        $planilha[] = ['Profile Title', 'Username', 'Password', '2FA Key', 'Cookie', 'Proxy Method', 'Proxy ID', 'Country', 'Proxy Type', 'Proxy Info', 'Enable System Proxy', 'Profile Notes', 'Tag Management', 'Open The Specified URL', 'UA(User Agent)'];
        $planilha[] = ['Please enter profile title', 'Open browser...', 'Open browser...', 'Fill in 2FA...', 'Cookies JSON', '1 or 2 or 3', 'Proxy ID', 'Country Code', 'Type:Noproxy...', 'Format:Host:Port...', '1:Global 2:Enable 3:Close', '', '0 or 1', 'URLs...', 'UA Info...'];
        $planilha[] = ["Note: Data from 4th line.", '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        foreach ($contasExport as $c) {
            $nomePerfil = trim($c['nome'] . ' ' . $c['sobrenome']) . ' #' . $c['id'];
            $planilha[] = [
                $nomePerfil, $c['email'], $c['senha'], $c['codigo_2fa'] ?? '', $c['cookies'] ?? '',
                '2', '', '', 'Noproxy', '', '1', '', '', '', ''
            ];
            
            // Marcar como exportado
            $pdo->prepare("UPDATE contas SET status = 'exportado' WHERE id = ?")->execute([$c['id']]);
        }

        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($planilha);
        $xlsx->downloadAs('Export_Contas_' . date('Y-m-d_H-i') . '.xlsx');
        exit;

    case 'mudar_status_massa':
        $ids = $_POST['ids'] ?? '';
        $novo_status = filter_input(INPUT_POST, 'novo_status', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if (!empty($ids) && in_array($novo_status, ['pendente', 'criada', 'autenticada', 'exportado'])) {
            $idsArray = array_filter(array_map('intval', explode(',', $ids)));
            if (count($idsArray) > 0) {
                $in = str_repeat('?,', count($idsArray) - 1) . '?';
                $sql = "UPDATE contas SET status = ?";
                if ($novo_status === 'criada') $sql .= ", data_criacao = NOW()";
                elseif ($novo_status === 'autenticada') $sql .= ", data_autenticacao = NOW()";
                $sql .= " WHERE id IN ($in)";
                
                $stmt = $pdo->prepare($sql);
                $params = array_merge([$novo_status], $idsArray);
                $stmt->execute($params);
            }
        }
        break;

    case 'mudar_status_direto':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $novo_status = filter_input(INPUT_POST, 'novo_status', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($id && in_array($novo_status, ['pendente', 'criada', 'autenticada', 'exportado'])) {
            $sql = "UPDATE contas SET status = ?";
            if ($novo_status === 'criada') $sql .= ", data_criacao = NOW()";
            elseif ($novo_status === 'autenticada') $sql .= ", data_autenticacao = NOW()";
            $sql .= " WHERE id = ?";
            $pdo->prepare($sql)->execute([$novo_status, $id]);
        }
        break;

    case 'ler_email_codigo':
        header('Content-Type: application/json');
        $conta_id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        
        if (!$conta_id) {
            echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT email FROM contas WHERE id = ?");
        $stmt->execute([$conta_id]);
        $conta = $stmt->fetch();

        if (!$conta) {
            echo json_encode(['sucesso' => false, 'erro' => 'Conta não encontrada.']);
            exit;
        }

        $emailAlvo = $conta['email'];
        global $emailHost, $emailUser, $emailPass; 
        
        // Tentar conectar ao IMAP
        $mbox = @imap_open("{{$emailHost}:993/imap/ssl}INBOX", $emailUser, $emailPass);
        if (!$mbox) {
            echo json_encode(['sucesso' => false, 'erro' => 'Erro de conexão IMAP: ' . imap_last_error()]);
            exit;
        }

        // Buscar emails para o endereço específico
        $emails = imap_search($mbox, 'TO "' . $emailAlvo . '"');

        if ($emails) {
            rsort($emails); // Mais recentes primeiro
            foreach ($emails as $msg_id) {
                $overview = imap_fetch_overview($mbox, $msg_id, 0);
                $body = imap_fetchbody($mbox, $msg_id, 1);
                
                // Regex para códigos de 6 dígitos
                if (preg_match('/\b\d{6}\b/', $body, $matches)) {
                    imap_close($mbox);
                    echo json_encode(['sucesso' => true, 'codigo' => $matches[0]]);
                    exit;
                }
            }
        }

        imap_close($mbox);
        echo json_encode(['sucesso' => false, 'erro' => 'Código não encontrado nos últimos e-mails.']);
        exit;

    case 'gerar_conta':
        $stmtConf = $pdo->query("SELECT * FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();

        $genero = $_POST['genero'] ?? $config['genero_padrao'];
        $pais = $_POST['pais'] ?? $config['pais_padrao'];
        $qtd = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT) ?: 1;
        if ($qtd > 50) $qtd = 50; // Limite de segurança

        for ($i = 0; $i < $qtd; $i++) {
            $dados = gerarNomeAleatorio($genero, $pais);
            if (!$dados) {
                $dados = ['nome' => 'User', 'sobrenome' => rand(100, 999), 'username' => 'user-' . rand(1000, 9999)];
            }

            $email = $config['email_prefixo'] . $config['email_contador'] . $config['email_dominio'];

            $sql = "INSERT INTO contas (nome, sobrenome, username, email, senha, genero, pais, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";
            $pdo->prepare($sql)->execute([$dados['nome'], $dados['sobrenome'], $dados['username'], $email, $config['senha_padrao'], $genero, $pais]);
            
            $pdo->query("UPDATE configuracoes SET email_contador = email_contador + 1");
            
            // Pequeno delay para não sobrecarregar se for muitos
            if ($qtd > 5) usleep(100000); 
        }
        break;

    case 'regerar_conta':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare("SELECT genero, pais FROM contas WHERE id = ?");
        $stmt->execute([$id]);
        $conta = $stmt->fetch();

        if ($conta) {
            $dados = gerarNomeAleatorio($conta['genero'], $conta['pais']);
            if ($dados) {
                $pdo->prepare("UPDATE contas SET nome = ?, sobrenome = ?, username = ? WHERE id = ?")
                    ->execute([$dados['nome'], $dados['sobrenome'], $dados['username'], $id]);
            }
        }
        break;

    case 'del_conta':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        if ($id) $pdo->prepare("DELETE FROM contas WHERE id = ?")->execute([$id]);
        break;

    case 'vincular_pessoa':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $pessoa_id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT) ?: null;
        if ($pessoa_id) {
            $pdo->prepare("UPDATE contas SET destinada_a = ?, data_vinculo = NOW() WHERE id = ?")->execute([$pessoa_id, $id]);
        } else {
            $pdo->prepare("UPDATE contas SET destinada_a = ?, data_vinculo = NULL WHERE id = ?")->execute([$pessoa_id, $id]);
        }
        break;

    case 'salvar_2fa':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $codigo = trim($_POST['codigo_2fa'] ?? '');
        $chave = trim($_POST['chave_2fa'] ?? '');
        if ($id && $codigo && $chave) {
            $pdo->prepare("UPDATE contas SET codigo_2fa = ?, chave_2fa = ? WHERE id = ?")->execute([$codigo, $chave, $id]);
        }
        break;

    case 'salvar_cookies':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $cookies = $_POST['cookies'] ?? '';
        if ($id) $pdo->prepare("UPDATE contas SET cookies = ? WHERE id = ?")->execute([$cookies, $id]);
        break;

    case 'add_pessoa':
        $nome = trim($_POST['nome_pessoa'] ?? '');
        if ($nome) $pdo->prepare("INSERT INTO pessoas (nome) VALUES (?)")->execute([$nome]);
        break;

    case 'del_pessoa':
        $id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT);
        if ($id) $pdo->prepare("DELETE FROM pessoas WHERE id = ?")->execute([$id]);
        break;

    case 'atualizar_config':
        $sql = "UPDATE configuracoes SET senha_padrao = ?, email_contador = ?, genero_padrao = ?, pais_padrao = ?, email_prefixo = ?, email_dominio = ?";
        $pdo->prepare($sql)->execute([
            $_POST['senha_padrao'], $_POST['email_contador'], $_POST['genero_padrao'], 
            $_POST['pais_padrao'], $_POST['email_prefixo'], $_POST['email_dominio']
        ]);
        break;
}

header("Location: " . $voltar_para . (strpos($voltar_para, '?') !== false ? '&' : '?') . "msg=ok");
exit;