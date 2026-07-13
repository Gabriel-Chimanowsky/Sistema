<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

// Aceitar modo forçado via GET
$forcar = isset($_GET['forcar']) && $_GET['forcar'] === '1';
$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === '1';

// ========================
//  PROCESSAR AÇÃO DE FORÇAR
// ========================
if ($forcar && $confirmar) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><title>Forçar Lote Slack</title><script src='tailwind.js?v=1'></script><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap' rel='stylesheet'><style>body{font-family:'Inter',sans-serif;}</style></head><body class='bg-slate-950 text-slate-100 p-8'>";
    echo "<h1 class='text-2xl font-extrabold mb-6 text-purple-400'>⚡ Forçando envio do lote ao Slack...</h1>";
    echo "<div class='space-y-2 font-mono text-sm'>";

    // 1. Buscar configurações
    $stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao, email_dominio FROM configuracoes LIMIT 1");
    $config = $stmtConf->fetch();
    $token = $config['slack_token'] ?? '';
    $canal = $config['slack_canal_notificacao'] ?? '';
    $dominioEmail = $config['email_dominio'] ?? '';
    $dominioLimpo = ltrim($dominioEmail, '@');
    $nomeDominio = strtolower(explode('.', $dominioLimpo)[0] ?? 'dollfinn');
    if (empty($nomeDominio)) $nomeDominio = 'dollfinn';

    if (empty($token)) {
        echo "<p class='text-red-400'>❌ Token do Slack não configurado.</p></div></body></html>";
        exit;
    }

    // 2. Buscar lista do mês
    $mesAtual = date('Y-m');
    $stmtLista = $pdo->prepare("SELECT * FROM slack_listas WHERE mes = ?");
    $stmtLista->execute([$mesAtual]);
    $listaObj = $stmtLista->fetch();

    if (!$listaObj) {
        echo "<p class='text-yellow-400'>⚠️ Lista do mês '{$mesAtual}' não encontrada. Rodando sincronizarSlackTracker para criar...</p>";
        sincronizarSlackTracker($pdo);
        $stmtLista->execute([$mesAtual]);
        $listaObj = $stmtLista->fetch();
        if (!$listaObj) {
            echo "<p class='text-red-400'>❌ Falha ao criar/encontrar lista do mês.</p></div></body></html>";
            exit;
        }
    }

    $list_id = $listaObj['list_id'];
    $primary_col_id = $listaObj['primary_col_id'];

    echo "<p class='text-emerald-400'>✅ Lista encontrada: <span class='text-slate-300'>{$listaObj['list_id']}</span></p>";

    // 3. Buscar/criar semana atual
    $week_title = obterSemanaDoMes(time());
    echo "<p class='text-blue-400'>📅 Semana atual: <span class='text-slate-300'>{$week_title}</span></p>";

    // Buscar itens da lista para encontrar semana
    $chItems = curl_init("https://slack.com/api/slackLists.items.list");
    curl_setopt($chItems, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chItems, CURLOPT_POST, true);
    curl_setopt($chItems, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json; charset=utf-8"
    ]);
    curl_setopt($chItems, CURLOPT_POSTFIELDS, json_encode(["list_id" => $list_id]));
    $itemsRes = json_decode(curl_exec($chItems), true);
    curl_close($chItems);

    $week_row_id = null;
    $items = $itemsRes['items'] ?? [];
    foreach ($items as $item) {
        if (empty($item['parent_item_id'])) {
            $itemName = '';
            if (isset($item['fields'])) {
                foreach ($item['fields'] as $f) {
                    if ($f['key'] === 'name' || $f['column_id'] === $primary_col_id) {
                        $itemName = extrairTextoSlackField($f);
                        break;
                    }
                }
            }
            if ($itemName === $week_title) {
                $week_row_id = $item['id'];
                break;
            }
        }
    }

    if (!$week_row_id) {
        echo "<p class='text-yellow-400'>⚠️ Linha da semana não encontrada no Slack, criando...</p>";
        $chNewWeek = curl_init("https://slack.com/api/slackLists.items.create");
        curl_setopt($chNewWeek, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chNewWeek, CURLOPT_POST, true);
        curl_setopt($chNewWeek, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token,
            "Content-Type: application/json; charset=utf-8"
        ]);
        curl_setopt($chNewWeek, CURLOPT_POSTFIELDS, json_encode([
            "list_id" => $list_id,
            "initial_fields" => [[
                "column_id" => $primary_col_id,
                "rich_text" => buildRichText($week_title)
            ]]
        ]));
        $newWeekRes = json_decode(curl_exec($chNewWeek), true);
        curl_close($chNewWeek);

        if ($newWeekRes && isset($newWeekRes['ok']) && $newWeekRes['ok']) {
            $week_row_id = $newWeekRes['item']['id'] ?? $newWeekRes['id'] ?? null;
            echo "<p class='text-emerald-400'>✅ Linha da semana criada com sucesso!</p>";
        } else {
            echo "<p class='text-red-400'>❌ Falha ao criar semana: " . htmlspecialchars(json_encode($newWeekRes)) . "</p></div></body></html>";
            exit;
        }
    } else {
        echo "<p class='text-emerald-400'>✅ Linha da semana já existe no Slack.</p>";
    }

    // 4. Contas não sincronizadas por domínio
    $contasUnsynced = $pdo->query(
        "SELECT id, email FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0 ORDER BY id ASC"
    )->fetchAll();

    $perfisPorDominio = [];
    foreach ($contasUnsynced as $c) {
        $domainEmail = strtolower(trim(explode('@', $c['email'])[1] ?? ''));
        $domName = strtolower(explode('.', $domainEmail)[0] ?? 'dollfinn');
        if (empty($domName)) $domName = 'dollfinn';
        $perfisPorDominio[$domName][] = $c['id'];
    }

    $totalPendente = count($contasUnsynced);
    echo "<p class='text-slate-400'>📊 Total de perfis pendentes de sync: <span class='font-bold text-white'>{$totalPendente}</span></p>";

    $lotesEnviados = 0;
    $hoje = date('Y-m-d');

    foreach ($perfisPorDominio as $domName => $idsDaZone) {
        $totalZone = count($idsDaZone);
        echo "<p class='text-slate-300 mt-2'>🔹 Domínio <span class='text-purple-400 font-bold'>{$domName}</span>: {$totalZone} pendentes</p>";

        if ($totalZone === 0) continue;

        // Forçar envio mesmo com menos de 50 (se forçado)
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM slack_lotes_count WHERE domain = ? AND type = 'perfil'");
        $stmtCount->execute([$domName]);
        $loteCount = (int) $stmtCount->fetchColumn();

        $startRange = ($loteCount * 50) + 1;
        $endRange = $startRange + $totalZone - 1; // Até onde realmente foi
        $loteText = "{$startRange} - {$endRange} perfis {$domName} (lote parcial)";

        echo "<p class='text-slate-400 pl-4'>→ Próximo lote: <span class='text-yellow-400'>{$loteText}</span></p>";

        $chSub = curl_init("https://slack.com/api/slackLists.items.create");
        curl_setopt($chSub, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chSub, CURLOPT_POST, true);
        curl_setopt($chSub, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token,
            "Content-Type: application/json; charset=utf-8"
        ]);
        curl_setopt($chSub, CURLOPT_POSTFIELDS, json_encode([
            "list_id" => $list_id,
            "parent_item_id" => $week_row_id,
            "initial_fields" => [
                [
                    "column_id" => $primary_col_id,
                    "rich_text" => buildRichText($loteText)
                ],
                [
                    "column_id" => "Col00",
                    "checkbox" => true
                ],
                [
                    "column_id" => "Col02",
                    "date" => [$hoje]
                ]
            ]
        ]));
        $subRes = json_decode(curl_exec($chSub), true);
        curl_close($chSub);

        if ($subRes && isset($subRes['ok']) && $subRes['ok']) {
            // Registrar lote no banco (conta como 1 lote para manter sequência)
            $pdo->prepare("INSERT INTO slack_lotes_count (list_id, week, type, domain) VALUES (?, ?, ?, ?)")
                ->execute([$list_id, $week_title, 'perfil', $domName]);

            // Marcar todas as contas como sincronizadas
            $in = str_repeat('?,', count($idsDaZone) - 1) . '?';
            $pdo->prepare("UPDATE contas SET slack_perfil_sync = 1 WHERE id IN ($in)")->execute($idsDaZone);

            echo "<p class='text-emerald-400 pl-4'>✅ Lote enviado e {$totalZone} conta(s) marcadas como sincronizadas!</p>";
            $lotesEnviados++;
        } else {
            $err = $subRes['error'] ?? 'Erro desconhecido';
            echo "<p class='text-red-400 pl-4'>❌ Falha ao enviar lote: <span class='font-mono'>{$err}</span></p>";
        }
    }

    echo "<hr class='border-slate-700 my-4'>";
    echo "<p class='text-xl font-bold text-emerald-400'>🎉 Concluído! {$lotesEnviados} lote(s) enviado(s) ao Slack.</p>";
    echo "<a href='slack.php' class='inline-block mt-4 px-5 py-2 bg-purple-600 hover:bg-purple-700 rounded-xl font-bold text-sm transition'>← Voltar ao Slack</a>";
    echo "</div></body></html>";
    exit;
}

// ========================
//  TELA DE DIAGNÓSTICO / CONFIRMAÇÃO
// ========================

// Buscar dados para exibir
$contasUnsynced = $pdo->query(
    "SELECT id, email FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0 ORDER BY id ASC"
)->fetchAll();

$perfisPorDominio = [];
foreach ($contasUnsynced as $c) {
    $domainEmail = strtolower(trim(explode('@', $c['email'])[1] ?? ''));
    $domName = strtolower(explode('.', $domainEmail)[0] ?? 'dollfinn');
    if (empty($domName)) $domName = 'dollfinn';
    $perfisPorDominio[$domName][] = $c['id'];
}

// Buscar contagem de lotes por domínio
$lotesInfo = [];
foreach (array_keys($perfisPorDominio) as $dom) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM slack_lotes_count WHERE domain = ? AND type = 'perfil'");
    $stmt->execute([$dom]);
    $count = (int) $stmt->fetchColumn();
    $lotesInfo[$dom] = $count;
}

$mesAtual = date('Y-m');
$stmtLista = $pdo->prepare("SELECT * FROM slack_listas WHERE mes = ?");
$stmtLista->execute([$mesAtual]);
$listaMes = $stmtLista->fetch();

$week_title = obterSemanaDoMes(time());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forçar Lote Slack - Diagnóstico</title>
    <script src="tailwind.js?v=1"></script>
    <script src="lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-3xl mx-auto px-4 mt-28 pb-16 space-y-6">

        <!-- Header -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-purple-900/40 rounded-2xl flex items-center justify-center">
                <i data-lucide="zap" class="w-7 h-7 text-purple-400"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight">Forçar Envio de Lote ao Slack</h1>
                <p class="text-sm text-slate-400">Envia o lote atual ao Slack mesmo que não tenha atingido 50 contas.</p>
            </div>
        </div>

        <!-- Diagnóstico Atual -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-800">
                <i data-lucide="activity" class="w-5 h-5 text-blue-400"></i>
                <h2 class="font-bold text-lg">Diagnóstico do Estado Atual</h2>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-slate-800/50 rounded-2xl p-4">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Mês Atual</div>
                    <div class="text-lg font-extrabold text-white"><?= htmlspecialchars($mesAtual) ?></div>
                </div>
                <div class="bg-slate-800/50 rounded-2xl p-4">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Semana Atual</div>
                    <div class="text-sm font-bold text-blue-400"><?= htmlspecialchars($week_title) ?></div>
                </div>
                <div class="bg-slate-800/50 rounded-2xl p-4">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Lista do Mês</div>
                    <?php if ($listaMes): ?>
                        <div class="text-xs font-mono text-emerald-400 truncate"><?= htmlspecialchars($listaMes['list_id']) ?></div>
                    <?php else: ?>
                        <div class="text-sm font-bold text-red-400">Não criada ainda</div>
                    <?php endif; ?>
                </div>
                <div class="bg-slate-800/50 rounded-2xl p-4">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Total Pendentes</div>
                    <div class="text-2xl font-extrabold <?= count($contasUnsynced) >= 50 ? 'text-emerald-400' : 'text-yellow-400' ?>"><?= count($contasUnsynced) ?></div>
                </div>
            </div>

            <!-- Detalhamento por Domínio -->
            <?php if (!empty($perfisPorDominio)): ?>
            <div class="space-y-3">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Por Domínio</div>
                <?php foreach ($perfisPorDominio as $dom => $ids): ?>
                    <?php
                        $count = count($ids);
                        $loteAtual = $lotesInfo[$dom] ?? 0;
                        $proximoStart = ($loteAtual * 50) + 1;
                        $proximoEnd = $proximoStart + $count - 1;
                    ?>
                    <div class="bg-slate-800/30 border border-slate-700/50 rounded-2xl p-4 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-purple-400">@<?= htmlspecialchars($dom) ?></div>
                            <div class="text-xs text-slate-400 mt-1">
                                Lotes já enviados: <span class="font-bold text-white"><?= $loteAtual ?></span>
                                &nbsp;·&nbsp;
                                Próximo lote: <span class="font-bold text-yellow-400"><?= $proximoStart ?> – <?= $proximoEnd ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-extrabold <?= $count >= 50 ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $count ?></div>
                            <div class="text-xs text-slate-400">pendentes</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="bg-emerald-950/20 border border-emerald-800/30 rounded-2xl p-4 text-emerald-400 text-sm font-semibold">
                    ✅ Nenhuma conta pendente de sincronização com o Slack.
                </div>
            <?php endif; ?>
        </div>

        <!-- Aviso e Botão de Confirmação -->
        <?php if (!empty($perfisPorDominio)): ?>
        <div class="bg-amber-950/20 border border-amber-700/40 rounded-3xl p-6 space-y-4">
            <div class="flex items-start gap-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5"></i>
                <div>
                    <div class="font-bold text-amber-300 text-sm">Atenção: Lote Parcial</div>
                    <p class="text-xs text-slate-400 mt-1">
                        Ao forçar, o lote será enviado com <strong class="text-white"><?= count($contasUnsynced) ?> contas</strong>
                        ao invés de 50. Isso irá registrar o lote no banco e marcar as contas como sincronizadas.
                        O próximo lote automático irá continuar a numeração normalmente a partir do ponto seguinte.
                    </p>
                </div>
            </div>

            <a href="?forcar=1&confirmar=1" 
               onclick="return confirm('Tem certeza que deseja forçar o envio do lote parcial (<?= count($contasUnsynced) ?> contas) para o Slack agora?')"
               class="flex items-center justify-center gap-2 w-full bg-purple-600 hover:bg-purple-700 active:scale-95 text-white font-extrabold py-3.5 px-6 rounded-2xl transition-all text-sm shadow-lg shadow-purple-900/40">
                <i data-lucide="send" class="w-4 h-4"></i>
                Confirmar e Forçar Envio Agora
            </a>
        </div>
        <?php endif; ?>

        <a href="slack.php" class="flex items-center gap-2 text-sm text-slate-400 hover:text-slate-200 transition font-semibold">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar para Integração Slack
        </a>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>
