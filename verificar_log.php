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
    <title>Cloudflare API Diagnostics & Inspector</title>
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

        <!-- Diagnóstico de Domínios e IDs no Banco -->
        <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">📊 Distribuição Atual de Contas por Domínio</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-700 text-slate-400">
                            <th class="py-2">Domínio</th>
                            <th class="py-2">Qtd Contas</th>
                            <th class="py-2">ID Mínimo</th>
                            <th class="py-2">ID Máximo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("
                                SELECT 
                                    LOWER(SUBSTRING_INDEX(email, '@', -1)) as dominio,
                                    COUNT(*) as total,
                                    MIN(id) as min_id,
                                    MAX(id) as max_id
                                FROM contas 
                                GROUP BY dominio
                                ORDER BY min_id ASC
                            ");
                            $rows = $stmt->fetchAll();
                            if (empty($rows)) {
                                echo "<tr><td colspan='4' class='py-4 text-center text-slate-500'>Nenhuma conta cadastrada no banco.</td></tr>";
                            } else {
                                foreach ($rows as $row) {
                                    echo "<tr class='border-b border-slate-850 hover:bg-slate-800/20'>";
                                    echo "<td class='py-2.5 font-semibold text-white'>" . htmlspecialchars($row['dominio']) . "</td>";
                                    echo "<td class='py-2.5 text-slate-300'>" . $row['total'] . "</td>";
                                    echo "<td class='py-2.5 font-mono text-slate-450'>" . $row['min_id'] . "</td>";
                                    echo "<td class='py-2.5 font-mono text-slate-450'>" . $row['max_id'] . "</td>";
                                    echo "</tr>";
                                }
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='4' class='py-4 text-red-500'>Erro: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Amostra de Contas -->
        <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">🔍 Amostra das primeiras 10 Contas de cada Domínio</h2>
            <div class="text-xs space-y-4">
                <?php
                try {
                    $stmtDominios = $pdo->query("SELECT DISTINCT LOWER(SUBSTRING_INDEX(email, '@', -1)) as dom FROM contas");
                    $doms = array_column($stmtDominios->fetchAll(), 'dom');
                    
                    foreach ($doms as $d) {
                        echo "<div class='space-y-1'>";
                        echo "<h3 class='font-bold text-sky-400'>" . htmlspecialchars($d) . "</h3>";
                        $stmtSample = $pdo->prepare("SELECT id, email FROM contas WHERE email LIKE ? ORDER BY id ASC LIMIT 10");
                        $stmtSample->execute(['%@' . $d]);
                        $samples = $stmtSample->fetchAll();
                        
                        echo "<div class='grid grid-cols-2 md:grid-cols-5 gap-2 font-mono text-[10px] text-slate-300 bg-slate-950 p-3 rounded-xl border border-slate-850'>";
                        foreach ($samples as $s) {
                            echo "<div>ID " . $s['id'] . ": " . htmlspecialchars(explode('@', $s['email'])[0]) . "</div>";
                        }
                        echo "</div>";
                        echo "</div>";
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
                <pre class="text-xs text-emerald-400 overflow-x-auto h-[300px] overflow-y-auto whitespace-pre-wrap leading-relaxed"><?php
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
