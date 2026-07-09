<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

$logFile = __DIR__ . '/cloudflare_api_debug.log';
$helperFile = __DIR__ . '/cloudflare_helper.php';
$processaFile = __DIR__ . '/processa.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloudflare API Diagnostics</title>
    <script src="tailwind.js?v=1"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;855&family=Fira+Code&display=swap" rel="stylesheet">
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

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'limpar_log') {
            if (file_exists($logFile)) {
                @unlink($logFile);
            }
            header("Location: verificar_log.php");
            exit;
        }
        ?>

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

        <!-- Logs da API -->
        <div class="space-y-2">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">📜 Histórico de Comunicação (Logs da API)</h2>
            <div class="bg-slate-950 p-6 rounded-2xl border border-slate-800 shadow-2xl">
                <pre class="text-xs text-emerald-400 overflow-x-auto h-[400px] overflow-y-auto whitespace-pre-wrap leading-relaxed"><?php
                    if (file_exists($logFile)) {
                        echo htmlspecialchars(file_get_contents($logFile));
                    } else {
                        echo "Nenhum histórico de log gravado até o momento.\n\nSe os status acima estiverem verdes, tente mudar o e-mail de algum cliente para disparar a sincronização.";
                    }
                ?></pre>
            </div>
        </div>

    </div>
</body>
</html>
