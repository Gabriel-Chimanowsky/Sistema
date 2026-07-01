<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

if (isFinanceiro()) {
    header("Location: relatorio.php");
    exit;
}

// 1. Processamento AJAX/POST antes de qualquer HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'obter_itens') {
        header('Content-Type: application/json');
        $list_id = $_POST['list_id'] ?? '';
        
        $stmtConf = $pdo->query("SELECT slack_token FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();
        $token = $config['slack_token'] ?? '';
        
        if (empty($list_id) || empty($token)) {
            echo json_encode([]);
            exit;
        }
        
        $stmtL = $pdo->prepare("SELECT primary_col_id FROM slack_listas WHERE list_id = ?");
        $stmtL->execute([$list_id]);
        $primary_col_id = $stmtL->fetchColumn() ?: 'name';
        
        $chItems = curl_init("https://slack.com/api/slackLists.items.list");
        curl_setopt($chItems, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chItems, CURLOPT_POST, true);
        curl_setopt($chItems, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token,
            "Content-Type: application/json; charset=utf-8"
        ]);
        curl_setopt($chItems, CURLOPT_POSTFIELDS, json_encode([
            "list_id" => $list_id
        ]));
        $resJson = json_decode(curl_exec($chItems), true);
        curl_close($chItems);
        
        $root_items = [];
        if ($resJson && isset($resJson['ok']) && $resJson['ok']) {
            $items = $resJson['items'] ?? [];
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
                    if (empty($itemName)) {
                        $itemName = $item['id'];
                    }
                    $root_items[] = [
                        'id' => $item['id'],
                        'name' => $itemName
                    ];
                }
            }
        }
        echo json_encode($root_items);
        exit;
    }
    
    // POST - Criar Lista
    if ($_POST['acao'] === 'criar_lista') {
        $nome_lista = trim($_POST['nome_lista'] ?? '');
        $todo_mode = isset($_POST['todo_mode']) ? true : false;
        
        $stmtConf = $pdo->query("SELECT slack_token FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();
        $token = $config['slack_token'] ?? '';
        
        if (empty($nome_lista)) {
            header("Location: slack.php?msg=erro_nome_lista");
            exit;
        }
        if (empty($token)) {
            header("Location: slack.php?msg=erro_sem_token");
            exit;
        }
        
        $ch = curl_init("https://slack.com/api/slackLists.create");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token,
            "Content-Type: application/json; charset=utf-8"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "name" => $nome_lista,
            "todo_mode" => $todo_mode
        ]));
        $resJson = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        if ($resJson && isset($resJson['ok']) && $resJson['ok']) {
            $list_id = $resJson['list_id'];
            $primary_col_id = 'name';
            if (isset($resJson['list_metadata']['schema'])) {
                foreach ($resJson['list_metadata']['schema'] as $col) {
                    if (!empty($col['is_primary_column'])) {
                        $primary_col_id = $col['id'];
                        break;
                    }
                }
            }
            // Gravar no banco de dados local com mes = NULL
            $stmtInsert = $pdo->prepare("INSERT INTO slack_listas (mes, nome, list_id, primary_col_id) VALUES (NULL, ?, ?, ?)");
            $stmtInsert->execute([$nome_lista, $list_id, $primary_col_id]);
            header("Location: slack.php?msg=lista_criada_sucesso");
            exit;
        } else {
            $erroMsg = $resJson['error'] ?? 'Erro desconhecido';
            header("Location: slack.php?msg=erro_api_slack&detalhe=" . urlencode($erroMsg));
            exit;
        }
    }
    
    // POST - Adicionar Item
    if ($_POST['acao'] === 'adicionar_item') {
        $list_id = $_POST['list_id'] ?? '';
        $item_titulo = trim($_POST['item_titulo'] ?? '');
        $parent_item_id = trim($_POST['parent_item_id'] ?? '');
        $concluido = isset($_POST['concluido']) ? true : false;
        $data_item = trim($_POST['data_item'] ?? '');
        
        $stmtConf = $pdo->query("SELECT slack_token FROM configuracoes LIMIT 1");
        $config = $stmtConf->fetch();
        $token = $config['slack_token'] ?? '';
        
        if (empty($list_id) || empty($item_titulo)) {
            header("Location: slack.php?msg=erro_campos_item");
            exit;
        }
        if (empty($token)) {
            header("Location: slack.php?msg=erro_sem_token");
            exit;
        }
        
        // Obter a coluna primária da lista
        $stmtL = $pdo->prepare("SELECT primary_col_id FROM slack_listas WHERE list_id = ?");
        $stmtL->execute([$list_id]);
        $primary_col_id = $stmtL->fetchColumn() ?: 'name';
        
        $initial_fields = [
            [
                "column_id" => $primary_col_id,
                "rich_text" => buildRichText($item_titulo)
            ]
        ];
        
        // Adicionar campos extras para modo Tarefas (Col00 = Concluído, Col02 = Data)
        $initial_fields[] = [
            "column_id" => "Col00",
            "checkbox" => $concluido
        ];
        
        if (!empty($data_item)) {
            $initial_fields[] = [
                "column_id" => "Col02",
                "date" => [$data_item]
            ];
        }
        
        $postData = [
            "list_id" => $list_id,
            "initial_fields" => $initial_fields
        ];
        
        if (!empty($parent_item_id)) {
            $postData["parent_item_id"] = $parent_item_id;
        }
        
        $ch = curl_init("https://slack.com/api/slackLists.items.create");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token,
            "Content-Type: application/json; charset=utf-8"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        $resJson = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        if ($resJson && isset($resJson['ok']) && $resJson['ok']) {
            header("Location: slack.php?msg=item_adicionado_sucesso");
            exit;
        } else {
            $erroMsg = $resJson['error'] ?? 'Erro desconhecido';
            header("Location: slack.php?msg=erro_api_slack&detalhe=" . urlencode($erroMsg));
            exit;
        }
    }
}

// 2. Obter configurações e testar conexão Slack
$stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao FROM configuracoes LIMIT 1");
$config = $stmtConf->fetch();
$token = $config['slack_token'] ?? '';
$canal = $config['slack_canal_notificacao'] ?? '';

$conexao_ok = false;
$team_name = '';
$team_domain = '';
$team_id = '';
$bot_user = '';

if (!empty($token)) {
    $ch = curl_init("https://slack.com/api/auth.test");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json; charset=utf-8"
    ]);
    $authRes = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (isset($authRes['ok']) && $authRes['ok']) {
        $conexao_ok = true;
        $team_name = $authRes['team'] ?? '';
        $team_domain = parse_url($authRes['url'] ?? '', PHP_URL_HOST) ?? 'winup-workspace.slack.com';
        $team_id = $authRes['team_id'] ?? '';
        $bot_user = $authRes['user'] ?? '';
    }
}

// 3. Buscar listas do banco de dados local
$listas = $pdo->query("SELECT * FROM slack_listas ORDER BY criado_em DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integração Slack - Facebook Account Manager V4.3</title>
    <script src="tailwind.js?v=1"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            950: '#020617',
                        }
                    }
                }
            }
        }
    </script>
    <script src="lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="common.js?v=<?= time() ?>"></script>
    <style>
        body { font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        @media (prefers-color-scheme: dark) {
            .glass-nav { background: rgba(15, 23, 42, 0.95) !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 min-h-screen pb-24">

    <?php include 'navbar.php'; ?>

    <main class="max-w-[1600px] mx-auto px-4 mt-24 space-y-6">

        <!-- Mensagens de Alerta -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'erro_nome_lista'): ?>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-sm font-bold text-red-800 dark:text-red-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                    Erro: O nome da lista não pode ser vazio.
                </div>
            <?php elseif ($_GET['msg'] === 'erro_sem_token'): ?>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-sm font-bold text-red-800 dark:text-red-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                    Erro: O Token do Slack não está configurado. Configure em Ajustes.
                </div>
            <?php elseif ($_GET['msg'] === 'erro_campos_item'): ?>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-sm font-bold text-red-800 dark:text-red-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                    Erro: Preencha todos os campos obrigatórios (Lista e Título do Item).
                </div>
            <?php elseif ($_GET['msg'] === 'erro_api_slack'): ?>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-sm font-bold text-red-800 dark:text-red-400 flex flex-col gap-1 shadow-sm">
                    <div class="flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                        <span>Erro na API do Slack:</span>
                    </div>
                    <span class="text-xs font-mono pl-7 mt-1 font-medium bg-red-100/50 dark:bg-red-900/30 p-2 rounded-xl border border-red-200/50 text-red-700 dark:text-red-400"><?= htmlspecialchars($_GET['detalhe'] ?? '') ?></span>
                </div>
            <?php elseif ($_GET['msg'] === 'lista_criada_sucesso'): ?>
                <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/50 p-4 rounded-2xl text-sm font-bold text-emerald-800 dark:text-emerald-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
                    Sucesso: Nova lista criada e cadastrada no banco de dados local.
                </div>
            <?php elseif ($_GET['msg'] === 'item_adicionado_sucesso'): ?>
                <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/50 p-4 rounded-2xl text-sm font-bold text-emerald-800 dark:text-emerald-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
                    Sucesso: Novo item criado na lista do Slack com sucesso.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Slack Header & Status Card -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-2xl flex items-center justify-center text-purple-600 dark:text-purple-400">
                    <i data-lucide="slack" class="w-7 h-7"></i>
                </div>
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight">Painel de Integração do Slack</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie e envie itens ou crie listas personalizadas diretamente no Slack.</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <?php if ($conexao_ok): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        Conectado ao Slack
                    </span>
                    <div class="text-right text-xs">
                        <div class="font-bold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($team_name) ?></div>
                        <div class="text-slate-400">Bot: @<?= htmlspecialchars($bot_user) ?></div>
                    </div>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-500"></i>
                        Slack Desconectado
                    </span>
                    <a href="config.php" class="text-xs font-semibold text-blue-500 hover:underline">Configurar Token</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Card 1: Criar Lista Manual -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <i data-lucide="folder-plus" class="w-5 h-5 text-blue-500"></i>
                    <h2 class="font-bold text-lg">Criar Nova Lista (Manual)</h2>
                </div>
                <form action="slack.php" method="POST" class="space-y-4">
                    <input type="hidden" name="acao" value="criar_lista">
                    
                    <div>
                        <label for="nome_lista" class="block text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Nome da Lista</label>
                        <input type="text" id="nome_lista" name="nome_lista" placeholder="Ex: Metas Extras, Lançamentos Manuais..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 outline-none focus:border-blue-500 transition text-sm font-semibold" required>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="todo_mode" name="todo_mode" checked class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500 bg-slate-50 dark:bg-slate-950 dark:border-slate-800 cursor-pointer">
                        <label for="todo_mode" class="text-xs font-semibold text-slate-500 dark:text-slate-400 select-none cursor-pointer">Modo de Tarefas (todo_mode: adiciona colunas de status e data)</label>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl transition shadow-lg shadow-blue-600/20 text-sm active:scale-95 flex items-center justify-center gap-2">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i>
                        Criar Lista no Slack
                    </button>
                </form>
            </div>

            <!-- Card 2: Adicionar Item a uma Lista -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <i data-lucide="plus-circle" class="w-5 h-5 text-blue-500"></i>
                    <h2 class="font-bold text-lg">Adicionar Item Manualmente</h2>
                </div>
                <form action="slack.php" method="POST" class="space-y-4">
                    <input type="hidden" name="acao" value="adicionar_item">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="list_id" class="block text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Selecionar Lista</label>
                            <select id="list_id" name="list_id" onchange="carregarItensPai(this.value)" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 outline-none focus:border-blue-500 transition text-sm font-semibold cursor-pointer" required>
                                <option value="">-- Selecione uma lista --</option>
                                <?php foreach ($listas as $l): ?>
                                    <?php 
                                        $label = $l['nome'] ? $l['nome'] : "Automação Mês: " . $l['mes'];
                                    ?>
                                    <option value="<?= htmlspecialchars($l['list_id']) ?>"><?= htmlspecialchars($label) ?> (<?= htmlspecialchars($l['list_id']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="parent_item_id" class="block text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Item Pai (Subtarefa) - Opcional</label>
                            <select id="parent_item_id" name="parent_item_id" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 outline-none focus:border-blue-500 transition text-sm font-semibold cursor-pointer">
                                <option value="">-- Sem Item Pai (Item Raiz) --</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="item_titulo" class="block text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Título do Item</label>
                        <input type="text" id="item_titulo" name="item_titulo" placeholder="Ex: Lote extra de contas, Teste de bot, etc" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 outline-none focus:border-blue-500 transition text-sm font-semibold" required>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                        <div>
                            <label for="data_item" class="block text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Data Limite / Conclusão (Opcional)</label>
                            <input type="date" id="data_item" name="data_item" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 outline-none focus:border-blue-500 transition text-sm font-semibold cursor-pointer">
                        </div>
                        <div class="flex items-center gap-2 pt-6">
                            <input type="checkbox" id="concluido" name="concluido" class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500 bg-slate-50 dark:bg-slate-950 dark:border-slate-800 cursor-pointer">
                            <label for="concluido" class="text-sm font-semibold text-slate-600 dark:text-slate-300 select-none cursor-pointer">Marcar como Concluído</label>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl transition shadow-lg shadow-blue-600/20 text-sm active:scale-95 flex items-center justify-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Lançar Item no Slack Lists
                    </button>
                </form>
            </div>

        </div>

        <!-- Tabela: Listas Cadastradas no Banco -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="table" class="w-5 h-5 text-blue-500"></i>
                    <h2 class="font-bold text-lg">Histórico de Listas Registradas (Automação e Manual)</h2>
                </div>
                <span class="text-xs font-semibold text-slate-400"><?= count($listas) ?> Lista(s)</span>
            </div>
            
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 text-xs font-black uppercase tracking-wider bg-slate-50/50 dark:bg-slate-950/20">
                            <th class="px-6 py-4">Nome / Mês</th>
                            <th class="px-6 py-4">Slack List ID</th>
                            <th class="px-6 py-4">Coluna Primária ID</th>
                            <th class="px-6 py-4">Tipo</th>
                            <th class="px-6 py-4">Criado em</th>
                            <th class="px-6 py-4 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($listas) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center font-semibold text-slate-400 dark:text-slate-600 bg-white dark:bg-slate-900">
                                    <i data-lucide="folder-open" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                                    Nenhuma lista registrada no banco de dados local.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($listas as $l): ?>
                                <tr class="border-b border-slate-100 dark:border-slate-900 tr-hover transition">
                                    <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">
                                        <?php if ($l['nome']): ?>
                                            <?= htmlspecialchars($l['nome']) ?>
                                        <?php else: ?>
                                            📅 Gestão Mês: <?= htmlspecialchars($l['mes']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs"><?= htmlspecialchars($l['list_id']) ?></td>
                                    <td class="px-6 py-4 font-mono text-xs"><?= htmlspecialchars($l['primary_col_id']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($l['mes']): ?>
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 border border-blue-200 dark:border-blue-900">Automação</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800 dark:bg-purple-950/30 dark:text-purple-400 border border-purple-200 dark:border-purple-900">Manual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-xs text-slate-500 dark:text-slate-400">
                                        <?= date('d/m/Y H:i:s', strtotime($l['criado_em'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <?php if ($conexao_ok && $team_id): ?>
                                            <?php 
                                                $listLink = "https://{$team_domain}/lists/{$team_id}/{$l['list_id']}";
                                            ?>
                                            <a href="<?= $listLink ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-extrabold text-slate-700 dark:text-slate-300 transition-all">
                                                <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                                Abrir Slack
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs font-semibold text-slate-400">Sem link (Slack Off)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Inicializa ícones Lucide
        lucide.createIcons();

        // Carrega dinamicamente itens pai quando a lista selecionada for alterada
        async function carregarItensPai(listId) {
            const parentSelect = document.getElementById('parent_item_id');
            parentSelect.innerHTML = '<option value="">Carregando...</option>';
            parentSelect.disabled = true;

            if (!listId) {
                parentSelect.innerHTML = '<option value="">-- Sem Item Pai (Item Raiz) --</option>';
                parentSelect.disabled = false;
                return;
            }

            try {
                const formData = new FormData();
                formData.append('acao', 'obter_itens');
                formData.append('list_id', listId);

                const response = await fetch('slack.php', {
                    method: 'POST',
                    body: formData
                });
                const items = await response.json();
                
                parentSelect.innerHTML = '<option value="">-- Sem Item Pai (Item Raiz) --</option>';
                if (items && items.length > 0) {
                    items.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        parentSelect.appendChild(option);
                    });
                }
            } catch (e) {
                console.error(e);
                parentSelect.innerHTML = '<option value="">Erro ao carregar itens</option>';
            } finally {
                parentSelect.disabled = false;
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
