<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_SPECIAL_CHARS) ?: filter_input(INPUT_GET, 'acao', FILTER_SANITIZE_SPECIAL_CHARS);
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

/**
 * Gera múltiplos nomes aleatórios usando a API randomuser.me em uma única chamada.
 */
function gerarNomesAleatoriosMassa($genero, $pais, $quantidade) {
    $generoApi = ($genero === 'mulher') ? 'female' : 'male';
    $url = "https://randomuser.me/api/?gender={$generoApi}&nat={$pais}&results={$quantidade}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $json = curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($json, true);
    if (!$dados || !isset($dados['results']) || count($dados['results']) === 0) {
        return [];
    }

    $usuarios = [];
    foreach ($dados['results'] as $res) {
        $nome = $res['name']['first'];
        $sobrenome = $res['name']['last'];
        
        $nomeLimpo = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($nome));
        $sobrenomeLimpo = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($sobrenome));
        $username = preg_replace('/[^a-z0-9]/', '', $nomeLimpo) . '-' . preg_replace('/[^a-z0-9]/', '', $sobrenomeLimpo);
        
        $usuarios[] = ['nome' => $nome, 'sobrenome' => $sobrenome, 'username' => $username];
    }

    return $usuarios;
}

/**
 * Verifica o status de um app da Meta (Facebook Graph API)
 * Retorna um array ['status_conexao' => 'online'|'caiu', 'status' => 'analise'|'aprovado'|'rejeitado']
 */
function verificarAppStatusMeta($app_id, $app_secret = null) {
    $app_id = trim($app_id);
    if (empty($app_id)) {
        return ['status_conexao' => 'caiu', 'status' => 'rejeitado'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

    if (!empty($app_secret)) {
        // Chamada autenticada com App Access Token (APP_ID|APP_SECRET)
        $token = urlencode($app_id . '|' . trim($app_secret));
        $url = "https://graph.facebook.com/v19.0/" . urlencode($app_id) . "?fields=id,name,development_mode&access_token=" . $token;
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);
        curl_close($ch);

        $dados = json_decode($res, true);
        if ($dados && isset($dados['id'])) {
            $devMode = !empty($dados['development_mode']);
            return [
                'status_conexao' => 'online',
                'status' => $devMode ? 'analise' : 'aprovado'
            ];
        }
        return ['status_conexao' => 'caiu', 'status' => 'rejeitado'];
    } else {
        // Chamada pública para a Graph API
        $url = "https://graph.facebook.com/v19.0/" . urlencode($app_id);
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);
        curl_close($ch);

        $dados = json_decode($res, true);
        if ($dados && isset($dados['error'])) {
            $errorCode = $dados['error']['code'] ?? 0;
            // 104 = Access token required (indica que o app existe e está ativo)
            // 190 = Invalid access token
            if ($errorCode == 104 || $errorCode == 190) {
                // Raspagem secundária do diálogo OAuth do Facebook para ver se está em modo dev
                $oauthUrl = "https://www.facebook.com/v19.0/dialog/oauth?client_id=" . urlencode($app_id) . "&redirect_uri=https://www.facebook.com/connect/login_success.html";
                
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, $oauthUrl);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                $html = curl_exec($ch2);
                curl_close($ch2);

                if ($html !== false) {
                    $htmlLower = strtolower($html);
                    if (strpos($htmlLower, 'modo de desenvolvimento') !== false || 
                        strpos($htmlLower, 'development mode') !== false ||
                        strpos($htmlLower, 'app not active') !== false ||
                        strpos($htmlLower, 'aplicativo n&atilde;o ativo') !== false ||
                        strpos($htmlLower, 'aplicativo não ativo') !== false) {
                        
                        return ['status_conexao' => 'online', 'status' => 'analise'];
                    }
                }
                return ['status_conexao' => 'online', 'status' => 'aprovado'];
            }
            return ['status_conexao' => 'caiu', 'status' => 'rejeitado'];
        }
        
        if ($dados && isset($dados['id'])) {
            $devMode = !empty($dados['development_mode']);
            return [
                'status_conexao' => 'online',
                'status' => $devMode ? 'analise' : 'aprovado'
            ];
        }

        return ['status_conexao' => 'caiu', 'status' => 'rejeitado'];
    }
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

        // ---------------------------------------------------------------
        // Exportar usando o Import_template.xlsx como base (compatível com
        // ixBrowser). Copiamos o arquivo e injetamos as linhas de dados
        // diretamente no XML via ZipArchive, preservando todos os estilos
        // e metadados que o ixBrowser valida.
        // ---------------------------------------------------------------
        $templatePath = __DIR__ . '/excel exemplo/Import_template.xlsx';
        $tmpFile = tempnam(sys_get_temp_dir(), 'ixb_') . '.xlsx';
        copy($templatePath, $tmpFile);

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
            // Fallback para SimpleXLSXGen se ZipArchive falhar
            require_once 'SimpleXLSXGen.php';
            $planilha = [['Profile Title','Username','Password','2FA Key','Cookie','Proxy Method','Proxy ID','Country','Proxy Type','Proxy Info','Enable System Proxy','Profile Notes','Tag Management','Open The Specified URL','UA(User Agent)']];
            foreach ($contasExport as $c) {
                $planilha[] = [trim($c['nome'].' '.$c['sobrenome']).' #'.$c['id'], $c['email'], $c['senha'], $c['codigo_2fa']??'', $c['cookies']??'', '2', '', '', 'Noproxy', '', '1', '', '', '', ''];
            }
            $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($planilha);
            $xlsx->downloadAs('Export_Contas_'.date('Y-m-d_H-i').'.xlsx');
            exit;
        }

        // Ler o sharedStrings.xml atual do template
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');

        // Contar quantas <si> já existem no template (índices 0..N-1)
        $existingCount = substr_count($ssXml, '<si>');

        // Função auxiliar: escapa XML e constrói <si><t>...</t></si>
        // Strings com espaços no início/fim precisam do atributo xml:space="preserve"
        $buildSi = function(string $val): string {
            $escaped = htmlspecialchars($val, ENT_XML1, 'UTF-8');
            $attr = (strlen($val) > 0 && ($val[0] === ' ' || $val[strlen($val)-1] === ' ')) ? ' xml:space="preserve"' : '';
            return "<si><t{$attr}>{$escaped}</t></si>";
        };

        // Montar as novas strings a adicionar (dados das contas)
        $newStrings = [];
        $rowsXml    = '';
        $rowNum     = 4; // dados a partir da linha 4

        foreach ($contasExport as $c) {
            $nomePerfil = trim($c['nome'] . ' ' . $c['sobrenome']) . ' #' . $c['id'];
            $rowValues  = [
                $nomePerfil,
                $c['email'],
                $c['senha'],
                $c['codigo_2fa'] ?? '',
                $c['cookies']    ?? '',
                '2',       // Proxy Method: Custom
                '',        // Proxy ID
                '',        // Country
                'Noproxy', // Proxy Type
                '',        // Proxy Info
                '1',       // Enable System Proxy: Follow global
                '',        // Profile Notes
                '',        // Tag Management
                '',        // Open URL
                '',        // UA
            ];

            $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O'];
            $rowXml = "<row r=\"{$rowNum}\" spans=\"1:15\">";
            foreach ($rowValues as $i => $val) {
                $col = $cols[$i];
                $ref = $col . $rowNum;
                if ($val === '') {
                    // Célula vazia
                    $rowXml .= "<c r=\"{$ref}\"/>";
                } elseif (is_numeric($val) && strpos($val, '.') === false) {
                    // Número inteiro — sem shared string
                    $rowXml .= "<c r=\"{$ref}\"><v>{$val}</v></c>";
                } else {
                    // String — adiciona ao sharedStrings
                    // Verifica se já está na lista nova
                    $idx = array_search($val, $newStrings, true);
                    if ($idx === false) {
                        $newStrings[] = $val;
                        $idx = count($newStrings) - 1;
                    }
                    $ssIdx = $existingCount + $idx;
                    $rowXml .= "<c r=\"{$ref}\" t=\"s\"><v>{$ssIdx}</v></c>";
                }
            }
            $rowXml .= "</row>";
            $rowsXml .= $rowXml;
            $rowNum++;

            // Marcar como exportado
            $pdo->prepare("UPDATE contas SET status = 'exportado', data_exportado = NOW() WHERE id = ?")->execute([$c['id']]);
        }

        // Atualizar sharedStrings.xml — adicionar novas strings antes do </sst>
        if (!empty($newStrings)) {
            $totalCount = $existingCount + count($newStrings);
            $newSiBlocks = '';
            foreach ($newStrings as $val) {
                $newSiBlocks .= $buildSi($val);
            }
            // Atualizar count e uniqueCount no <sst>
            $ssXml = preg_replace('/count="\d+"/', 'count="' . $totalCount . '"', $ssXml);
            $ssXml = preg_replace('/uniqueCount="\d+"/', 'uniqueCount="' . $totalCount . '"', $ssXml);
            // Inserir antes do fechamento </sst>
            $ssXml = str_replace('</sst>', $newSiBlocks . '</sst>', $ssXml);
            $zip->addFromString('xl/sharedStrings.xml', $ssXml);
        }

        // Atualizar sheet1.xml — inserir linhas de dados e atualizar dimension
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        // Atualizar o atributo dimension (A1:O3 → A1:O{lastRow})
        $lastRow  = $rowNum - 1;
        $sheetXml = preg_replace('/ref="A1:O\d+"/', "ref=\"A1:O{$lastRow}\"", $sheetXml);

        // Inserir as novas linhas antes de </sheetData>
        $sheetXml = str_replace('</sheetData>', $rowsXml . '</sheetData>', $sheetXml);

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        // Servir o arquivo
        $filename = 'Export_Contas_' . date('Y-m-d_H-i') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . filesize($tmpFile));
        readfile($tmpFile);
        @unlink($tmpFile);
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
                elseif ($novo_status === 'exportado') $sql .= ", data_exportado = NOW()";
                $sql .= " WHERE id IN ($in)";
                
                $stmt = $pdo->prepare($sql);
                $params = array_merge([$novo_status], $idsArray);
                $stmt->execute($params);
            }
        }
        break;

    case 'del_massa':
        $ids = $_POST['ids'] ?? '';
        if (!empty($ids)) {
            $idsArray = array_filter(array_map('intval', explode(',', $ids)));
            if (count($idsArray) > 0) {
                $in = str_repeat('?,', count($idsArray) - 1) . '?';
                $pdo->prepare("DELETE FROM contas WHERE id IN ($in)")->execute($idsArray);
            }
        }
        break;

    case 'vincular_pessoa_massa':
        $ids = $_POST['ids'] ?? '';
        $pessoa_id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT) ?: null;
        if (!empty($ids)) {
            $idsArray = array_filter(array_map('intval', explode(',', $ids)));
            if (count($idsArray) > 0) {
                $in = str_repeat('?,', count($idsArray) - 1) . '?';
                if ($pessoa_id) {
                    $params = array_merge([$pessoa_id], $idsArray);
                    $pdo->prepare("UPDATE contas SET destinada_a = ?, data_vinculo = NOW() WHERE id IN ($in)")->execute($params);
                } else {
                    $pdo->prepare("UPDATE contas SET destinada_a = NULL, data_vinculo = NULL WHERE id IN ($in)")->execute($idsArray);
                }
            }
        }
        break;

    case 'regerar_massa':
        $ids = $_POST['ids'] ?? '';
        if (!empty($ids)) {
            $idsArray = array_filter(array_map('intval', explode(',', $ids)));
            foreach ($idsArray as $rid) {
                $stmt = $pdo->prepare("SELECT genero, pais FROM contas WHERE id = ?");
                $stmt->execute([$rid]);
                $conta = $stmt->fetch();
                if ($conta) {
                    $dados = gerarNomeAleatorio($conta['genero'], $conta['pais']);
                    if ($dados) {
                        $pdo->prepare("UPDATE contas SET nome = ?, sobrenome = ?, username = ? WHERE id = ?")
                            ->execute([$dados['nome'], $dados['sobrenome'], $dados['username'], $rid]);
                    }
                }
                usleep(150000); // 150ms entre requests para não sobrecarregar a API
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
            elseif ($novo_status === 'exportado') $sql .= ", data_exportado = NOW()";
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
        if ($qtd > 200) $qtd = 200; // Limite de segurança aumentado para 200

        // Obter todos os nomes de uma só vez para evitar rate limits
        $nomesEmLote = gerarNomesAleatoriosMassa($genero, $pais, $qtd);

        for ($i = 0; $i < $qtd; $i++) {
            $dados = $nomesEmLote[$i] ?? null;
            if (!$dados) {
                $dados = ['nome' => 'User', 'sobrenome' => rand(100, 999), 'username' => 'user-' . rand(1000, 9999)];
            }

            $emailValido = false;
            while (!$emailValido) {
                $email = $config['email_prefixo'] . $config['email_contador'] . $config['email_dominio'];

                // Verificação de pré-existência no banco de dados
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE email = ?");
                $stmtCheck->execute([$email]);
                $existe = $stmtCheck->fetchColumn() > 0;

                if ($existe) {
                    $config['email_contador']++;
                    $pdo->query("UPDATE configuracoes SET email_contador = email_contador + 1");
                    continue;
                }

                try {
                    $sql = "INSERT INTO contas (nome, sobrenome, username, email, senha, genero, pais, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";
                    $pdo->prepare($sql)->execute([$dados['nome'], $dados['sobrenome'], $dados['username'], $email, $config['senha_padrao'], $genero, $pais]);
                    
                    // Incrementa para o próximo e-mail de forma bem sucedida
                    $config['email_contador']++;
                    $pdo->query("UPDATE configuracoes SET email_contador = email_contador + 1");
                    $emailValido = true;
                } catch (PDOException $e) {
                    // Trata colisões concorrentes (Integrity constraint violation)
                    if ($e->getCode() == 23000) {
                        $config['email_contador']++;
                        $pdo->query("UPDATE configuracoes SET email_contador = email_contador + 1");
                    } else {
                        throw $e;
                    }
                }
            }
        }
        sincronizarSlackTracker($pdo);
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
        $sql = "UPDATE configuracoes SET senha_padrao = ?, email_contador = ?, genero_padrao = ?, pais_padrao = ?, email_prefixo = ?, email_dominio = ?, slack_token = ?, slack_canal_notificacao = ?, preco_perfil = ?, preco_bm = ?, preco_pagina = ?";
        $pdo->prepare($sql)->execute([
            $_POST['senha_padrao'], $_POST['email_contador'], $_POST['genero_padrao'], 
            $_POST['pais_padrao'], $_POST['email_prefixo'], $_POST['email_dominio'],
            $_POST['slack_token'], $_POST['slack_canal_notificacao'],
            $_POST['preco_perfil'], $_POST['preco_bm'], $_POST['preco_pagina']
        ]);
        break;

    case 'add_desconto':
        // Migração automática
        try { $pdo->query("SELECT id FROM descontos LIMIT 1"); } catch (Exception $e) {
            $pdo->query("CREATE TABLE IF NOT EXISTS `descontos` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `pessoa_id` int(11) NOT NULL,
                `mes` varchar(7) NOT NULL,
                `motivo` varchar(255) NOT NULL,
                `valor` decimal(10,2) NOT NULL,
                `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `pessoa_id` (`pessoa_id`),
                CONSTRAINT `descontos_ibfk_1` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoas` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $pessoa_id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT);
        $mes       = preg_replace('/[^0-9\-]/', '', $_POST['mes'] ?? '');
        $motivo    = trim($_POST['motivo'] ?? '');
        $valor     = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
        if ($pessoa_id && $mes && $motivo && $valor > 0) {
            $pdo->prepare("INSERT INTO descontos (pessoa_id, mes, motivo, valor) VALUES (?, ?, ?, ?)")
                ->execute([$pessoa_id, $mes, $motivo, $valor]);
        }
        break;

    case 'del_desconto':
        $id = filter_input(INPUT_POST, 'desconto_id', FILTER_VALIDATE_INT);
        if ($id) $pdo->prepare("DELETE FROM descontos WHERE id = ?")->execute([$id]);
        break;

    case 'toggle_nota':
        try { $pdo->query("SELECT excluir_nota FROM contas LIMIT 1"); } catch (Exception $e) {
            $pdo->query("ALTER TABLE contas ADD COLUMN excluir_nota TINYINT(1) NOT NULL DEFAULT 0");
        }
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("UPDATE contas SET excluir_nota = 1 - COALESCE(excluir_nota, 0) WHERE id = ?")->execute([$id]);
        }
        break;

    case 'criar_bm':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("UPDATE contas SET bm_criada = 1, data_bm_criada = NOW() WHERE id = ?")->execute([$id]);
            sincronizarSlackTracker($pdo);
        }
        break;

    case 'criar_pagina':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("UPDATE contas SET pagina_criada = 1, data_pagina_criada = NOW() WHERE id = ?")->execute([$id]);
        }
        break;

    case 'remover_bm':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("UPDATE contas SET bm_criada = 0, data_bm_criada = NULL WHERE id = ?")->execute([$id]);
            sincronizarSlackTracker($pdo);
        }
        break;

    case 'remover_pagina':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("UPDATE contas SET pagina_criada = 0, data_pagina_criada = NULL WHERE id = ?")->execute([$id]);
        }
        break;

    case 'regerar_todas_falhadas':
        $contasFalhadas = $pdo->query("SELECT id, genero, pais FROM contas WHERE nome = 'User'")->fetchAll();
        if (count($contasFalhadas) > 0) {
            foreach ($contasFalhadas as $conta) {
                $dados = gerarNomeAleatorio($conta['genero'], $conta['pais']);
                if ($dados) {
                    $pdo->prepare("UPDATE contas SET nome = ?, sobrenome = ?, username = ? WHERE id = ?")
                        ->execute([$dados['nome'], $dados['sobrenome'], $dados['username'], $conta['id']]);
                }
                usleep(150000); // delay de 150ms para evitar rate limit
            }
        }
        break;

    case 'editar_conta_completa':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $nome = trim($_POST['nome'] ?? '');
        $sobrenome = trim($_POST['sobrenome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $codigo_2fa = trim($_POST['codigo_2fa'] ?? '');
        
        if ($id) {
            $pdo->prepare("UPDATE contas SET nome = ?, sobrenome = ?, email = ?, username = ?, senha = ?, codigo_2fa = ? WHERE id = ?")
                ->execute([$nome, $sobrenome, $email, $username, $senha, $codigo_2fa, $id]);
        }
        break;

    case 'add_app':
        $nome = trim($_POST['nome'] ?? '');
        $app_id = trim($_POST['app_id'] ?? '');
        $app_secret = trim($_POST['app_secret'] ?? '') ?: null;
        $observacao = trim($_POST['observacao'] ?? '') ?: null;

        if (empty($nome) || empty($app_id)) {
            header("Location: apps.php?msg=erro_campos");
            exit;
        }

        // Verificar se app_id já existe
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM apps WHERE app_id = ?");
        $stmtCheck->execute([$app_id]);
        if ($stmtCheck->fetchColumn() > 0) {
            header("Location: apps.php?msg=erro_duplicado");
            exit;
        }

        // Validação automatizada
        $resultado = verificarAppStatusMeta($app_id, $app_secret);
        $status_conexao = $resultado['status_conexao'];
        $status = $resultado['status'];

        $stmt = $pdo->prepare("INSERT INTO apps (nome, app_id, app_secret, status, status_conexao, observacao, data_verificacao) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nome, $app_id, $app_secret, $status, $status_conexao, $observacao]);
        break;

    case 'edit_app':
        $id = filter_input(INPUT_POST, 'app_id_db', FILTER_VALIDATE_INT);
        $nome = trim($_POST['nome'] ?? '');
        $app_id = trim($_POST['app_id'] ?? '');
        $app_secret = trim($_POST['app_secret'] ?? '') ?: null;
        $observacao = trim($_POST['observacao'] ?? '') ?: null;

        if ($id && !empty($nome) && !empty($app_id)) {
            // Verificar duplicidade de app_id excluindo o próprio ID
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM apps WHERE app_id = ? AND id != ?");
            $stmtCheck->execute([$app_id, $id]);
            if ($stmtCheck->fetchColumn() > 0) {
                header("Location: apps.php?msg=erro_duplicado");
                exit;
            }

            // Validação automatizada
            $resultado = verificarAppStatusMeta($app_id, $app_secret);
            $status_conexao = $resultado['status_conexao'];
            $status = $resultado['status'];

            $stmt = $pdo->prepare("UPDATE apps SET nome = ?, app_id = ?, app_secret = ?, status = ?, status_conexao = ?, observacao = ?, data_verificacao = NOW() WHERE id = ?");
            $stmt->execute([$nome, $app_id, $app_secret, $status, $status_conexao, $observacao, $id]);
        }
        break;

    case 'del_app':
        $id = filter_input(INPUT_POST, 'app_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("DELETE FROM apps WHERE id = ?")->execute([$id]);
        }
        break;

    case 'verificar_app_status':
        $id = filter_input(INPUT_POST, 'app_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare("SELECT app_id, app_secret FROM apps WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if ($app) {
                $resultado = verificarAppStatusMeta($app['app_id'], $app['app_secret']);
                $status_conexao = $resultado['status_conexao'];
                $status = $resultado['status'];
                
                $stmtUp = $pdo->prepare("UPDATE apps SET status_conexao = ?, status = ?, data_verificacao = NOW() WHERE id = ?");
                $stmtUp->execute([$status_conexao, $status, $id]);

                // Se for requisição AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'sucesso' => true,
                        'status_conexao' => $status_conexao,
                        'status' => $status,
                        'data_verificacao' => date('d/m/Y H:i')
                    ]);
                    exit;
                }
            }
        }
        break;

    case 'mudar_app_status_direto':
        $id = filter_input(INPUT_POST, 'app_id', FILTER_VALIDATE_INT);
        $novo_status = filter_input(INPUT_POST, 'novo_status', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($id && in_array($novo_status, ['analise', 'aprovado', 'rejeitado'])) {
            $pdo->prepare("UPDATE apps SET status = ? WHERE id = ?")->execute([$novo_status, $id]);
        }
        break;

    case 'importar_apps_token':
        $token = trim($_POST['token'] ?? '');
        if (empty($token)) {
            header("Location: apps.php?msg=erro_token");
            exit;
        }

        // Buscar a lista de apps do usuário via Graph API
        $url = "https://graph.facebook.com/v19.0/me/applications?fields=id,name,development_mode&limit=100&access_token=" . urlencode($token);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        curl_close($ch);

        $dados = json_decode($res, true);
        if (!$dados || isset($dados['error'])) {
            $msgErro = $dados['error']['message'] ?? 'Erro desconhecido na API do Facebook.';
            header("Location: apps.php?msg=erro_api_facebook&detalhe=" . urlencode($msgErro));
            exit;
        }

        $appsImportados = $dados['data'] ?? [];
        $contadorNovos = 0;
        $contadorAtualizados = 0;

        foreach ($appsImportados as $appData) {
            $app_id = $appData['id'];
            $nome = $appData['name'];
            $devMode = !empty($appData['development_mode']);
            $status = $devMode ? 'analise' : 'aprovado';

            // Verificar se já existe
            $stmtCheck = $pdo->prepare("SELECT id FROM apps WHERE app_id = ?");
            $stmtCheck->execute([$app_id]);
            $appExistente = $stmtCheck->fetch();

            if ($appExistente) {
                // Atualiza status e nome
                $stmtUp = $pdo->prepare("UPDATE apps SET nome = ?, status = ?, status_conexao = 'online', data_verificacao = NOW() WHERE id = ?");
                $stmtUp->execute([$nome, $status, $appExistente['id']]);
                $contadorAtualizados++;
            } else {
                // Insere novo
                $stmtIn = $pdo->prepare("INSERT INTO apps (nome, app_id, status, status_conexao, data_verificacao) VALUES (?, ?, ?, 'online', NOW())");
                $stmtIn->execute([$nome, $app_id, $status]);
                $contadorNovos++;
            }
        }

        header("Location: apps.php?msg=importacao_sucesso&novos=" . $contadorNovos . "&atualizados=" . $contadorAtualizados);
        exit;
}

header("Location: " . $voltar_para . (strpos($voltar_para, '?') !== false ? '&' : '?') . "msg=ok");
exit;