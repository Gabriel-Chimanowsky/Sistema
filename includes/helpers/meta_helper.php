<?php
/**
 * Helper para Validação e Monitoramento de Aplicativos Meta (Facebook Graph API)
 */

if (!function_exists('verificarAppStatusMeta')) {
    function verificarAppStatusMeta($app_id, $app_secret = null, $user_access_token = null, $tracked_permissions = '') {
        $app_id = trim($app_id);
        if (empty($app_id)) {
            return [
                'status_conexao' => 'caiu', 
                'status' => 'rejeitado', 
                'permissions_status' => [],
                'observacao_adicional' => null
            ];
        }

        $token = null;
        if (!empty($user_access_token)) {
            $token = trim($user_access_token);
        } elseif (!empty($app_secret)) {
            $token = $app_id . '|' . trim($app_secret);
        }

        $observacao_adicional = null;
        $permissions_status = [];

        // Parse tracked permissions
        $tracked_arr = [];
        if (!empty($tracked_permissions)) {
            $tracked_arr = array_filter(array_map('trim', explode(',', $tracked_permissions)));
        }

        if (!empty($token)) {
            // 1. Chamada autenticada com Token para buscar detalhes básicos
            $ch = curl_init();
            $url = "https://graph.facebook.com/v19.0/" . urlencode($app_id) . "?fields=id,name,development_mode&access_token=" . urlencode($token);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $res = curl_exec($ch);
            curl_close($ch);

            $dados = json_decode($res, true);
            if ($dados && isset($dados['id'])) {
                $devMode = true;
                if (isset($dados['development_mode'])) {
                    $devMode = !empty($dados['development_mode']);
                } else {
                    $chPub = curl_init();
                    $urlPub = "https://graph.facebook.com/v19.0/" . urlencode($app_id);
                    curl_setopt($chPub, CURLOPT_URL, $urlPub);
                    curl_setopt($chPub, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chPub, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($chPub, CURLOPT_TIMEOUT, 10);
                    curl_setopt($chPub, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                    $resPub = curl_exec($chPub);
                    curl_close($chPub);

                    $dadosPub = json_decode($resPub, true);
                    $isLive = false;
                    if ($dadosPub && isset($dadosPub['id'])) {
                        $isLive = true;
                    }
                    $devMode = !$isLive;
                }
                $status = $devMode ? 'analise' : 'aprovado';
                $status_conexao = 'online';

                // 2. Buscar status detalhado das permissões do aplicativo
                $chPerm = curl_init();
                $urlPerm = "https://graph.facebook.com/v19.0/" . urlencode($app_id) . "/permissions?access_token=" . urlencode($token);
                curl_setopt($chPerm, CURLOPT_URL, $urlPerm);
                curl_setopt($chPerm, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chPerm, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chPerm, CURLOPT_TIMEOUT, 10);
                $resPerm = curl_exec($chPerm);
                curl_close($chPerm);

                $permDados = json_decode($resPerm, true);
                $approved_list = [];
                if ($permDados && isset($permDados['data'])) {
                    foreach ($permDados['data'] as $item) {
                        if (isset($item['permission']) && isset($item['status'])) {
                            $approved_list[$item['permission']] = strtolower($item['status']);
                        }
                    }
                }

                $all_live = true;
                foreach ($tracked_arr as $p) {
                    $p_status = $approved_list[$p] ?? 'unapproved';
                    $permissions_status[$p] = $p_status;
                    if ($p_status !== 'live' && $p_status !== 'granted' && $p_status !== 'aprovado') {
                        $all_live = false;
                    }
                }

                // 3. Auto-ativar Live Mode se todas as permissões forem aprovadas
                if ($devMode && count($tracked_arr) > 0 && $all_live) {
                    $chPost = curl_init();
                    curl_setopt($chPost, CURLOPT_URL, "https://graph.facebook.com/v19.0/" . urlencode($app_id));
                    curl_setopt($chPost, CURLOPT_POST, true);
                    curl_setopt($chPost, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chPost, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($chPost, CURLOPT_POSTFIELDS, http_build_query([
                        'development_mode' => 'false',
                        'access_token' => $token
                    ]));
                    $postRes = curl_exec($chPost);
                    curl_close($chPost);

                    $postDados = json_decode($postRes, true);
                    if ($postDados && !empty($postDados['success'])) {
                        $status = 'aprovado';
                        $status_conexao = 'online';
                        $observacao_adicional = "[Automático] Aplicativo ativado para Live Mode com sucesso, pois todas as permissões monitoradas foram aprovadas.";
                    } else {
                        $msgErro = $postDados['error']['message'] ?? 'Erro desconhecido ao mudar modo do aplicativo.';
                        $observacao_adicional = "[Automático] Tentativa de ativar Live Mode falhou: " . $msgErro;
                    }
                }

                return [
                    'status_conexao' => $status_conexao,
                    'status' => $status,
                    'permissions_status' => $permissions_status,
                    'observacao_adicional' => $observacao_adicional
                ];
            }
        }

        // Chamada pública para a Graph API (fallback de token ausente ou expirado)
        $ch = curl_init();
        $url = "https://graph.facebook.com/v19.0/" . urlencode($app_id);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $res = curl_exec($ch);
        curl_close($ch);

        $dados = json_decode($res, true);
        
        foreach ($tracked_arr as $p) {
            $permissions_status[$p] = 'unapproved';
        }

        if ($dados && isset($dados['id'])) {
            return [
                'status_conexao' => 'online',
                'status' => 'aprovado',
                'permissions_status' => $permissions_status,
                'observacao_adicional' => null
            ];
        }

        // Checagem de redirecionamento do diálogo OAuth do Facebook
        $oauthUrl = "https://www.facebook.com/v19.0/dialog/oauth?client_id=" . urlencode($app_id) . "&redirect_uri=https://www.facebook.com/connect/login_success.html";
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $oauthUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch2, CURLOPT_HEADER, true);
        curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $html = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch2, CURLINFO_REDIRECT_URL);
        curl_close($ch2);

        if ($httpCode2 == 302 && !empty($redirectUrl)) {
            if (stripos($redirectUrl, 'PLATFORM__INVALID_APP_ID') !== false || stripos($redirectUrl, '/oauth/error/') !== false) {
                return [
                    'status_conexao' => 'caiu', 
                    'status' => 'rejeitado',
                    'permissions_status' => $permissions_status,
                    'observacao_adicional' => null
                ];
            }
            
            return [
                'status_conexao' => 'caiu', 
                'status' => 'analise',
                'permissions_status' => $permissions_status,
                'observacao_adicional' => null
            ];
        }

        return [
            'status_conexao' => 'caiu', 
            'status' => 'rejeitado',
            'permissions_status' => $permissions_status,
            'observacao_adicional' => null
        ];
    }
}
