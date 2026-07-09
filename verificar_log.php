<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

$logFile = __DIR__ . '/cloudflare_api_debug.log';
$helperFile = __DIR__ . '/cloudflare_helper.php';
$processaFile = __DIR__ . '/processa.php';

$mensagemAcao = '';

// Processar ações antes de renderizar qualquer HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'limpar_log') {
        try {
            $pdo->query("TRUNCATE TABLE cloudflare_api_logs");
        } catch (Exception $e) {}
        if (file_exists($logFile)) {
            @unlink($logFile);
        }
        header("Location: verificar_log.php");
        exit;
    }
    
    if ($acao === 'forcar_sync_slack') {
        try {
            sincronizarSlackTracker($pdo);
            $mensagemAcao = "✓ Sincronização do Slack disparada com sucesso!";
        } catch (Exception $e) {
            $mensagemAcao = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloudflare & Slack Diagnostics</title>
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
                <p class="text-xs text-slate-400 mt-1">Status em tempo real das alterações do Cloudflare e Slack.</p>
            </div>
            <div class="flex gap-2">
                <form method="POST" class="inline">
                    <input type="hidden" name="acao" value="forcar_sync_slack">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition">
                        💬 Forçar Sync Slack
                    </button>
                </form>
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

        <?php if (!empty($mensagemAcao)): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 p-4 rounded-xl text-xs font-bold">
                <?php echo htmlspecialchars($mensagemAcao); ?>
            </div>
        <?php endif; ?>

        <!-- Diagnóstico de Domínios e IDs no Banco -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Domínios e Contas -->
            <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">📊 Distribuição de Contas por Domínio</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-700 text-slate-400">
                                <th class="py-2">Domínio</th>
                                <th class="py-2">Qtd Contas</th>
                                <th class="py-2">ID Mín</th>
                                <th class="py-2">ID Máx</th>
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
                                    echo "<tr><td colspan='4' class='py-4 text-center text-slate-500'>Nenhuma conta cadastrada.</td></tr>";
                                } else {
                                    foreach ($rows as $row) {
                                        echo "<tr class='border-b border-slate-850 hover:bg-slate-800/20'>";
                                        echo "<td class='py-2 font-semibold text-white'>" . htmlspecialchars($row['dominio']) . "</td>";
                                        echo "<td class='py-2 text-slate-300'>" . $row['total'] . "</td>";
                                        echo "<td class='py-2 font-mono text-slate-450'>" . $row['min_id'] . "</td>";
                                        echo "<td class='py-2 font-mono text-slate-450'>" . $row['max_id'] . "</td>";
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

            <!-- Status do Sincronizador de Contas (Slack) -->
            <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">💬 Status do Sincronizador do Slack</h2>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Contas Pendentes de Sincronizar:</span>
                        <span class="font-mono text-white font-bold">
                            <?php
                            try {
                                $countUnsynced = $pdo->query("SELECT COUNT(*) FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0")->fetchColumn();
                                echo $countUnsynced;
                            } catch (Exception $e) {
                                echo "Erro";
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">BMs Pendentes de Sincronizar:</span>
                        <span class="font-mono text-white font-bold">
                            <?php
                            try {
                                $countBmUnsynced = $pdo->query("SELECT COUNT(*) FROM contas WHERE bm_criada = 1 AND slack_bm_sync = 0")->fetchColumn();
                                echo $countBmUnsynced;
                            } catch (Exception $e) {
                                echo "Erro";
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Lotes Gravados no Banco (Slack) -->
        <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">📦 Lotes de Sincronização Gravados (Banco de Dados)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-700 text-slate-400">
                            <th class="py-2">ID Lote</th>
                            <th class="py-2">List ID (Slack)</th>
                            <th class="py-2">Semana</th>
                            <th class="py-2">Tipo</th>
                            <th class="py-2">Domínio</th>
                            <th class="py-2">Criado Em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmtLotes = $pdo->query("SELECT * FROM slack_lotes_count ORDER BY id DESC LIMIT 50");
                            $lotes = $stmtLotes->fetchAll();
                            if (empty($lotes)) {
                                echo "<tr><td colspan='6' class='py-4 text-center text-slate-500'>Nenhum lote de sincronização gravado.</td></tr>";
                            } else {
                                foreach ($lotes as $l) {
                                    echo "<tr class='border-b border-slate-850 hover:bg-slate-800/20'>";
                                    echo "<td class='py-2 font-mono text-slate-400'>" . $l['id'] . "</td>";
                                    echo "<td class='py-2 font-mono text-slate-300'>" . htmlspecialchars($l['list_id']) . "</td>";
                                    echo "<td class='py-2 text-slate-350'>" . htmlspecialchars($l['week']) . "</td>";
                                    echo "<td class='py-2'><span class='px-2 py-0.5 rounded text-[10px] font-bold uppercase " . ($l['type'] === 'perfil' ? 'bg-sky-500/10 text-sky-400' : 'bg-purple-500/10 text-purple-400') . "'>" . htmlspecialchars($l['type']) . "</span></td>";
                                    echo "<td class='py-2 font-semibold text-white'>" . htmlspecialchars($l['domain'] ?? 'N/A') . "</td>";
                                    echo "<td class='py-2 text-slate-450'>" . date("d/m/Y H:i:s", strtotime($l['criado_em'])) . "</td>";
                                    echo "</tr>";
                                }
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='6' class='py-4 text-red-500'>Erro: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Logs da API Cloudflare -->
        <div class="space-y-2">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">📜 Histórico de Comunicação (Logs da API)</h2>
            <div class="bg-slate-950 p-6 rounded-2xl border border-slate-800 shadow-2xl">
                <pre class="text-xs text-emerald-400 overflow-x-auto h-[250px] overflow-y-auto whitespace-pre-wrap leading-relaxed"><?php
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
