<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Debug Slack Badge</title>
<script src="tailwind.js?v=1"></script>
<style>body{font-family:monospace;}</style>
</head>
<body class="bg-slate-950 text-slate-100 p-8 space-y-6 text-sm">
<h1 class="text-xl font-bold text-purple-400">🔍 Debug Badge Slack</h1>

<?php
// 1. Total não sync por status
$rows = $pdo->query("
    SELECT status, slack_perfil_sync, COUNT(*) as total 
    FROM contas 
    GROUP BY status, slack_perfil_sync 
    ORDER BY status, slack_perfil_sync
")->fetchAll();

echo "<div class='bg-slate-900 p-4 rounded-xl'><h2 class='font-bold text-yellow-400 mb-3'>Contas por Status + slack_perfil_sync</h2><table class='w-full text-xs'><tr class='text-slate-400'><th class='text-left p-1'>Status</th><th class='text-left p-1'>slack_perfil_sync</th><th class='text-left p-1'>Total</th></tr>";
foreach ($rows as $r) {
    $cor = ($r['status'] === 'criada' && $r['slack_perfil_sync'] == 0) ? 'text-red-400 font-bold' : 'text-slate-300';
    echo "<tr class='{$cor}'><td class='p-1'>{$r['status']}</td><td class='p-1'>{$r['slack_perfil_sync']}</td><td class='p-1'>{$r['total']}</td></tr>";
}
echo "</table></div>";

// 2. Contas de hoje
$hoje = $pdo->query("
    SELECT COUNT(*) FROM log_criacao_contas WHERE DATE(criado_em) = CURDATE()
")->fetchColumn();
echo "<div class='bg-slate-900 p-4 rounded-xl'><h2 class='font-bold text-blue-400 mb-2'>Criadas hoje (log): <span class='text-white'>{$hoje}</span></h2>";

// 3. Contas criadas hoje na tabela contas (não sync)
$hojeSemSync = $pdo->query("
    SELECT c.status, c.slack_perfil_sync, COUNT(*) as total
    FROM contas c
    INNER JOIN log_criacao_contas l ON l.conta_id = c.id
    WHERE DATE(l.criado_em) = CURDATE()
    GROUP BY c.status, c.slack_perfil_sync
")->fetchAll();
echo "<h2 class='font-bold text-green-400 mb-2 mt-3'>Status das contas criadas HOJE:</h2><table class='w-full text-xs'><tr class='text-slate-400'><th class='text-left p-1'>Status</th><th class='text-left p-1'>slack_sync</th><th class='text-left p-1'>Total</th></tr>";
foreach ($hojeSemSync as $r) {
    echo "<tr class='text-slate-300'><td class='p-1'>{$r['status']}</td><td class='p-1'>{$r['slack_perfil_sync']}</td><td class='p-1'>{$r['total']}</td></tr>";
}
echo "</table></div>";

// 4. Contagem que a badge usa
$contasNaoSync = (int) $pdo->query("
    SELECT COUNT(*) FROM contas 
    WHERE status IN ('criada','autenticada','exportado') 
    AND slack_perfil_sync = 0
")->fetchColumn();
$faltam = ($contasNaoSync === 0) ? 50 : (50 - ($contasNaoSync % 50));
if ($faltam === 50 && $contasNaoSync > 0) $faltam = 0;

echo "<div class='bg-slate-900 p-4 rounded-xl'>
    <h2 class='font-bold text-purple-400 mb-2'>Resultado da badge</h2>
    <p>contasNaoSync = <span class='text-yellow-300 font-bold'>{$contasNaoSync}</span></p>
    <p>faltamParaSlack = <span class='text-red-400 font-bold'>{$faltam}</span></p>
    <p class='mt-2 text-slate-400'>Badge mostra: <span class='text-white font-bold'>{$faltam} p/ Slack</span></p>
</div>";

// 5. Agrupamento por domínio (como o sincronizarSlackTracker vê)
$todos = $pdo->query("SELECT email FROM contas WHERE status IN ('criada','autenticada','exportado') AND slack_perfil_sync = 0")->fetchAll();
$porDominio = [];
foreach ($todos as $c) {
    $domainEmail = strtolower(trim(explode('@', $c['email'])[1] ?? ''));
    $domName = strtolower(explode('.', $domainEmail)[0] ?? 'dollfinn');
    if (empty($domName)) $domName = 'dollfinn';
    $porDominio[$domName] = ($porDominio[$domName] ?? 0) + 1;
}
arsort($porDominio);
echo "<div class='bg-slate-900 p-4 rounded-xl'><h2 class='font-bold text-orange-400 mb-2'>Por domínio (como o auto-sync vê)</h2><table class='w-full text-xs'><tr class='text-slate-400'><th class='text-left p-1'>Domínio</th><th class='text-left p-1'>Pendentes</th><th class='text-left p-1'>Dispara lote?</th></tr>";
foreach ($porDominio as $dom => $cnt) {
    $dispara = $cnt >= 50 ? "<span class='text-emerald-400'>✅ SIM</span>" : "<span class='text-red-400'>❌ NÃO ({$cnt} < 50)</span>";
    echo "<tr class='text-slate-300'><td class='p-1'>{$dom}</td><td class='p-1 font-bold'>{$cnt}</td><td class='p-1'>{$dispara}</td></tr>";
}
echo "</table></div>";
?>

<a href="index.php" class="inline-block px-4 py-2 bg-slate-800 hover:bg-slate-700 rounded-xl text-xs font-bold transition">← Voltar</a>
</body>
</html>
