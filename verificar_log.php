<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

$logFile = __DIR__ . '/cloudflare_api_debug.log';
$helperFile = __DIR__ . '/cloudflare_helper.php';
$processaFile = __DIR__ . '/processa.php';

// Processar limpeza de log antes de renderizar qualquer HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'limpar_log') {
    try {
        $pdo->query("TRUNCATE TABLE cloudflare_api_logs");
    } catch (Exception $e) {}
    if (file_exists($logFile)) {
        @unlink($logFile);
    }
    header("Location: verificar_log.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloudflare API Diagnostics</title>
    <script src="tailwind.js?v=1"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Fira+Code&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        pre { font-family: 'Fira Code', monospace; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-8 font-sans">
    <div class="max-w-4xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Painel de Diagnóstico de Integração</h1>
                <p class="text-xs text-slate-400 mt-1">Status em tempo real das alterações do Cloudflare.</p>
            </div>
            <div class="flex gap-2">
                <button onclick="window.location.reload()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition">
                    🔄 Atualizar Diagnóstico
                </button>
                <form method="POST" class="inline">
                    <input type="hidden" name="acao" value="limpar_log">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition">
                        🗑️ Limpar Log
                    </button>
                </form>
            </div>
        </div>

        <!-- Diagnóstico Físico de Arquivos e Permissões -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">📁 Status dos Arquivos (Git Pull)</h2>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">cloudflare_helper.php:</span>
                        <span class="font-mono text-slate-200">
                            <?php 
                            if (file_exists($helperFile)) {
                                echo "Presente (Modificado em: " . date("d/m/Y H:i:s", filemtime($helperFile)) . ")";
                            } else {
                                echo "<span class='text-red-500 font-bold'>NÃO ENCONTRADO</span>";
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">processa.php:</span>
                        <span class="font-mono text-slate-200">
                            <?php 
                            if (file_exists($processaFile)) {
                                echo "Presente (Modificado em: " . date("d/m/Y H:i:s", filemtime($processaFile)) . ")";
                            } else {
                                echo "<span class='text-red-500 font-bold'>NÃO ENCONTRADO</span>";
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Pasta Raiz Gravável:</span>
                        <span class="font-mono">
                            <?php 
                            if (is_writable(__DIR__)) {
                                echo "<span class='text-emerald-400 font-bold'>SIM (Gravação de Logs ok)</span>";
                            } else {
                                echo "<span class='text-red-500 font-bold'>NÃO (Erro de permissão para criar logs)</span>";
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">💾 Status do Banco de Dados</h2>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Coluna 'email' em 'pessoas':</span>
                        <span class="font-mono">
                            <?php 
                            try {
                                $stmt = $pdo->query("SHOW COLUMNS FROM pessoas");
                                $colunas = array_column($stmt->fetchAll(), 'Field');
                                if (in_array('email', $colunas)) {
                                    echo "<span class='text-emerald-400 font-bold'>SIM (Criada com sucesso)</span>";
                                } else {
                                    echo "<span class='text-red-500 font-bold'>NÃO (Erro na migração)</span>";
                                }
                            } catch (Exception $e) {
                                echo "<span class='text-red-500 font-bold'>Erro ao verificar: " . $e->getMessage() . "</span>";
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Configuração Cloudflare:</span>
                        <span class="font-mono">
                            <?php 
                            try {
                                $stmtConf = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id FROM configuracoes LIMIT 1");
                                $config = $stmtConf->fetch();
                                $token = $config['cloudflare_token'] ?? '';
                                $zone = $config['cloudflare_zone_id'] ?? '';
                                
                                if (!empty($token) && !empty($zone)) {
                                    echo "<span class='text-emerald-400 font-bold'>Configurado (Token e Zone presentes)</span>";
                                } else {
                                    echo "<span class='text-amber-500 font-bold'>INCOMPLETO (Verifique token ou zone_id em branco)</span>";
                                }
                            } catch (Exception $e) {
                                echo "<span class='text-red-500 font-bold'>Erro: " . $e->getMessage() . "</span>";
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Dump da API de Regras do Cloudflare (Diagnóstico de Paginação) -->
        <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">⚡ Teste de Resposta da API (Paginador do Cloudflare)</h2>
            <div class="text-xs space-y-2">
                <?php
                try {
                    $stmtConf = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id FROM configuracoes LIMIT 1");
                    $config = $stmtConf->fetch();
                    $token = $config['cloudflare_token'] ?? '';
                    $zone = $config['cloudflare_zone_id'] ?? '';
                    
                    if (!empty($token) && !empty($zone)) {
                        require_once 'cloudflare_helper.php';
                        $url = "https://api.cloudflare.com/client/v4/zones/{$zone}/email/routing/rules?page=1&per_page=50";
                        $data = cfApiCall($token, $url, 'GET');
                        
                        echo "<p><strong class='text-slate-400'>Chaves da resposta raiz:</strong> " . implode(', ', array_keys($data)) . "</p>";
                        if (isset($data['result_info'])) {
                            echo "<p class='text-emerald-400 font-bold'>result_info encontrado:</p>";
                            echo "<pre class='bg-slate-950 p-3 rounded-lg text-emerald-400 font-mono mt-1'>" . print_r($data['result_info'], true) . "</pre>";
                        } else {
                            echo "<p class='text-red-500 font-bold'>AVISO: 'result_info' está FALTANDO na resposta da API do Cloudflare!</p>";
                        }
                    } else {
                        echo "<p class='text-amber-500'>Credenciais em branco no banco.</p>";
                    }
                } catch (Exception $e) {
                    echo "<p class='text-red-500'>Erro: " . $e->getMessage() . "</p>";
                }
                ?>
            </div>
        </div>

        <!-- Logs da API -->
        <div class="space-y-2">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">📜 Histórico de Comunicação (Logs da API)</h2>
            <div class="bg-slate-950 p-6 rounded-2xl border border-slate-800 shadow-2xl">
                <pre class="text-xs text-emerald-400 overflow-x-auto h-[400px] overflow-y-auto whitespace-pre-wrap leading-relaxed"><?php
                    $logs = [];
                    try {
                        $stmtLogs = $pdo->query("SELECT texto FROM cloudflare_api_logs ORDER BY id DESC LIMIT 100");
                        $logs = array_reverse(array_column($stmtLogs->fetchAll(), 'texto'));
                    } catch (Exception $e) {
                        $logs[] = "Erro ao ler logs do banco: " . $e->getMessage() . "\n";
                    }
                    
                    if (file_exists($logFile)) {
                        $logs[] = "\n--- LOGS DO ARQUIVO FÍSICO ---\n" . file_get_contents($logFile);
                    }
                    
                    if (empty($logs) || (count($logs) === 1 && empty(trim($logs[0])))) {
                        echo "Nenhum histórico de log gravado até o momento.\n\nSe os status acima estiverem verdes, tente mudar o e-mail de algum cliente para disparar a sincronização.";
                    } else {
                        echo htmlspecialchars(implode("", $logs));
                    }
                ?></pre>
            </div>
        </div>

    </div>
</body>
</html>
