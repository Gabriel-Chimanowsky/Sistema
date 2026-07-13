<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

$executar  = isset($_GET['executar'])  && $_GET['executar']  === '1';
$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === '1';
$quantidade = (int) ($_GET['qtd'] ?? 100);
if ($quantidade < 1 || $quantidade > 500) $quantidade = 100;

// ========================
// EXECUÇÃO
// ========================
if ($executar && $confirmar) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><title>Remover Contas de Teste</title><script src='tailwind.js?v=1'></script><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap' rel='stylesheet'><style>body{font-family:'Inter',sans-serif;}</style></head><body class='bg-slate-950 text-slate-100 p-8 space-y-3 font-mono text-sm'>";
    echo "<h1 class='text-2xl font-extrabold mb-6 text-red-400'>🗑️ Removendo {$quantidade} logs/contas de teste...</h1>";

    // Buscar os registros do log_criacao_contas mais recentes
    $stmtLogs = $pdo->prepare("
        SELECT id, conta_id FROM log_criacao_contas 
        ORDER BY id DESC 
        LIMIT ?
    ");
    $stmtLogs->execute([$quantidade]);
    $logsData = $stmtLogs->fetchAll();

    if (empty($logsData)) {
        echo "<p class='text-yellow-400'>⚠️ Nenhum log de criação encontrado para remover.</p></body></html>";
        exit;
    }

    $logIds = array_column($logsData, 'id');
    $contaIds = array_filter(array_column($logsData, 'conta_id'));

    echo "<p class='text-slate-400'>Logs encontrados: <span class='text-white font-bold'>" . min($logIds) . " até " . max($logIds) . " (" . count($logIds) . " registros)</span></p>";

    // 1. Remover do log_criacao_contas
    $inLogs = implode(',', array_map('intval', $logIds));
    $pdo->query("DELETE FROM log_criacao_contas WHERE id IN ($inLogs)");
    $logRemovidos = count($logIds);
    echo "<p class='text-yellow-400'>📋 Registros removidos do log_criacao_contas: <span class='font-bold text-white'>{$logRemovidos}</span></p>";

    // 2. Remover da tabela contas
    $contasRemovidas = 0;
    if (!empty($contaIds)) {
        $inContas = implode(',', array_map('intval', $contaIds));
        $stmtDel = $pdo->query("DELETE FROM contas WHERE id IN ($inContas)");
        $contasRemovidas = $stmtDel->rowCount();
    }
    echo "<p class='text-red-400'>🗑️ Contas removidas da tabela contas: <span class='font-bold text-white'>{$contasRemovidas}</span></p>";

    echo "<hr class='border-slate-700 my-4'>";
    echo "<p class='text-emerald-400 text-lg font-bold'>✅ Concluído! {$contasRemovidas} conta(s) e {$logRemovidos} log(s) removidos.</p>";
    echo "<p class='text-slate-400 text-xs mt-1'>Os contadores de Criadas Hoje e Criadas na Semana já refletem a remoção.</p>";
    echo "<div class='flex gap-3 mt-4'>";
    echo "<a href='index.php' class='px-5 py-2 bg-slate-800 hover:bg-slate-700 rounded-xl font-bold text-sm transition'>← Dashboard</a>";
    echo "<a href='remover_teste.php' class='px-5 py-2 bg-red-800 hover:bg-red-700 rounded-xl font-bold text-sm transition'>Remover mais</a>";
    echo "</div></body></html>";
    exit;
}

// ========================
// TELA DE CONFIRMAÇÃO
// ========================

// Preview: quais logs e contas relacionadas serão deletados
$stmtPreview = $pdo->prepare("
    SELECT l.id AS log_id, l.criado_em AS log_criado_em, c.id AS conta_id, c.email, c.status, c.slack_perfil_sync
    FROM log_criacao_contas l
    LEFT JOIN contas c ON c.id = l.conta_id
    ORDER BY l.id DESC
    LIMIT ?
");
$stmtPreview->execute([$quantidade]);
$preview = $stmtPreview->fetchAll();

$logCount = count($preview);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remover Contas de Teste</title>
    <script src="tailwind.js?v=1"></script>
    <script src="lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-3xl mx-auto px-4 mt-28 pb-16 space-y-6">

        <!-- Header -->
        <div class="bg-slate-900 border border-red-900/40 rounded-3xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-red-900/30 rounded-2xl flex items-center justify-center">
                <i data-lucide="trash-2" class="w-7 h-7 text-red-400"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight">Remover Contas de Teste</h1>
                <p class="text-sm text-slate-400">Remove as últimas N contas criadas + entradas do log (reduz contadores de hoje/semana).</p>
            </div>
        </div>

        <!-- Seletor de quantidade -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-800">
                <i data-lucide="sliders" class="w-5 h-5 text-slate-400"></i>
                <h2 class="font-bold">Quantidade a Remover</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ([10, 25, 50, 100, 200] as $q): ?>
                    <a href="?qtd=<?= $q ?>"
                       class="px-4 py-2 rounded-xl text-sm font-bold border transition <?= $quantidade === $q ? 'bg-red-600 border-red-500 text-white' : 'bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-700' ?>">
                        <?= $q ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Aviso -->
        <div class="bg-red-950/20 border border-red-700/40 rounded-3xl p-5 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5"></i>
            <div class="text-sm">
                <div class="font-bold text-red-300">Ação irreversível!</div>
                <p class="text-slate-400 mt-1">
                    Serão removidas as <strong class="text-white"><?= $quantidade ?> contas com IDs mais altos</strong> (as mais recentes)
                    e seus <strong class="text-white"><?= $logCount ?></strong> registro(s) do log de criação.
                    Os contadores de <em>Criadas Hoje</em> e <em>Criadas na Semana</em> serão reduzidos automaticamente.
                </p>
            </div>
        </div>

        <!-- Preview das contas -->
        <?php if (!empty($preview)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <span class="font-bold text-sm">Preview — Contas que serão removidas</span>
                <span class="text-xs text-slate-400"><?= count($preview) ?> de <?= $quantidade ?> solicitadas</span>
            </div>
            <div class="overflow-x-auto max-h-64 overflow-y-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-950/50 text-slate-400 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-2">ID</th>
                            <th class="px-4 py-2">Email</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Sync Slack</th>
                            <th class="px-4 py-2">Criada em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview as $c): ?>
                        <tr class="border-t border-slate-800 text-slate-300">
                            <td class="px-4 py-1.5 font-mono"><?= $c['log_id'] ?></td>
                            <td class="px-4 py-1.5 font-mono truncate max-w-[200px]"><?= htmlspecialchars($c['email'] ?? 'Conta excluída') ?></td>
                            <td class="px-4 py-1.5"><?= htmlspecialchars($c['status'] ?? 'N/A') ?></td>
                            <td class="px-4 py-1.5 <?= $c['slack_perfil_sync'] ? 'text-emerald-400' : 'text-slate-500' ?>"><?= $c['slack_perfil_sync'] ? '✅ sim' : '— não' ?></td>
                            <td class="px-4 py-1.5 text-slate-400"><?= $c['log_criado_em'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botão de confirmação -->
        <a href="?executar=1&confirmar=1&qtd=<?= $quantidade ?>"
           onclick="return confirm('Tem certeza? Isso vai apagar <?= $quantidade ?> contas e seus logs de criação. Ação irreversível!')"
           class="flex items-center justify-center gap-2 w-full bg-red-600 hover:bg-red-700 active:scale-95 text-white font-extrabold py-4 px-6 rounded-2xl transition-all text-sm shadow-lg shadow-red-900/40">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
            Confirmar e Remover <?= $quantidade ?> Contas de Teste
        </a>

        <a href="index.php" class="flex items-center gap-2 text-sm text-slate-400 hover:text-slate-200 transition font-semibold">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar para o Dashboard
        </a>

    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
