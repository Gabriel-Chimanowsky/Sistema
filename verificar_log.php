<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

$logFile = __DIR__ . '/cloudflare_api_debug.log';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloudflare API Log Viewer</title>
    <script src="tailwind.js?v=1"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Fira+Code&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        pre { font-family: 'Fira Code', monospace; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-8">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Logs da API do Cloudflare</h1>
                <p class="text-xs text-slate-400 mt-1">Inspecione as requisições de sincronização em tempo real.</p>
            </div>
            <div class="flex gap-2">
                <button onclick="window.location.reload()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition">
                    🔄 Atualizar
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

        <div class="bg-slate-950 p-6 rounded-2xl border border-slate-800 shadow-2xl">
            <pre class="text-xs text-emerald-400 overflow-x-auto h-[600px] overflow-y-auto whitespace-pre-wrap leading-relaxed"><?php
                if (file_exists($logFile)) {
                    echo htmlspecialchars(file_get_contents($logFile));
                } else {
                    echo "Nenhum log gerado ainda.\n\nSiga estes passos para gerar o log:\n1. Dê 'git pull' no seu servidor.\n2. Vá na página de Clientes e mude o e-mail de um cliente (para um e-mail diferente).\n3. Volte aqui e clique em 'Atualizar'.";
                }
            ?></pre>
        </div>
    </div>
</body>
</html>
