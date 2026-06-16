<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

if (isFinanceiro()) {
    header("Location: relatorio.php");
    exit;
}

// Estatísticas Globais (Sem filtros)
$totalApps = $pdo->query("SELECT COUNT(*) FROM apps")->fetchColumn();
$appsAprovados = $pdo->query("SELECT COUNT(*) FROM apps WHERE status = 'aprovado'")->fetchColumn();
$appsAnalise = $pdo->query("SELECT COUNT(*) FROM apps WHERE status = 'analise'")->fetchColumn();
$appsRejeitados = $pdo->query("SELECT COUNT(*) FROM apps WHERE status = 'rejeitado'")->fetchColumn();
$appsOnline = $pdo->query("SELECT COUNT(*) FROM apps WHERE status_conexao = 'online'")->fetchColumn();
$appsCaiu = $pdo->query("SELECT COUNT(*) FROM apps WHERE status_conexao = 'caiu'")->fetchColumn();

// Filtros de busca e status
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$conexao_filter = trim($_GET['conexao'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(nome LIKE ? OR app_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== '') {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

if ($conexao_filter !== '') {
    $where[] = "status_conexao = ?";
    $params[] = $conexao_filter;
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Contagem total filtrada para paginação
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM apps $where_sql");
$stmtCount->execute($params);
$totalFiltered = $stmtCount->fetchColumn();

// Paginação
$limit = 50;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($page < 1) $page = 1;
$totalPages = max(1, ceil($totalFiltered / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

// Ordenação
$sort = $_GET['sort'] ?? 'id';
$dir = strtoupper($_GET['dir'] ?? 'DESC');
$colunasValidas = ['id', 'nome', 'app_id', 'status', 'status_conexao', 'data_verificacao'];
if (!in_array($sort, $colunasValidas)) $sort = 'id';
if ($dir !== 'ASC' && $dir !== 'DESC') $dir = 'DESC';
$ordemSQL = "ORDER BY {$sort} {$dir}";

$stmtApps = $pdo->prepare("SELECT * FROM apps $where_sql $ordemSQL LIMIT ? OFFSET ?");
// Bind limit and offset
$stmtApps->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmtApps->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
// Bind parameter values
for ($i = 0; $i < count($params); $i++) {
    $stmtApps->bindValue($i + 1, $params[$i]);
}
$stmtApps->execute();
$appsList = $stmtApps->fetchAll();

function linkSortApp($coluna, $nomeExibicao, $sortAtual, $dirAtual) {
    $novoDir = ($sortAtual === $coluna && $dirAtual === 'ASC') ? 'DESC' : 'ASC';
    $icone = ($sortAtual === $coluna) ? ($dirAtual === 'ASC' ? ' 🔼' : ' 🔽') : '';
    // Preservar filtros atuais ao mudar ordenação
    $query = http_build_query(array_merge($_GET, ['sort' => $coluna, 'dir' => $novoDir]));
    return "<a href='?{$query}' class='flex items-center gap-1 hover:text-blue-500 transition'>{$nomeExibicao}{$icone}</a>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplicativos Meta - Facebook Account Manager V4.3</title>
    <script src="tailwind.js"></script>
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
        .tr-hover:hover { background-color: rgba(59, 130, 246, 0.02); }
        .dark .tr-hover:hover { background-color: rgba(59, 130, 246, 0.05); }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 min-h-screen pb-24">

    <?php include 'navbar.php'; ?>

    <main class="max-w-[1600px] mx-auto px-4 mt-24 space-y-6">
        
        <!-- Mensagens de Alerta -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'erro_duplicado'): ?>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-sm font-bold text-red-800 dark:text-red-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                    Erro: Já existe um aplicativo cadastrado com este ID do App (App ID).
                </div>
            <?php elseif ($_GET['msg'] === 'erro_campos'): ?>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-sm font-bold text-red-800 dark:text-red-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                    Erro: Preencha todos os campos obrigatórios (Nome e ID do App).
                </div>
            <?php elseif ($_GET['msg'] === 'erro_token'): ?>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-sm font-bold text-red-800 dark:text-red-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                    Erro: O token do Facebook não pode ser vazio.
                </div>
            <?php elseif ($_GET['msg'] === 'erro_api_facebook'): ?>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-2xl text-sm font-bold text-red-800 dark:text-red-400 flex flex-col gap-1 shadow-sm">
                    <div class="flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                        <span>Erro na API do Facebook:</span>
                    </div>
                    <span class="text-xs font-mono pl-7 mt-1 font-medium bg-red-100/50 dark:bg-red-900/30 p-2 rounded-xl border border-red-200/50 text-red-700 dark:text-red-400"><?= htmlspecialchars($_GET['detalhe'] ?? '') ?></span>
                </div>
            <?php elseif ($_GET['msg'] === 'importacao_sucesso'): ?>
                <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/50 p-4 rounded-2xl text-sm font-bold text-emerald-800 dark:text-emerald-400 flex items-center gap-2 shadow-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
                    Sucesso: Importado <?= (int)$_GET['novos'] ?> novos aplicativos e atualizado <?= (int)$_GET['atualizados'] ?> aplicativos existentes.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Total de Apps</p>
                    <h3 class="text-2xl font-black mt-1"><?= $totalApps ?></h3>
                </div>
                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <i data-lucide="layout-grid" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between border-l-4 border-l-emerald-500">
                <div>
                    <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Online</p>
                    <h3 class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1"><?= $appsOnline ?></h3>
                </div>
                <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between border-l-4 border-l-rose-500">
                <div>
                    <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Caiu / Off</p>
                    <h3 class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1"><?= $appsCaiu ?></h3>
                </div>
                <div class="w-10 h-10 bg-rose-100 dark:bg-rose-900/30 rounded-xl flex items-center justify-center text-rose-600 dark:text-rose-400 animate-pulse">
                    <i data-lucide="x-circle" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Aprovados</p>
                    <h3 class="text-2xl font-black text-blue-600 mt-1"><?= $appsAprovados ?></h3>
                </div>
                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <i data-lucide="thumbs-up" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Em Análise</p>
                    <h3 class="text-2xl font-black text-amber-600 mt-1"><?= $appsAnalise ?></h3>
                </div>
                <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <i data-lucide="eye" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Rejeitados</p>
                    <h3 class="text-2xl font-black text-red-500 mt-1"><?= $appsRejeitados ?></h3>
                </div>
                <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center text-red-500">
                    <i data-lucide="thumbs-down" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <!-- Filters and Actions Bar -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <!-- Filters Form -->
            <form method="GET" class="flex flex-wrap items-center gap-3 flex-1">
                <!-- Search -->
                <div class="relative w-full md:w-80">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar app por nome ou ID..." 
                        class="w-full bg-slate-50 dark:bg-slate-800 text-xs pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 outline-none focus:border-blue-500 font-bold transition-all">
                </div>

                <!-- Status Meta -->
                <select name="status" onchange="this.form.submit()" class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold p-2.5 rounded-xl outline-none cursor-pointer">
                    <option value="">Status Meta: Todos</option>
                    <option value="analise" <?= $status_filter === 'analise' ? 'selected' : '' ?>>Em Análise</option>
                    <option value="aprovado" <?= $status_filter === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                    <option value="rejeitado" <?= $status_filter === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                </select>

                <!-- Conexao Status -->
                <select name="conexao" onchange="this.form.submit()" class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold p-2.5 rounded-xl outline-none cursor-pointer">
                    <option value="">Status Conexão: Todos</option>
                    <option value="online" <?= $conexao_filter === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="caiu" <?= $conexao_filter === 'caiu' ? 'selected' : '' ?>>Caiu / Desativado</option>
                </select>

                <!-- Limpar Filtros -->
                <?php if ($search !== '' || $status_filter !== '' || $conexao_filter !== ''): ?>
                    <a href="apps.php" class="text-xs font-bold text-red-500 hover:text-red-700 transition flex items-center gap-1">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i> Limpar Filtros
                    </a>
                <?php endif; ?>
            </form>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button type="button" id="btnVerifyAll" onclick="verificarTodosApps()" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-white px-5 py-2.5 rounded-xl font-bold flex items-center gap-2 transition active:scale-95 text-xs">
                    <i data-lucide="refresh-cw" id="iconVerifyAll" class="w-4 h-4"></i>
                    Verificar Todos
                </button>
                <button type="button" onclick="abrirModalImportar()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold flex items-center gap-2 shadow-lg shadow-emerald-600/20 transition active:scale-95 text-xs">
                    <i data-lucide="facebook" class="w-4 h-4"></i>
                    Conectar Facebook
                </button>
                <button type="button" onclick="abrirModalAdd()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-bold flex items-center gap-2 shadow-lg shadow-blue-600/20 transition active:scale-95 text-xs">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Adicionar Aplicativo
                </button>
            </div>

        </div>

        <!-- Table Container -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-500 font-bold uppercase text-[11px] tracking-widest">
                            <th class="p-4 w-16 text-center"><?= linkSortApp('id', 'ID', $sort, $dir) ?></th>
                            <th class="p-4"><?= linkSortApp('nome', 'Nome do Aplicativo', $sort, $dir) ?></th>
                            <th class="p-4"><?= linkSortApp('app_id', 'ID do App (Client ID)', $sort, $dir) ?></th>
                            <th class="p-4">Chave Secreta (App Secret)</th>
                            <th class="p-4 text-center"><?= linkSortApp('status', 'Status no Meta', $sort, $dir) ?></th>
                            <th class="p-4 text-center"><?= linkSortApp('status_conexao', 'Status de Conexão', $sort, $dir) ?></th>
                            <th class="p-4"><?= linkSortApp('data_verificacao', 'Última Validação', $sort, $dir) ?></th>
                            <th class="p-4">Observações</th>
                            <th class="p-4 text-right pr-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800" id="appsTableBody">
                        <?php foreach ($appsList as $app): ?>
                            <tr class="tr-hover group transition-colors" data-id="<?= $app['id'] ?>" data-json='<?= json_encode($app) ?>'>
                                <td class="p-4 text-center font-bold text-slate-400">#<?= $app['id'] ?></td>
                                <td class="p-4">
                                    <span class="font-extrabold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($app['nome']) ?></span>
                                </td>
                                <td class="p-4 font-mono text-xs">
                                    <div class="flex items-center gap-2 group/copy-id">
                                        <span class="text-blue-600 dark:text-blue-400 font-bold"><?= htmlspecialchars($app['app_id']) ?></span>
                                        <button onclick="copiar('<?= $app['app_id'] ?>', 'ID do App copiado')" class="opacity-0 group-hover/copy-id:opacity-100 transition">
                                            <i data-lucide="copy" class="w-3.5 h-3.5 text-slate-400 hover:text-blue-500"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="p-4 text-xs font-mono">
                                    <?php if (!empty($app['app_secret'])): ?>
                                        <div class="flex items-center gap-2 group/copy-sec">
                                            <span class="app-secret-masked">••••••••••••••••</span>
                                            <span class="app-secret-raw hidden"><?= htmlspecialchars($app['app_secret']) ?></span>
                                            <button onclick="toggleSecretVisibility(this)" class="text-slate-400 hover:text-blue-500 transition" title="Exibir Chave">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                            </button>
                                            <button onclick="copiar('<?= addslashes($app['app_secret']) ?>', 'App Secret copiada')" class="opacity-0 group-hover/copy-sec:opacity-100 transition">
                                                <i data-lucide="copy" class="w-3.5 h-3.5 text-slate-400 hover:text-blue-500"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Não informada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <form method="POST" action="processa.php" class="inline-block">
                                        <input type="hidden" name="acao" value="mudar_app_status_direto">
                                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                        <select name="novo_status" onchange="this.form.submit()" class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full border-2 cursor-pointer outline-none transition-all dark:bg-slate-900
                                            <?= $app['status'] === 'analise' ? 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800' : '' ?>
                                            <?= $app['status'] === 'aprovado' ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' : '' ?>
                                            <?= $app['status'] === 'rejeitado' ? 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800' : '' ?>">
                                            <option value="analise" <?= $app['status'] === 'analise' ? 'selected' : '' ?>>Em Análise</option>
                                            <option value="aprovado" <?= $app['status'] === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                            <option value="rejeitado" <?= $app['status'] === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="p-4 text-center connection-status-container">
                                    <div class="inline-flex items-center gap-1.5 font-bold uppercase text-[10px] px-2.5 py-1 rounded-full border 
                                        <?= $app['status_conexao'] === 'online' ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900' : 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900 animate-pulse' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $app['status_conexao'] === 'online' ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                                        <span class="connection-status-text"><?= $app['status_conexao'] === 'online' ? 'Online' : 'Caiu' ?></span>
                                    </div>
                                </td>
                                <td class="p-4 text-xs font-semibold text-slate-500 app-verified-date">
                                    <?= $app['data_verificacao'] ? date('d/m/Y H:i', strtotime($app['data_verificacao'])) : 'Nunca validado' ?>
                                </td>
                                <td class="p-4 text-xs font-medium text-slate-600 dark:text-slate-400 truncate max-w-[200px]">
                                    <?= htmlspecialchars($app['observacao'] ?? '') ?>
                                </td>
                                <td class="p-4 text-right pr-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="verificarApp(<?= $app['id'] ?>, this)" class="p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition btn-verificar-app" title="Testar Conexão">
                                            <i data-lucide="activity" class="w-4 h-4"></i>
                                        </button>
                                        <div class="w-px h-4 bg-slate-200 dark:bg-slate-700"></div>
                                        <button onclick="abrirModalEditarApp(<?= htmlspecialchars(json_encode($app)) ?>)" class="p-2 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition" title="Editar Aplicativo">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="abrirModal('Excluir este aplicativo permanentemente?', this.closest('tr').querySelector('.form-del'))" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition" title="Excluir Aplicativo">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                        
                                        <form method="POST" action="processa.php" class="form-del hidden">
                                            <input type="hidden" name="acao" value="del_app">
                                            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($appsList)): ?>
                            <tr>
                                <td colspan="9" class="p-12 text-center">
                                    <i data-lucide="layout-grid" class="w-12 h-12 text-slate-300 mx-auto mb-4"></i>
                                    <p class="text-slate-400 font-bold">Nenhum aplicativo cadastrado ou encontrado com os filtros selecionados.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Barra de Paginação -->
            <?php if ($totalPages > 1): ?>
                <div class="bg-slate-50 dark:bg-slate-800/20 px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div class="text-xs text-slate-500 font-semibold">
                        Mostrando página <span class="text-slate-800 dark:text-slate-200 font-extrabold"><?= $page ?></span> de <span class="text-slate-800 dark:text-slate-200 font-extrabold"><?= $totalPages ?></span> (Total: <?= $totalFiltered ?> apps filtrados)
                    </div>
                    <div class="flex items-center gap-1.5">
                        <!-- Botão Anterior -->
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="p-2 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-xl transition-all shadow-sm flex items-center justify-center text-slate-600 dark:text-slate-300">
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            </a>
                        <?php else: ?>
                            <div class="p-2 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-300 dark:text-slate-600 cursor-not-allowed">
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Números de Página -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="px-3.5 py-1.5 text-xs font-black rounded-xl border transition-all shadow-sm <?= $p === $page ? 'bg-blue-600 border-blue-600 text-white shadow-blue-600/10' : 'bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Botão Próximo -->
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="p-2 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-xl transition-all shadow-sm flex items-center justify-center text-slate-600 dark:text-slate-300">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </a>
                        <?php else: ?>
                            <div class="p-2 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-300 dark:text-slate-600 cursor-not-allowed">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal: Adicionar/Editar App -->
    <div id="modalApp" class="hidden fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-[100] items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-8 max-w-lg w-full shadow-2xl border border-slate-200 dark:border-slate-800 transform scale-90 transition-all duration-200" id="modalAppContent">
            <div class="flex items-center justify-between mb-6">
                <h3 id="modalAppTitle" class="text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span id="modalAppTitleIcon"><i data-lucide="plus-circle" class="w-6 h-6 text-blue-600"></i></span>
                    <span>Adicionar Aplicativo</span>
                </h3>
                <button type="button" onclick="fecharModalApp()" class="p-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="processa.php" class="space-y-4" id="formApp">
                <input type="hidden" name="acao" id="formAppAcao" value="add_app">
                <input type="hidden" name="app_id_db" id="formAppIdDb" value="">

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Nome do Aplicativo *</label>
                    <input type="text" name="nome" id="formAppNome" required placeholder="Ex: Meu App de Login Facebook" 
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-3.5 rounded-2xl outline-none focus:border-blue-500 font-bold transition">
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">ID do Aplicativo (App ID / Client ID) *</label>
                    <input type="text" name="app_id" id="formAppId" required placeholder="Ex: 582910481029481" 
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-3.5 rounded-2xl outline-none focus:border-blue-500 font-bold font-mono transition">
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Chave Secreta (App Secret) - Opcional</label>
                    <input type="text" name="app_secret" id="formAppSecret" placeholder="Opcional. Melhora a precisão do teste de conexão" 
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-3.5 rounded-2xl outline-none focus:border-blue-500 font-bold font-mono transition">
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Observações / Anotações</label>
                    <textarea name="observacao" id="formAppObs" placeholder="Ex: Utilizado na campanha X, vinculado à conta Y..." rows="2" 
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-3.5 rounded-2xl outline-none focus:border-blue-500 font-medium transition"></textarea>
                </div>

                <div class="flex gap-3 pt-4 border-t border-slate-100 dark:border-slate-855">
                    <button type="button" onclick="fecharModalApp()" class="flex-1 px-5 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-2xl font-bold transition">Cancelar</button>
                    <button type="submit" class="flex-1 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold shadow-lg shadow-blue-600/30 transition">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Conectar Facebook (Importar via Token) -->
    <div id="modalImportar" class="hidden fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-[100] items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-8 max-w-lg w-full shadow-2xl border border-slate-200 dark:border-slate-800 transform scale-90 transition-all duration-200" id="modalImportarContent">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="facebook" class="w-6 h-6 text-emerald-600"></i>
                    <span>Conectar Facebook</span>
                </h3>
                <button type="button" onclick="fecharModalImportar()" class="p-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="processa.php" class="space-y-4">
                <input type="hidden" name="acao" value="importar_apps_token">

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">User Access Token (Token de Acesso)</label>
                    <textarea name="token" required placeholder="Cole seu token de acesso de desenvolvedor aqui..." rows="4" 
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-3.5 rounded-2xl outline-none focus:border-emerald-500 font-mono text-xs transition"></textarea>
                </div>

                <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/50 p-4 rounded-2xl text-[11px] leading-relaxed space-y-2 text-slate-600 dark:text-slate-400 font-medium">
                    <p class="font-bold text-blue-800 dark:text-blue-400 flex items-center gap-1">
                        <i data-lucide="info" class="w-3.5 h-3.5"></i> Como obter seu token?
                    </p>
                    <p>
                        1. Acesse o <a href="https://developers.facebook.com/tools/explorer/" target="_blank" class="text-blue-600 hover:underline font-bold">Graph API Explorer</a> da Meta.<br>
                        2. Clique em <strong>Generate Access Token</strong>.<br>
                        3. Certifique-se de usar uma conta com acesso aos apps e cole o token acima.<br>
                        <em>Obs: O sistema importará ou atualizará todos os apps automaticamente!</em>
                    </p>
                </div>

                <div class="flex gap-3 pt-4 border-t border-slate-100 dark:border-slate-855">
                    <button type="button" onclick="fecharModalImportar()" class="flex-1 px-5 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-2xl font-bold transition">Cancelar</button>
                    <button type="submit" class="flex-1 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-bold shadow-lg shadow-emerald-600/30 transition">Importar Apps</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Layout de Confirmação Exclusão -->
    <div id="modalConfirmacao" class="hidden fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-[100] items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 p-8 rounded-[2rem] shadow-2xl max-w-sm w-full border border-slate-200 dark:border-slate-800 transform scale-90 transition-all">
            <div class="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-2xl flex items-center justify-center mb-6 mx-auto">
                <i data-lucide="alert-triangle" class="w-10 h-10"></i>
            </div>
            <h3 class="text-2xl font-black text-center mb-2">Tem certeza?</h3>
            <p id="textoModal" class="text-slate-500 dark:text-slate-400 text-center mb-8 font-medium"></p>
            <div class="flex gap-3">
                <button onclick="fecharModal()" class="flex-1 px-4 py-3 bg-slate-100 dark:bg-slate-800 rounded-2xl font-bold transition hover:bg-slate-200">Cancelar</button>
                <button onclick="confirmarModal()" class="flex-1 px-4 py-3 bg-red-600 text-white rounded-2xl font-bold shadow-lg shadow-red-600/30 transition hover:bg-red-700">Excluir</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-24 right-8 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl shadow-2xl font-bold flex items-center gap-3 transform translate-y-32 opacity-0 transition-all z-50">
        <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center text-white"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg"></span>
    </div>

    <script>
        lucide.createIcons();

        // ── Modal Confirmação ──────────────────────────────────────
        let formAlvo = null;
        function abrirModal(txt, form) {
            document.getElementById('textoModal').innerText = txt;
            const m = document.getElementById('modalConfirmacao');
            m.classList.remove('hidden'); m.classList.add('flex');
            setTimeout(() => m.children[0].classList.remove('scale-90'), 10);
            formAlvo = form;
        }
        function fecharModal() {
            const m = document.getElementById('modalConfirmacao');
            m.children[0].classList.add('scale-90');
            setTimeout(() => { m.classList.add('hidden'); m.classList.remove('flex'); }, 200);
            formAlvo = null;
        }
        function confirmarModal() { if(formAlvo) formAlvo.submit(); }

        // ── Modal Importar via Token ──────────────────────────────
        function abrirModalImportar() {
            const m = document.getElementById('modalImportar');
            m.classList.remove('hidden'); m.classList.add('flex');
            setTimeout(() => document.getElementById('modalImportarContent').classList.remove('scale-90'), 10);
            lucide.createIcons();
        }

        function fecharModalImportar() {
            const m = document.getElementById('modalImportar');
            document.getElementById('modalImportarContent').classList.add('scale-90');
            setTimeout(() => { m.classList.add('hidden'); m.classList.remove('flex'); }, 200);
        }

        // ── Modal Adicionar/Editar App ─────────────────────────────
        function abrirModalAdd() {
            document.getElementById('modalAppTitle').querySelector('span').innerText = "Adicionar Aplicativo";
            document.getElementById('modalAppTitleIcon').innerHTML = '<i data-lucide="plus-circle" class="w-6 h-6 text-blue-600"></i>';
            document.getElementById('formAppAcao').value = "add_app";
            document.getElementById('formAppIdDb').value = "";
            document.getElementById('formAppNome').value = "";
            document.getElementById('formAppId').value = "";
            document.getElementById('formAppSecret').value = "";
            document.getElementById('formAppObs').value = "";
            
            const m = document.getElementById('modalApp');
            m.classList.remove('hidden'); m.classList.add('flex');
            setTimeout(() => document.getElementById('modalAppContent').classList.remove('scale-90'), 10);
            lucide.createIcons();
        }

        function abrirModalEditarApp(app) {
            document.getElementById('modalAppTitle').querySelector('span').innerText = "Editar Aplicativo";
            document.getElementById('modalAppTitleIcon').innerHTML = '<i data-lucide="edit" class="w-6 h-6 text-blue-600"></i>';
            document.getElementById('formAppAcao').value = "edit_app";
            document.getElementById('formAppIdDb').value = app.id;
            document.getElementById('formAppNome').value = app.nome;
            document.getElementById('formAppId').value = app.app_id;
            document.getElementById('formAppSecret').value = app.app_secret || '';
            document.getElementById('formAppObs').value = app.observacao || '';
            
            const m = document.getElementById('modalApp');
            m.classList.remove('hidden'); m.classList.add('flex');
            setTimeout(() => document.getElementById('modalAppContent').classList.remove('scale-90'), 10);
            lucide.createIcons();
        }

        function fecharModalApp() {
            const m = document.getElementById('modalApp');
            document.getElementById('modalAppContent').classList.add('scale-90');
            setTimeout(() => { m.classList.add('hidden'); m.classList.remove('flex'); }, 200);
        }

        function toggleSecretVisibility(btn) {
            const container = btn.closest('div');
            const masked = container.querySelector('.app-secret-masked');
            const raw = container.querySelector('.app-secret-raw');
            const icon = btn.querySelector('i');
            
            if (masked.classList.contains('hidden')) {
                masked.classList.remove('hidden');
                raw.classList.add('hidden');
                btn.innerHTML = '<i data-lucide="eye" class="w-3.5 h-3.5"></i>';
            } else {
                masked.classList.add('hidden');
                raw.classList.remove('hidden');
                btn.innerHTML = '<i data-lucide="eye-off" class="w-3.5 h-3.5"></i>';
            }
            lucide.createIcons();
        }

        // Atualizar estilos e valor do select de status de aprovação de forma dinâmica
        function atualizarSelectStatusRow(row, status) {
            const selectStatus = row.querySelector('select[name="novo_status"]');
            if (selectStatus && status) {
                selectStatus.value = status;
                selectStatus.className = 'text-[10px] font-black uppercase px-3 py-1.5 rounded-full border-2 cursor-pointer outline-none transition-all dark:bg-slate-900';
                if (status === 'analise') {
                    selectStatus.classList.add('bg-amber-50', 'text-amber-600', 'border-amber-200', 'dark:bg-amber-900/20', 'dark:border-amber-800');
                } else if (status === 'aprovado') {
                    selectStatus.classList.add('bg-emerald-50', 'text-emerald-600', 'border-emerald-200', 'dark:bg-emerald-900/20', 'dark:border-emerald-800');
                } else if (status === 'rejeitado') {
                    selectStatus.classList.add('bg-rose-50', 'text-rose-600', 'border-rose-200', 'dark:bg-rose-900/20', 'dark:border-rose-800');
                }
            }
        }

        // ── Verificação de Status com AJAX ──────────────────────────
        function verificarApp(id, btn) {
            const row = btn.closest('tr');
            const icon = btn.querySelector('i');
            
            // Estado de carregamento
            btn.classList.add('pointer-events-none');
            icon.classList.add('animate-spin', 'text-blue-500');
            
            const containerStatus = row.querySelector('.connection-status-container');
            const textVerifiedDate = row.querySelector('.app-verified-date');
            
            const data = new FormData();
            data.append('acao', 'verificar_app_status');
            data.append('app_id', id);
            
            fetch('processa.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: data
            })
            .then(res => res.json())
            .then(res => {
                icon.classList.remove('animate-spin', 'text-blue-500');
                btn.classList.remove('pointer-events-none');
                
                if (res.sucesso) {
                    const statusConexao = res.status_conexao;
                    
                    // Atualiza a badge de status de conexão
                    if (statusConexao === 'online') {
                        containerStatus.innerHTML = `
                            <div class="inline-flex items-center gap-1.5 font-bold uppercase text-[10px] px-2.5 py-1 rounded-full border bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span class="connection-status-text">Online</span>
                            </div>
                        `;
                        mostrarToast("Aplicativo está ONLINE");
                    } else {
                        containerStatus.innerHTML = `
                            <div class="inline-flex items-center gap-1.5 font-bold uppercase text-[10px] px-2.5 py-1 rounded-full border bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900 animate-pulse">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                <span class="connection-status-text">Caiu</span>
                            </div>
                        `;
                        mostrarToast("ATENÇÃO: Aplicativo CAIU!");
                    }
                    
                    // Atualiza dinamicamente o status no Meta
                    atualizarSelectStatusRow(row, res.status);
                    
                    // Atualiza data de validação
                    if (textVerifiedDate) {
                        textVerifiedDate.innerText = res.data_verificacao;
                    }
                } else {
                    mostrarToast("Erro ao processar verificação");
                }
            })
            .catch(err => {
                icon.classList.remove('animate-spin', 'text-blue-500');
                btn.classList.remove('pointer-events-none');
                mostrarToast("Erro na requisição de rede");
                console.error(err);
            });
        }

        // Verificar todos os aplicativos em lote sequencialmente
        async function verificarTodosApps() {
            const buttons = document.querySelectorAll('.btn-verificar-app');
            if (buttons.length === 0) return;
            
            const btnAll = document.getElementById('btnVerifyAll');
            const iconAll = document.getElementById('iconVerifyAll');
            
            btnAll.classList.add('pointer-events-none', 'bg-blue-50', 'dark:bg-slate-800');
            iconAll.classList.add('animate-spin', 'text-blue-500');
            
            mostrarToast("Iniciando verificação de todos os apps...");
            
            for (let i = 0; i < buttons.length; i++) {
                const btn = buttons[i];
                const row = btn.closest('tr');
                const id = row.dataset.id;
                
                row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                
                await new Promise((resolve) => {
                    const icon = btn.querySelector('i');
                    btn.classList.add('pointer-events-none');
                    icon.classList.add('animate-spin', 'text-blue-500');
                    
                    const containerStatus = row.querySelector('.connection-status-container');
                    const textVerifiedDate = row.querySelector('.app-verified-date');
                    
                    const data = new FormData();
                    data.append('acao', 'verificar_app_status');
                    data.append('app_id', id);
                    
                    fetch('processa.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: data
                    })
                    .then(res => res.json())
                    .then(res => {
                        icon.classList.remove('animate-spin', 'text-blue-500');
                        btn.classList.remove('pointer-events-none');
                        
                        if (res.sucesso) {
                            const statusConexao = res.status_conexao;
                            if (statusConexao === 'online') {
                                containerStatus.innerHTML = `
                                    <div class="inline-flex items-center gap-1.5 font-bold uppercase text-[10px] px-2.5 py-1 rounded-full border bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span class="connection-status-text">Online</span>
                                    </div>
                                `;
                            } else {
                                containerStatus.innerHTML = `
                                    <div class="inline-flex items-center gap-1.5 font-bold uppercase text-[10px] px-2.5 py-1 rounded-full border bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900 animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        <span class="connection-status-text">Caiu</span>
                                    </div>
                                `;
                            }
                            
                            atualizarSelectStatusRow(row, res.status);
                            
                            if (textVerifiedDate) {
                                textVerifiedDate.innerText = res.data_verificacao;
                            }
                        }
                        resolve();
                    })
                    .catch(err => {
                        icon.classList.remove('animate-spin', 'text-blue-500');
                        btn.classList.remove('pointer-events-none');
                        resolve();
                    });
                });
                
                await new Promise(r => setTimeout(r, 200));
            }
            
            btnAll.classList.remove('pointer-events-none', 'bg-blue-50', 'dark:bg-slate-800');
            iconAll.classList.remove('animate-spin', 'text-blue-500');
            mostrarToast("Verificação concluída com sucesso!");
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }
    </script>
</body>
</html>
