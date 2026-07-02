<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

// Fetch credentials
$stmt = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id, cloudflare_dest_email FROM configuracoes LIMIT 1");
$config = $stmt->fetch();

$token = $config['cloudflare_token'] ?? '';
$zoneId = $config['cloudflare_zone_id'] ?? '';
$destEmail = $config['cloudflare_dest_email'] ?? '';

$tokenMascarado = !empty($token) ? substr($token, 0, 6) . "..." . substr($token, -6) : "VAZIO";

// API endpoints
$verifyUrl = "https://api.cloudflare.com/client/v4/user/tokens/verify";
$zoneUrl = "https://api.cloudflare.com/client/v4/zones/" . $zoneId;
$rulesUrl = "https://api.cloudflare.com/client/v4/zones/" . $zoneId . "/email/routing/rules";

function makeCfRequest($url, $token, $method = 'GET') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?: $response
    ];
}

$verifyResult = makeCfRequest($verifyUrl, $token);
$zoneResult = !empty($zoneId) ? makeCfRequest($zoneUrl, $token) : ['code' => 'N/A', 'body' => 'Zone ID vazio'];
$rulesResult = !empty($zoneId) ? makeCfRequest($rulesUrl, $token) : ['code' => 'N/A', 'body' => 'Zone ID vazio'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostics Cloudflare API</title>
    <script src="tailwind.js?v=1"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        pre { font-family: 'Fira Code', monospace; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-8">
    <div class="max-w-4xl mx-auto space-y-8">
        
        <div class="flex items-center justify-between border-b border-slate-800 pb-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-white">Diagnóstico Cloudflare API</h1>
                <p class="text-slate-400 mt-1 text-sm">Ferramenta para inspecionar permissões reais do seu Token.</p>
            </div>
            <a href="cloudflare.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-xl text-xs font-bold transition">
                Voltar ao Bot
            </a>
        </div>

        <!-- Credenciais Atuais -->
        <div class="bg-slate-800/50 border border-slate-800 p-6 rounded-2xl">
            <h2 class="text-lg font-bold text-white mb-4">Credenciais Carregadas do Banco</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div>
                    <span class="block text-xs font-bold text-slate-500 uppercase">API Token</span>
                    <code class="text-slate-300 font-mono"><?= htmlspecialchars($tokenMascarado) ?></code>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-500 uppercase">Zone ID</span>
                    <code class="text-slate-300 font-mono"><?= htmlspecialchars($zoneId ?: 'VAZIO') ?></code>
                </div>
                <div>
                    <span class="block text-xs font-bold text-slate-500 uppercase">Email de Destino</span>
                    <span class="text-slate-300"><?= htmlspecialchars($destEmail ?: 'VAZIO') ?></span>
                </div>
            </div>
        </div>

        <!-- Teste 1: Verify Token -->
        <div class="bg-slate-800/30 border border-slate-800/80 rounded-2xl overflow-hidden">
            <div class="p-5 bg-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="font-extrabold text-sm text-white">1. Verificação Geral do Token</h3>
                    <p class="text-xs text-slate-400">Endpoint: /user/tokens/verify</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold <?= $verifyResult['code'] === 200 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' ?>">
                    HTTP <?= $verifyResult['code'] ?>
                </span>
            </div>
            <div class="p-5">
                <pre class="bg-slate-950 p-4 rounded-xl text-xs overflow-x-auto text-emerald-400 max-h-60"><?= htmlspecialchars(print_r($verifyResult['body'], true)) ?></pre>
            </div>
        </div>

        <!-- Teste 2: Zone Get -->
        <div class="bg-slate-800/30 border border-slate-800/80 rounded-2xl overflow-hidden">
            <div class="p-5 bg-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="font-extrabold text-sm text-white">2. Leitura de Informações do Domínio</h3>
                    <p class="text-xs text-slate-400">Endpoint: /zones/{zone_id}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold <?= $zoneResult['code'] === 200 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' ?>">
                    HTTP <?= $zoneResult['code'] ?>
                </span>
            </div>
            <div class="p-5">
                <pre class="bg-slate-950 p-4 rounded-xl text-xs overflow-x-auto text-sky-400 max-h-60"><?= htmlspecialchars(print_r($zoneResult['body'], true)) ?></pre>
            </div>
        </div>

        <!-- Teste 3: Email Rules -->
        <div class="bg-slate-800/30 border border-slate-800/80 rounded-2xl overflow-hidden">
            <div class="p-5 bg-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="font-extrabold text-sm text-white">3. Acesso às Regras de Email (Email Routing Rules)</h3>
                    <p class="text-xs text-slate-400">Endpoint: /zones/{zone_id}/email/routing/rules</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold <?= $rulesResult['code'] === 200 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' ?>">
                    HTTP <?= $rulesResult['code'] ?>
                </span>
            </div>
            <div class="p-5">
                <pre class="bg-slate-950 p-4 rounded-xl text-xs overflow-x-auto text-amber-400 max-h-80"><?= htmlspecialchars(print_r($rulesResult['body'], true)) ?></pre>
            </div>
        </div>

    </div>
</body>
</html>
