<?php
/**
 * Cloudflare Email Routing API Helper
 * Fornece funções para gerenciar redirecionamentos diretamente no Cloudflare.
 */

if (!function_exists('registrarCfLog')) {
    /**
     * Grava logs de depuração no banco de dados (tabela cloudflare_api_logs)
     * e também em arquivo físico se houver permissão de gravação.
     */
    function registrarCfLog($texto, $pdo = null) {
        if ($pdo === null) {
            global $pdo;
        }
        if (isset($pdo)) {
            try {
                $pdo->prepare("INSERT INTO cloudflare_api_logs (texto) VALUES (?)")->execute([$texto]);
            } catch (Exception $e) {
                // Silencia se houver erro ao gravar log no banco
            }
        }
        // Fallback para arquivo
        $logFile = __DIR__ . '/cloudflare_api_debug.log';
        @file_put_contents($logFile, $texto, FILE_APPEND);
    }
}

if (!function_exists('cfApiCall')) {
    /**
     * Faz chamadas HTTP cURL para a API do Cloudflare.
     */
    function cfApiCall($token, $url, $method = 'GET', $body = null) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fail-safe contra erros de certificado SSL local/servidor
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // --- LOG DE DEBUG DA API DO CLOUDFLARE ---
        $logData = date('[Y-m-d H:i:s]') . " {$method} {$url}\n";
        if ($body !== null) {
            $logData .= "Request Body: " . json_encode($body) . "\n";
        }
        $logData .= "HTTP Code: {$httpCode}\n";
        if ($curlError) {
            $logData .= "cURL Error: {$curlError}\n";
        } else {
            $logData .= "Response: " . substr($response, 0, 500) . "\n";
        }
        $logData .= "--------------------------------------------------\n";
        registrarCfLog($logData);
        // ------------------------------------------
        
        if ($curlError) {
            return ['success' => false, 'errors' => [['message' => 'cURL error: ' . $curlError]]];
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'errors' => [['message' => 'JSON decode error: ' . $response]]];
        }
        
        return $decoded;
    }
}

if (!function_exists('buscarRegraPorEmail')) {
    /**
     * Procura no Cloudflare por uma regra de roteamento vinculada a um e-mail específico (alias).
     */
    function buscarRegraPorEmail($token, $zoneId, $email) {
        $page = 1;
        $perPage = 50;
        $emailLower = strtolower(trim($email));
        
        while (true) {
            $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/email/routing/rules?page={$page}&per_page={$perPage}";
            $data = cfApiCall($token, $url, 'GET');
            
            if (!$data || !isset($data['success']) || !$data['success']) {
                break;
            }
            
            foreach ($data['result'] as $rule) {
                foreach ($rule['matchers'] as $matcher) {
                    if (isset($matcher['field']) && $matcher['field'] === 'to' && isset($matcher['value'])) {
                        if (strtolower(trim($matcher['value'])) === $emailLower) {
                            return $rule;
                        }
                    }
                }
            }
            
            $info = $data['result_info'] ?? null;
            if (!$info || $page >= ($info['total_pages'] ?? 1)) {
                break;
            }
            $page++;
        }
        
        return null;
    }
}

if (!function_exists('criarRegraCloudflare')) {
    /**
     * Cria uma nova regra de redirecionamento de e-mail no Cloudflare.
     */
    function criarRegraCloudflare($token, $zoneId, $aliasEmail, $destEmail) {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/email/routing/rules";
        $body = [
            'name' => 'Redirect: ' . $aliasEmail,
            'enabled' => true,
            'matchers' => [
                [
                    'type' => 'literal',
                    'field' => 'to',
                    'value' => $aliasEmail
                ]
            ],
            'actions' => [
                [
                    'type' => 'forward',
                    'value' => [$destEmail]
                ]
            ],
            'priority' => 0
        ];
        return cfApiCall($token, $url, 'POST', $body);
    }
}

if (!function_exists('atualizarRegraCloudflare')) {
    /**
     * Atualiza uma regra de redirecionamento existente no Cloudflare.
     */
    function atualizarRegraCloudflare($token, $zoneId, $ruleId, $aliasEmail, $destEmail) {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/email/routing/rules/{$ruleId}";
        $body = [
            'name' => 'Redirect: ' . $aliasEmail,
            'enabled' => true,
            'matchers' => [
                [
                    'type' => 'literal',
                    'field' => 'to',
                    'value' => $aliasEmail
                ]
            ],
            'actions' => [
                [
                    'type' => 'forward',
                    'value' => [$destEmail]
                ]
            ],
            'priority' => 0
        ];
        return cfApiCall($token, $url, 'PUT', $body);
    }
}

if (!function_exists('excluirRegraCloudflare')) {
    /**
     * Exclui uma regra de redirecionamento no Cloudflare.
     */
    function excluirRegraCloudflare($token, $zoneId, $ruleId) {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/email/routing/rules/{$ruleId}";
        return cfApiCall($token, $url, 'DELETE');
    }
}

if (!function_exists('sincronizarRedirecionamentoConta')) {
    /**
     * Sincroniza o redirecionamento de e-mail de uma conta específica no Cloudflare.
     * Cria, atualiza ou exclui a regra conforme a existência do dono e do e-mail de destino.
     */
    function sincronizarRedirecionamentoConta($contaId, $pdo) {
        $logData = date('[Y-m-d H:i:s]') . " [START] sincronizarRedirecionamentoConta para Conta ID: {$contaId}\n";
        
        // 1. Obter configurações do Cloudflare
        $stmtConf = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();
        
        $token = $config['cloudflare_token'] ?? '';
        $zoneId = $config['cloudflare_zone_id'] ?? '';
        
        $logData .= "Credenciais - Token: " . (!empty($token) ? 'definido' : 'VAZIO') . " | Zone ID: " . (!empty($zoneId) ? 'definido' : 'VAZIO') . "\n";
        registrarCfLog($logData, $pdo);
        
        if (empty($token) || empty($zoneId)) {
            return; // Cloudflare não está configurado
        }
        
        // 2. Obter informações da conta
        $stmtConta = $pdo->prepare("SELECT email, destinada_a FROM contas WHERE id = ?");
        $stmtConta->execute([$contaId]);
        $conta = $stmtConta->fetch();
        
        if (!$conta) {
            return;
        }
        
        $aliasEmail = $conta['email'];
        $pessoaId = $conta['destinada_a'];
        
        $destEmail = null;
        if ($pessoaId) {
            $stmtPessoa = $pdo->prepare("SELECT email FROM pessoas WHERE id = ?");
            $stmtPessoa->execute([$pessoaId]);
            $destEmail = $stmtPessoa->fetchColumn() ?: null;
        }
        
        // 3. Buscar regra existente no Cloudflare
        $regraExistente = buscarRegraPorEmail($token, $zoneId, $aliasEmail);
        
        if (!empty($destEmail)) {
            // Se o dono possui um e-mail cadastrado
            if ($regraExistente) {
                // Atualizar se o destino atual for diferente ou se estiver inativo
                $destAtual = $regraExistente['actions'][0]['value'][0] ?? '';
                if (strtolower(trim($destAtual)) !== strtolower(trim($destEmail)) || !$regraExistente['enabled']) {
                    atualizarRegraCloudflare($token, $zoneId, $regraExistente['id'], $aliasEmail, $destEmail);
                }
            } else {
                // Se a regra não existe, criar uma nova
                criarRegraCloudflare($token, $zoneId, $aliasEmail, $destEmail);
            }
        } else {
            // Sem email de destino ou sem dono. Excluir regra do Cloudflare se existir.
            if ($regraExistente) {
                excluirRegraCloudflare($token, $zoneId, $regraExistente['id']);
            }
        }
    }
}

if (!function_exists('buscarTodasRegras')) {
    /**
     * Busca todas as regras de redirecionamento de e-mail no Cloudflare de uma só vez (paginado).
     */
    function buscarTodasRegras($token, $zoneId) {
        $rules = [];
        $page = 1;
        $perPage = 50;
        
        while (true) {
            $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/email/routing/rules?page={$page}&per_page={$perPage}";
            $data = cfApiCall($token, $url, 'GET');
            
            if (!$data || !isset($data['success']) || !$data['success']) {
                break;
            }
            
            foreach ($data['result'] as $rule) {
                $rules[] = $rule;
            }
            
            $info = $data['result_info'] ?? null;
            if (!$info || $page >= ($info['total_pages'] ?? 1)) {
                break;
            }
            $page++;
        }
        
        return $rules;
    }
}

if (!function_exists('indexarRegrasPorEmail')) {
    /**
     * Indexa as regras do Cloudflare em um array associativo chaveado pelo e-mail do alias (lowercase).
     */
    function indexarRegrasPorEmail($rules) {
        $indexed = [];
        foreach ($rules as $rule) {
            foreach ($rule['matchers'] as $matcher) {
                if (isset($matcher['field']) && $matcher['field'] === 'to' && isset($matcher['value'])) {
                    $emailLower = strtolower(trim($matcher['value']));
                    $indexed[$emailLower] = $rule;
                }
            }
        }
        return $indexed;
    }
}

if (!function_exists('sincronizarRedirecionamentosPessoa')) {
    /**
     * Sincroniza os redirecionamentos de todas as contas associadas a uma pessoa.
     * Otimizado para fazer apenas uma chamada de listagem de regras no Cloudflare.
     */
    function sincronizarRedirecionamentosPessoa($pessoaId, $pdo) {
        $logData = date('[Y-m-d H:i:s]') . " [START] sincronizarRedirecionamentosPessoa para Pessoa ID: {$pessoaId}\n";
        
        $stmtConf = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();
        
        $token = $config['cloudflare_token'] ?? '';
        $zoneId = $config['cloudflare_zone_id'] ?? '';
        
        $logData .= "Credenciais - Token: " . (!empty($token) ? 'definido' : 'VAZIO') . " | Zone ID: " . (!empty($zoneId) ? 'definido' : 'VAZIO') . "\n";
        registrarCfLog($logData, $pdo);
        
        if (empty($token) || empty($zoneId)) {
            return;
        }
        
        $regras = buscarTodasRegras($token, $zoneId);
        $regrasPorEmail = indexarRegrasPorEmail($regras);
        
        $stmtContas = $pdo->prepare("SELECT id, email FROM contas WHERE destinada_a = ?");
        $stmtContas->execute([$pessoaId]);
        $contas = $stmtContas->fetchAll();
        
        $stmtPessoa = $pdo->prepare("SELECT email FROM pessoas WHERE id = ?");
        $stmtPessoa->execute([$pessoaId]);
        $destEmail = $stmtPessoa->fetchColumn() ?: null;
        
        foreach ($contas as $c) {
            $aliasEmail = $c['email'];
            $regraExistente = $regrasPorEmail[strtolower(trim($aliasEmail))] ?? null;
            
            if (!empty($destEmail)) {
                if ($regraExistente) {
                    $destAtual = $regraExistente['actions'][0]['value'][0] ?? '';
                    if (strtolower(trim($destAtual)) !== strtolower(trim($destEmail)) || !$regraExistente['enabled']) {
                        atualizarRegraCloudflare($token, $zoneId, $regraExistente['id'], $aliasEmail, $destEmail);
                    }
                } else {
                    criarRegraCloudflare($token, $zoneId, $aliasEmail, $destEmail);
                }
            } else {
                if ($regraExistente) {
                    excluirRegraCloudflare($token, $zoneId, $regraExistente['id']);
                }
            }
        }
    }
}

if (!function_exists('sincronizarRedirecionamentoContasMassa')) {
    /**
     * Sincroniza redirecionamentos para múltiplos IDs de contas de uma única vez.
     * Otimizado para fazer apenas uma chamada de listagem de regras no Cloudflare.
     */
    function sincronizarRedirecionamentoContasMassa($idsArray, $pdo) {
        $stmtConf = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();
        
        if (empty($config['cloudflare_token']) || empty($config['cloudflare_zone_id'])) {
            return;
        }
        
        $token = $config['cloudflare_token'];
        $zoneId = $config['cloudflare_zone_id'];
        
        $regras = buscarTodasRegras($token, $zoneId);
        $regrasPorEmail = indexarRegrasPorEmail($regras);
        
        foreach ($idsArray as $contaId) {
            $stmtConta = $pdo->prepare("SELECT email, destinada_a FROM contas WHERE id = ?");
            $stmtConta->execute([$contaId]);
            $conta = $stmtConta->fetch();
            
            if (!$conta) continue;
            
            $aliasEmail = $conta['email'];
            $pessoaId = $conta['destinada_a'];
            
            $destEmail = null;
            if ($pessoaId) {
                $stmtPessoa = $pdo->prepare("SELECT email FROM pessoas WHERE id = ?");
                $stmtPessoa->execute([$pessoaId]);
                $destEmail = $stmtPessoa->fetchColumn() ?: null;
            }
            
            $regraExistente = $regrasPorEmail[strtolower(trim($aliasEmail))] ?? null;
            
            if (!empty($destEmail)) {
                if ($regraExistente) {
                    $destAtual = $regraExistente['actions'][0]['value'][0] ?? '';
                    if (strtolower(trim($destAtual)) !== strtolower(trim($destEmail)) || !$regraExistente['enabled']) {
                        atualizarRegraCloudflare($token, $zoneId, $regraExistente['id'], $aliasEmail, $destEmail);
                    }
                } else {
                    criarRegraCloudflare($token, $zoneId, $aliasEmail, $destEmail);
                }
            } else {
                if ($regraExistente) {
                    excluirRegraCloudflare($token, $zoneId, $regraExistente['id']);
                }
            }
        }
    }
}

if (!function_exists('removerRedirecionamentoConta')) {
    /**
     * Exclui o redirecionamento de uma conta que foi excluída do sistema.
     */
    function removerRedirecionamentoConta($contaId, $pdo) {
        $stmtConf = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();
        
        if (empty($config['cloudflare_token']) || empty($config['cloudflare_zone_id'])) {
            return;
        }
        
        $token = $config['cloudflare_token'];
        $zoneId = $config['cloudflare_zone_id'];
        
        $stmtConta = $pdo->prepare("SELECT email FROM contas WHERE id = ?");
        $stmtConta->execute([$contaId]);
        $email = $stmtConta->fetchColumn();
        
        if (!$email) {
            return;
        }
        
        $regraExistente = buscarRegraPorEmail($token, $zoneId, $email);
        if ($regraExistente) {
            excluirRegraCloudflare($token, $zoneId, $regraExistente['id']);
        }
    }
}

if (!function_exists('removerRedirecionamentoContasMassa')) {
    /**
     * Exclui redirecionamentos de múltiplas contas de uma só vez.
     * Otimizado para fazer apenas uma chamada de listagem de regras no Cloudflare.
     */
    function removerRedirecionamentoContasMassa($idsArray, $pdo) {
        $stmtConf = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();
        
        if (empty($config['cloudflare_token']) || empty($config['cloudflare_zone_id'])) {
            return;
        }
        
        $token = $config['cloudflare_token'];
        $zoneId = $config['cloudflare_zone_id'];
        
        $regras = buscarTodasRegras($token, $zoneId);
        $regrasPorEmail = indexarRegrasPorEmail($regras);
        
        foreach ($idsArray as $contaId) {
            $stmtConta = $pdo->prepare("SELECT email FROM contas WHERE id = ?");
            $stmtConta->execute([$contaId]);
            $email = $stmtConta->fetchColumn();
            
            if (!$email) continue;
            
            $regraExistente = $regrasPorEmail[strtolower(trim($email))] ?? null;
            if ($regraExistente) {
                excluirRegraCloudflare($token, $zoneId, $regraExistente['id']);
            }
        }
    }
}
