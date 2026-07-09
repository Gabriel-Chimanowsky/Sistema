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

                // Registrar tentativas no log imutável
                if ($novo_status === 'criada') {
                    $stmtLog = $pdo->prepare("INSERT INTO log_criacao_contas (conta_id) VALUES (?)");
                    foreach ($idsArray as $logId) {
                        $stmtLog->execute([$logId]);
                    }
                }
            }
        }
        break;

    case 'del_massa':
        $ids = $_POST['ids'] ?? '';
        if (!empty($ids)) {
            $idsArray = array_filter(array_map('intval', explode(',', $ids)));
            if (count($idsArray) > 0) {
                require_once 'cloudflare_helper.php';
                removerRedirecionamentoContasMassa($idsArray, $pdo);
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
                
                require_once 'cloudflare_helper.php';
                sincronizarRedirecionamentoContasMassa($idsArray, $pdo);
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
            // Registrar tentativa no log imutável
            if ($novo_status === 'criada') {
                $pdo->prepare("INSERT INTO log_criacao_contas (conta_id) VALUES (?)")->execute([$id]);
            }
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
        if ($id) {
            require_once 'cloudflare_helper.php';
            removerRedirecionamentoConta($id, $pdo);
            $pdo->prepare("DELETE FROM contas WHERE id = ?")->execute([$id]);
        }
        break;

    case 'vincular_pessoa':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $pessoa_id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT) ?: null;
        if ($pessoa_id) {
            $pdo->prepare("UPDATE contas SET destinada_a = ?, data_vinculo = NOW() WHERE id = ?")->execute([$pessoa_id, $id]);
        } else {
            $pdo->prepare("UPDATE contas SET destinada_a = NULL, data_vinculo = NULL WHERE id = ?")->execute([$id]);
        }
        require_once 'cloudflare_helper.php';
        sincronizarRedirecionamentoConta($id, $pdo);
        break;

    case 'salvar_2fa':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $codigo = trim($_POST['codigo_2fa'] ?? '');
        if ($id) {
            $stmt = $pdo->prepare("SELECT status FROM contas WHERE id = ?");
            $stmt->execute([$id]);
            $statusAtual = $stmt->fetchColumn();
            
            if ($codigo !== '' && $statusAtual !== 'exportado') {
                $pdo->prepare("UPDATE contas SET codigo_2fa = ?, status = 'autenticada', data_autenticacao = NOW() WHERE id = ?")->execute([$codigo, $id]);
            } else {
                $pdo->prepare("UPDATE contas SET codigo_2fa = ? WHERE id = ?")->execute([$codigo, $id]);
            }
        }
        break;

    case 'salvar_cookies':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $cookies = $_POST['cookies'] ?? '';
        if ($id) $pdo->prepare("UPDATE contas SET cookies = ? WHERE id = ?")->execute([$cookies, $id]);
        break;

    case 'add_pessoa':
        $nome = trim($_POST['nome_pessoa'] ?? '');
        $email = trim($_POST['email_pessoa'] ?? '') ?: null;
        if ($nome) {
            try {
                $pdo->prepare("INSERT INTO pessoas (nome, email) VALUES (?, ?)")->execute([$nome, $email]);
            } catch (PDOException $e) {
                // Se a coluna 'email' não existir, tenta criá-la e refaz o insert
                if ($e->getCode() == '42S22' || strpos($e->getMessage(), '1054') !== false) {
                    try {
                        $pdo->query("ALTER TABLE pessoas ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL");
                        $pdo->prepare("INSERT INTO pessoas (nome, email) VALUES (?, ?)")->execute([$nome, $email]);
                    } catch (Exception $e2) {
                        die("Erro ao adicionar cliente e criar coluna: " . $e2->getMessage());
                    }
                } else {
                    die("Erro ao adicionar cliente: " . $e->getMessage());
                }
            }
        }
        break;

    case 'del_pessoa':
        $id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT);
        if ($id) {
            // Buscar todas as contas destinadas a esta pessoa antes de deletar
            $stmtContas = $pdo->prepare("SELECT id FROM contas WHERE destinada_a = ?");
            $stmtContas->execute([$id]);
            $contasAfetadas = $stmtContas->fetchAll(PDO::FETCH_COLUMN);
            
            // Deletar a pessoa
            $pdo->prepare("DELETE FROM pessoas WHERE id = ?")->execute([$id]);
            
            // Sincronizar as contas afetadas no Cloudflare para remover as regras (em lote)
            if (count($contasAfetadas) > 0) {
                require_once 'cloudflare_helper.php';
                removerRedirecionamentoContasMassa($contasAfetadas, $pdo);
            }
        }
        break;

    case 'salvar_email_pessoa':
        $id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT);
        $email = trim($_POST['email'] ?? '') ?: null;
        if ($id) {
            // Obter e-mail anterior
            $stmtOld = $pdo->prepare("SELECT email FROM pessoas WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldEmail = $stmtOld->fetchColumn() ?: null;
            
            // Só sincroniza se o e-mail realmente mudou
            $emailMudou = (strtolower(trim($oldEmail ?? '')) !== strtolower(trim($email ?? '')));
            
            try {
                $pdo->prepare("UPDATE pessoas SET email = ? WHERE id = ?")->execute([$email, $id]);
            } catch (PDOException $e) {
                // Se a coluna 'email' não existir, tenta criá-la e refaz o update
                if ($e->getCode() == '42S22' || strpos($e->getMessage(), '1054') !== false) {
                    try {
                        $pdo->query("ALTER TABLE pessoas ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL");
                        $pdo->prepare("UPDATE pessoas SET email = ? WHERE id = ?")->execute([$email, $id]);
                    } catch (Exception $e2) {
                        die("Erro ao atualizar e-mail e criar coluna: " . $e2->getMessage());
                    }
                } else {
                    die("Erro ao atualizar e-mail: " . $e->getMessage());
                }
            }
            
            if ($emailMudou) {
                try {
                    require_once 'cloudflare_helper.php';
                    sincronizarRedirecionamentosPessoa($id, $pdo);
                } catch (Exception $e) {
                    // Sincronização de regras falhou ou Cloudflare indisponível, mas salvou o e-mail localmente.
                }
            }
        }
        break;

    case 'salvar_nota_conta':
        // Migração automática
        try { $pdo->query("SELECT nota_conta FROM contas LIMIT 1"); } catch (Exception $e) {
            $pdo->query("ALTER TABLE contas ADD COLUMN nota_conta TEXT DEFAULT NULL");
        }
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        $nota = trim($_POST['nota_conta'] ?? '');
        if ($id) {
            $pdo->prepare("UPDATE contas SET nota_conta = ? WHERE id = ?")->execute([$nota ?: null, $id]);
        }
        break;

    case 'salvar_comentario_pessoa':
        // Migração automática: adiciona coluna se ainda não existir
        try { $pdo->query("SELECT comentario FROM pessoas LIMIT 1"); } catch (Exception $e) {
            $pdo->query("ALTER TABLE pessoas ADD COLUMN comentario TEXT DEFAULT NULL");
        }
        $id = filter_input(INPUT_POST, 'pessoa_id', FILTER_VALIDATE_INT);
        $comentario = trim($_POST['comentario'] ?? '');
        if ($id) {
            $pdo->prepare("UPDATE pessoas SET comentario = ? WHERE id = ?")->execute([$comentario ?: null, $id]);
        }
        break;

    case 'atualizar_config':
        $sql = "UPDATE configuracoes SET senha_padrao = ?, email_contador = ?, genero_padrao = ?, pais_padrao = ?, email_prefixo = ?, email_dominio = ?, slack_token = ?, slack_canal_notificacao = ?, preco_perfil = ?, preco_bm = ?, preco_pagina = ?, cloudflare_token = ?, cloudflare_zone_id = ?, cloudflare_dest_email = ?";
        $pdo->prepare($sql)->execute([
            $_POST['senha_padrao'], $_POST['email_contador'], $_POST['genero_padrao'], 
            $_POST['pais_padrao'], $_POST['email_prefixo'], $_POST['email_dominio'],
            $_POST['slack_token'], $_POST['slack_canal_notificacao'],
            $_POST['preco_perfil'], $_POST['preco_bm'], $_POST['preco_pagina'],
            $_POST['cloudflare_token'], $_POST['cloudflare_zone_id'], $_POST['cloudflare_dest_email']
        ]);
        break;

    case 'salvar_cloudflare_config':
        header('Content-Type: application/json');
        $token = trim($_POST['cloudflare_token'] ?? '');
        $zone_id = trim($_POST['cloudflare_zone_id'] ?? '');
        $dest_email = trim($_POST['cloudflare_dest_email'] ?? '');
        
        try {
            $sql = "UPDATE configuracoes SET cloudflare_token = ?, cloudflare_zone_id = ?, cloudflare_dest_email = ?";
            $pdo->prepare($sql)->execute([$token, $zone_id, $dest_email]);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Credenciais salvas com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar credenciais: ' . $e->getMessage()]);
        }
        exit;

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

    case 'criar_dev':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("UPDATE contas SET dev_criada = 1, data_dev_criada = NOW() WHERE id = ?")->execute([$id]);
        }
        break;

    case 'remover_dev':
        $id = filter_input(INPUT_POST, 'conta_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("UPDATE contas SET dev_criada = 0, data_dev_criada = NULL WHERE id = ?")->execute([$id]);
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
            // Verificar se o e-mail já está cadastrado em outra conta
            if ($email !== '') {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE email = ? AND id != ?");
                $stmtCheck->execute([$email, $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    header("Location: " . $voltar_para . (strpos($voltar_para, '?') !== false ? '&' : '?') . "msg=erro_email_duplicado");
                    exit;
                }
            }

            $stmt = $pdo->prepare("SELECT status FROM contas WHERE id = ?");
            $stmt->execute([$id]);
            $statusAtual = $stmt->fetchColumn();
            
            try {
                if ($codigo_2fa !== '' && $statusAtual !== 'exportado') {
                    $pdo->prepare("UPDATE contas SET nome = ?, sobrenome = ?, email = ?, username = ?, senha = ?, codigo_2fa = ?, status = 'autenticada', data_autenticacao = NOW() WHERE id = ?")
                        ->execute([$nome, $sobrenome, $email, $username, $senha, $codigo_2fa, $id]);
                } else {
                    $pdo->prepare("UPDATE contas SET nome = ?, sobrenome = ?, email = ?, username = ?, senha = ?, codigo_2fa = ? WHERE id = ?")
                        ->execute([$nome, $sobrenome, $email, $username, $senha, $codigo_2fa, $id]);
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                    header("Location: " . $voltar_para . (strpos($voltar_para, '?') !== false ? '&' : '?') . "msg=erro_email_duplicado");
                    exit;
                } else {
                    throw $e;
                }
            }
        }
        break;

    case 'add_app':
        $nome = trim($_POST['nome'] ?? '');
        $app_id = trim($_POST['app_id'] ?? '');
        $app_secret = trim($_POST['app_secret'] ?? '') ?: null;
        $observacao = trim($_POST['observacao'] ?? '') ?: null;
        $permissions = trim($_POST['permissions'] ?? '') ?: null;
        $user_access_token = trim($_POST['user_access_token'] ?? '') ?: null;

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
        $resultado = verificarAppStatusMeta($app_id, $app_secret, $user_access_token, $permissions);
        $status_conexao = $resultado['status_conexao'];
        $status = $resultado['status'];
        $permissions_status = json_encode($resultado['permissions_status']);
        $obs = $observacao;
        if ($resultado['observacao_adicional']) {
            $obs = trim(($observacao ? $observacao . "\n" : "") . $resultado['observacao_adicional']);
        }

        $stmt = $pdo->prepare("INSERT INTO apps (nome, app_id, app_secret, status, status_conexao, permissions, permissions_status, user_access_token, observacao, data_verificacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nome, $app_id, $app_secret, $status, $status_conexao, $permissions, $permissions_status, $user_access_token, $obs]);
        break;

    case 'edit_app':
        $id = filter_input(INPUT_POST, 'app_id_db', FILTER_VALIDATE_INT);
        $nome = trim($_POST['nome'] ?? '');
        $app_id = trim($_POST['app_id'] ?? '');
        $app_secret = trim($_POST['app_secret'] ?? '') ?: null;
        $observacao = trim($_POST['observacao'] ?? '') ?: null;
        $permissions = trim($_POST['permissions'] ?? '') ?: null;
        $user_access_token = trim($_POST['user_access_token'] ?? '') ?: null;

        if ($id && !empty($nome) && !empty($app_id)) {
            // Verificar duplicidade de app_id excluindo o próprio ID
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM apps WHERE app_id = ? AND id != ?");
            $stmtCheck->execute([$app_id, $id]);
            if ($stmtCheck->fetchColumn() > 0) {
                header("Location: apps.php?msg=erro_duplicado");
                exit;
            }

            // Validação automatizada
            $resultado = verificarAppStatusMeta($app_id, $app_secret, $user_access_token, $permissions);
            $status_conexao = $resultado['status_conexao'];
            $status = $resultado['status'];
            $permissions_status = json_encode($resultado['permissions_status']);
            $obs = $observacao;
            if ($resultado['observacao_adicional']) {
                $obs = trim(($observacao ? $observacao . "\n" : "") . $resultado['observacao_adicional']);
            }

            $stmt = $pdo->prepare("UPDATE apps SET nome = ?, app_id = ?, app_secret = ?, status = ?, status_conexao = ?, permissions = ?, permissions_status = ?, user_access_token = ?, observacao = ?, data_verificacao = NOW() WHERE id = ?");
            $stmt->execute([$nome, $app_id, $app_secret, $status, $status_conexao, $permissions, $permissions_status, $user_access_token, $obs, $id]);
        }
        break;

    case 'del_app':
        $id = filter_input(INPUT_POST, 'app_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("DELETE FROM apps WHERE id = ?")->execute([$id]);
        }
        break;

    case 'del_apps_massa':
        $ids = $_POST['ids'] ?? '';
        if (!empty($ids)) {
            $idsArray = array_filter(array_map('intval', explode(',', $ids)));
            if (count($idsArray) > 0) {
                $in = str_repeat('?,', count($idsArray) - 1) . '?';
                $pdo->prepare("DELETE FROM apps WHERE id IN ($in)")->execute($idsArray);
            }
        }
        break;

    case 'verificar_app_status':
        header('Content-Type: application/json');
        $id = filter_input(INPUT_POST, 'app_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare("SELECT app_id, app_secret, user_access_token, permissions, observacao FROM apps WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if ($app) {
                $resultado = verificarAppStatusMeta($app['app_id'], $app['app_secret'], $app['user_access_token'], $app['permissions']);
                $status_conexao = $resultado['status_conexao'];
                $status = $resultado['status'];
                $permissions_status = json_encode($resultado['permissions_status']);
                $obs = $app['observacao'];
                if ($resultado['observacao_adicional']) {
                    $obs = trim(($app['observacao'] ? $app['observacao'] . "\n" : "") . $resultado['observacao_adicional']);
                }
                
                $stmtUp = $pdo->prepare("UPDATE apps SET status_conexao = ?, status = ?, permissions_status = ?, observacao = ?, data_verificacao = NOW() WHERE id = ?");
                $stmtUp->execute([$status_conexao, $status, $permissions_status, $obs, $id]);

                echo json_encode([
                    'sucesso' => true,
                    'status_conexao' => $status_conexao,
                    'status' => $status,
                    'data_verificacao' => date('d/m/Y H:i'),
                    'permissions' => $app['permissions'] ?? '',
                    'permissions_status' => $permissions_status
                ]);
                exit;
            }
        }
        echo json_encode(['sucesso' => false, 'erro' => 'Aplicativo não encontrado.']);
        exit;

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

        $appsEncontrados = [];

        // Helper para fazer requisição GET na Graph API do Facebook de forma padronizada
        $fbGet = function($endpoint, $token) {
            $url = "https://graph.facebook.com/v19.0/" . ltrim($endpoint, '/') . (strpos($endpoint, '?') !== false ? '&' : '?') . "access_token=" . urlencode($token);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $res = curl_exec($ch);
            curl_close($ch);
            return json_decode($res, true);
        };

        // 1. Buscar de /me/assigned_applications (apps do desenvolvedor)
        $resAssigned = $fbGet("me/assigned_applications?fields=id,name,development_mode&limit=100", $token);
        if ($resAssigned && isset($resAssigned['data'])) {
            foreach ($resAssigned['data'] as $app) {
                if (isset($app['id'])) {
                    $appsEncontrados[$app['id']] = $app;
                }
            }
        }

        // 2. Buscar Contas de Negócios (Business Manager) e depois seus respectivos apps vinculados
        $resBiz = $fbGet("me/businesses?limit=100", $token);
        if ($resBiz && isset($resBiz['data'])) {
            foreach ($resBiz['data'] as $biz) {
                $bizId = $biz['id'] ?? null;
                if (!$bizId) continue;

                // Apps de propriedade do Business
                $resOwned = $fbGet("{$bizId}/owned_apps?fields=id,name,development_mode&limit=100", $token);
                if ($resOwned && isset($resOwned['data'])) {
                    foreach ($resOwned['data'] as $app) {
                        if (isset($app['id'])) {
                            $appsEncontrados[$app['id']] = $app;
                        }
                    }
                }

                // Apps de clientes compartilhados com o Business
                $resClient = $fbGet("{$bizId}/client_apps?fields=id,name,development_mode&limit=100", $token);
                if ($resClient && isset($resClient['data'])) {
                    foreach ($resClient['data'] as $app) {
                        if (isset($app['id'])) {
                            $appsEncontrados[$app['id']] = $app;
                        }
                    }
                }
            }
        }

        // 3. Fallback final para /me/applications (antigo) se não encontrou nada nas buscas anteriores
        if (empty($appsEncontrados)) {
            $resAppsLegacy = $fbGet("me/applications?fields=id,name,development_mode&limit=100", $token);
            if ($resAppsLegacy && isset($resAppsLegacy['data'])) {
                foreach ($resAppsLegacy['data'] as $app) {
                    if (isset($app['id'])) {
                        $appsEncontrados[$app['id']] = $app;
                    }
                }
            }
        }

        // Se mesmo após todas as varreduras o array continuar vazio, exibe dica amigável
        if (empty($appsEncontrados)) {
            $msgErro = "Nenhum aplicativo pôde ser encontrado nas APIs de desenvolvedor. Verifique se o token de acesso possui as permissões necessárias (ex: 'business_management', 'manage_app_solution') ou cadastre seus aplicativos manualmente no botão 'Adicionar Aplicativo'.";
            header("Location: apps.php?msg=erro_api_facebook&detalhe=" . urlencode($msgErro));
            exit;
        }

        $appsImportados = array_values($appsEncontrados);
        $contadorNovos = 0;
        $contadorAtualizados = 0;

        foreach ($appsImportados as $appData) {
            $app_id = $appData['id'];
            $nome = $appData['name'];
            
            $devMode = null;
            if (isset($appData['development_mode'])) {
                $devMode = (bool)$appData['development_mode'];
            } else {
                // Se o campo development_mode veio ausente na listagem em lote,
                // fazemos uma chamada direta rápida no nó do app para obter o valor real
                $detalheApp = $fbGet($app_id . "?fields=development_mode", $token);
                if ($detalheApp && isset($detalheApp['development_mode'])) {
                    $devMode = (bool)$detalheApp['development_mode'];
                }
            }
            
            if ($devMode === null) {
                // Se não conseguimos obter de forma direta, chamamos a rotina unificada
                $resultado = verificarAppStatusMeta($app_id, null, $token);
                $status = $resultado['status'];
            } else {
                $status = $devMode ? 'analise' : 'aprovado';
            }

            // Verificar se já existe
            $stmtCheck = $pdo->prepare("SELECT id FROM apps WHERE app_id = ?");
            $stmtCheck->execute([$app_id]);
            $appExistente = $stmtCheck->fetch();

            if ($appExistente) {
                // Atualiza status e nome, salvando o token para futuras verificações automáticas
                $stmtUp = $pdo->prepare("UPDATE apps SET nome = ?, status = ?, status_conexao = 'online', user_access_token = ?, data_verificacao = NOW() WHERE id = ?");
                $stmtUp->execute([$nome, $status, $token, $appExistente['id']]);
                $contadorAtualizados++;
            } else {
                // Insere novo salvando o token para futuras verificações automáticas
                $stmtIn = $pdo->prepare("INSERT INTO apps (nome, app_id, status, status_conexao, user_access_token, data_verificacao) VALUES (?, ?, ?, 'online', ?, NOW())");
                $stmtIn->execute([$nome, $app_id, $status, $token]);
                $contadorNovos++;
            }
        }

        header("Location: apps.php?msg=importacao_sucesso&novos=" . $contadorNovos . "&atualizados=" . $contadorAtualizados);
        exit;
}

header("Location: " . $voltar_para . (strpos($voltar_para, '?') !== false ? '&' : '?') . "msg=ok");
exit;