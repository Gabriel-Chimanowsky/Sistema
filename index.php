<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

// Sincronização automática com Slack Lists
sincronizarSlackTracker($pdo);

if (isFinanceiro()) {
    header("Location: relatorio.php");
    exit;
}

global $apiHerosms, $urlApi;
$urlApi = 'https://hero-sms.com/stubs/handler_api.php';

// Verificações de banco rápidas (migrações automáticas leves)
try { $pdo->query("SELECT data_autenticacao FROM contas LIMIT 1"); } catch (Exception $e) { $pdo->query("ALTER TABLE contas ADD COLUMN data_autenticacao DATETIME NULL"); }
try { $pdo->query("SELECT cookies FROM contas LIMIT 1"); } catch (Exception $e) { $pdo->query("ALTER TABLE contas ADD COLUMN cookies LONGTEXT NULL"); }
try { $pdo->query("SELECT nota_conta FROM contas LIMIT 1"); } catch (Exception $e) { $pdo->query("ALTER TABLE contas ADD COLUMN nota_conta TEXT DEFAULT NULL"); }
// Log imutável de tentativas de criação (nunca decrementado por deleções ou reversões)
try { $pdo->query("SELECT id FROM log_criacao_contas LIMIT 1"); } catch (Exception $e) {
    $pdo->query("CREATE TABLE IF NOT EXISTS log_criacao_contas (id INT AUTO_INCREMENT PRIMARY KEY, conta_id INT NOT NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_criado_em (criado_em)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Processamento de ações AJAX/Post direto na index (legado mantido para compatibilidade, mas limpo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'gerar_numero_sms') {
        $servico = $_POST['servico'] ?? 'fb'; 
        $pais = 73; 
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$urlApi}?action=getNumber&service={$servico}&country={$pais}&operator=any&api_key={$apiHerosms}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $resposta = trim(curl_exec($ch));
        curl_close($ch);
        
        if (strpos($resposta, 'ACCESS_NUMBER') !== false) {
            $partes = explode(':', $resposta);
            echo json_encode(['sucesso' => true, 'numero' => $partes[2], 'id_pedido' => $partes[1]]);
        } else {
            echo json_encode(['sucesso' => false, 'erro' => 'Erro HeroSMS: ' . $resposta]);
        }
        exit;
    }

    if ($_POST['acao'] === 'receber_codigo_sms') {
        $id_pedido = $_POST['id_pedido'] ?? '';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$urlApi}?action=getStatus&id={$id_pedido}&api_key={$apiHerosms}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $resposta = trim(curl_exec($ch));
        curl_close($ch);

        if (strpos($resposta, 'STATUS_OK') !== false) {
            $partes = explode(':', $resposta);
            echo json_encode(['sucesso' => true, 'codigo' => $partes[1]]);
        } else {
            echo json_encode(['sucesso' => false, 'erro' => $resposta]);
        }
        exit;
    }

    if ($_POST['acao'] === 'mudar_status_direto') {
        $conta_id = $_POST['conta_id'] ?? 0;
        $novo_status = $_POST['novo_status'] ?? 'pendente';
        $sql = "UPDATE contas SET status = ?";
        if ($novo_status === 'criada') $sql .= ", data_criacao = NOW()";
        elseif ($novo_status === 'autenticada') $sql .= ", data_autenticacao = NOW()";
        $sql .= " WHERE id = ?";
        $pdo->prepare($sql)->execute([$novo_status, $conta_id]);
        // Registrar tentativa no log imutável
        if ($novo_status === 'criada' && $conta_id) {
            $pdo->prepare("INSERT INTO log_criacao_contas (conta_id) VALUES (?)")->execute([$conta_id]);
        }
        header("Location: index.php"); exit;
    }

    if ($_POST['acao'] === 'editar_2fa_direto') {
        $codigo = trim($_POST['codigo_2fa'] ?? '');
        $conta_id = $_POST['conta_id'];
        
        $stmt = $pdo->prepare("SELECT status FROM contas WHERE id = ?");
        $stmt->execute([$conta_id]);
        $statusAtual = $stmt->fetchColumn();
        
        if ($codigo !== '' && $statusAtual !== 'exportado') {
            $pdo->prepare("UPDATE contas SET codigo_2fa = ?, status = 'autenticada', data_autenticacao = NOW() WHERE id = ?")->execute([$codigo, $conta_id]);
        } else {
            $pdo->prepare("UPDATE contas SET codigo_2fa = ? WHERE id = ?")->execute([$codigo, $conta_id]);
        }
        header("Location: index.php"); exit;
    }
}

// Stats
$totalContas = $pdo->query("SELECT COUNT(*) FROM contas")->fetchColumn();
// Criadas — usa log imutável (tentativas, nunca diminui mesmo com deleções ou reversões)
$contasCriadasSemana = $pdo->query("SELECT COUNT(*) FROM log_criacao_contas WHERE YEARWEEK(criado_em, 0) = YEARWEEK(NOW(), 0)")->fetchColumn();
$contasCriadasHoje   = $pdo->query("SELECT COUNT(*) FROM log_criacao_contas WHERE DATE(criado_em) = CURDATE()")->fetchColumn();
// Autenticadas — usa data_autenticacao (pode mudar se re-autenticar, ok)
$contasAutenticadasSemana = $pdo->query("SELECT COUNT(*) FROM contas WHERE YEARWEEK(data_autenticacao, 0) = YEARWEEK(NOW(), 0)")->fetchColumn();
$contasAutenticadasHoje   = $pdo->query("SELECT COUNT(*) FROM contas WHERE DATE(data_autenticacao) = CURDATE()")->fetchColumn();
$contasAutenticadas = $pdo->query("SELECT COUNT(*) FROM contas WHERE status IN ('autenticada', 'exportado')")->fetchColumn();

// Novas estatísticas solicitadas pelo usuário
$contasAdmin = $pdo->query("SELECT COUNT(*) FROM contas WHERE destinada_a IN (SELECT id FROM pessoas WHERE nome LIKE '%administrador%')")->fetchColumn();
$contasPessoal = $pdo->query("SELECT COUNT(*) FROM contas WHERE destinada_a IS NOT NULL AND destinada_a NOT IN (SELECT id FROM pessoas WHERE nome LIKE '%administrador%')")->fetchColumn();
$contasNaoProntas = $pdo->query("SELECT COUNT(*) FROM contas WHERE status IN ('pendente', 'criada')")->fetchColumn();
$contasLivres = $totalContas - $contasNaoProntas - $contasPessoal - $contasAdmin;
$contasLivresComBm = $pdo->query("SELECT COUNT(*) FROM contas WHERE (destinada_a IS NULL OR destinada_a = '' OR destinada_a = 0) AND status NOT IN ('pendente', 'criada') AND bm_criada = 1")->fetchColumn();
$contasFalhadas = $pdo->query("SELECT COUNT(*) FROM contas WHERE nome = 'User'")->fetchColumn();

// Buscar domínio configurado
$stmtConf = $pdo->query("SELECT email_dominio FROM configuracoes LIMIT 1");
$config = $stmtConf->fetch();
$email_dominio = $config['email_dominio'] ?? '@dollfinn';
if (strpos($email_dominio, '@') !== 0) {
    $email_dominio = '@' . $email_dominio;
}

// Contador de contas pendentes para o próximo lote Slack (acumulativo, por domínio principal)
$dominioConf = strtolower(ltrim($config['email_dominio'] ?? 'dollfinn', '@'));
$nomeDominioConf = strtolower(explode('.', $dominioConf)[0] ?? 'dollfinn');
if (empty($nomeDominioConf)) $nomeDominioConf = 'dollfinn';

$stmtNaoSync = $pdo->prepare("
    SELECT COUNT(*) FROM contas 
    WHERE status IN ('criada','autenticada','exportado') 
    AND slack_perfil_sync = 0 
    AND LOWER(SUBSTRING_INDEX(email, '@', -1)) LIKE ?
");
$stmtNaoSync->execute([$dominioConf . '%']);
$contasNaoSync = (int) $stmtNaoSync->fetchColumn();

// Quantas faltam para completar o próximo lote de 50
$faltamParaSlack = ($contasNaoSync === 0) ? 50 : (50 - ($contasNaoSync % 50));
if ($faltamParaSlack === 50 && $contasNaoSync > 0) {
    // Múltiplo exato de 50: o lote já deveria ter sido disparado
    $faltamParaSlack = 0;
}



// Listagem
$sort = $_GET['sort'] ?? 'id';
$dir = strtoupper($_GET['dir'] ?? 'DESC');
$colunasValidas = ['id', 'username', 'nome', 'sobrenome', 'email', 'status'];
if (!in_array($sort, $colunasValidas)) $sort = 'id';
if ($dir !== 'ASC' && $dir !== 'DESC') $dir = 'DESC';

$stmtPessoas = $pdo->query("SELECT * FROM pessoas ORDER BY nome ASC");
$pessoas = $stmtPessoas->fetchAll();

// Filtro de comentario
$filtroComentario = $_GET['comentario'] ?? '';

// Paginacao - limit vindo do GET
$limitOpcoes = [25, 50, 100, 200, 500];
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 50;
if (!in_array($limit, $limitOpcoes)) $limit = 50;

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($page < 1) $page = 1;

// WHERE dinamico
$whereClause = '';
if ($filtroComentario === 'com') {
    $whereClause = 'WHERE nota_conta IS NOT NULL AND nota_conta <> ""';
} elseif ($filtroComentario === 'sem') {
    $whereClause = 'WHERE (nota_conta IS NULL OR nota_conta = "")';
}

$totalContasQuery = $pdo->query("SELECT COUNT(*) FROM contas {$whereClause}")->fetchColumn();
$totalPages = max(1, ceil($totalContasQuery / $limit));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $limit;

$ordemSQL = "{$sort} {$dir}";
$stmtContas = $pdo->prepare("SELECT *, UNIX_TIMESTAMP(data_criacao) as criacao_unix, UNIX_TIMESTAMP(data_autenticacao) as auth_unix, UNIX_TIMESTAMP(data_exportado) as export_unix FROM contas {$whereClause} ORDER BY {$ordemSQL} LIMIT ? OFFSET ?");
$stmtContas->bindValue(1, $limit, PDO::PARAM_INT);
$stmtContas->bindValue(2, $offset, PDO::PARAM_INT);
$stmtContas->execute();
$contas = $stmtContas->fetchAll();
$tempoDb = time();

function buildQuery(array $extra = []): string {
    global $sort, $dir, $limit, $filtroComentario;
    $params = array_merge([
        'sort'       => $sort,
        'dir'        => $dir,
        'limit'      => $limit,
        'comentario' => $filtroComentario,
    ], $extra);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}

function linkSort(string $coluna, string $nomeExibicao, string $sortAtual, string $dirAtual): string {
    $novoDir = ($sortAtual === $coluna && $dirAtual === 'ASC') ? 'DESC' : 'ASC';
    $icone = ($sortAtual === $coluna) ? ($dirAtual === 'ASC' ? ' 🔼' : ' 🔽') : '';
    $q = buildQuery(['sort' => $coluna, 'dir' => $novoDir, 'page' => 1]);
    return "<a href='{$q}' class='flex items-center gap-1 hover:text-blue-500 transition'>{$nomeExibicao}{$icone}</a>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas - Facebook Account Manager V4.3</title>
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
            .bg-white\/95 { background-color: rgba(15, 23, 42, 0.98) !important; }
        }
        .tr-hover:hover { background-color: rgba(59, 130, 246, 0.02); }
        .dark .tr-hover:hover { background-color: rgba(59, 130, 246, 0.05); }

        /* ---- Mobile safe-area ---- */
        .footer-bar {
            padding-bottom: calc(env(safe-area-inset-bottom) + 0.5rem);
        }
        /* Prevent iOS tap highlight */
        button, a, select, input { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
        /* Prevent iOS zoom on inputs (needs font-size >= 16px) */
        input, select, textarea { font-size: 16px; }
        @media (min-width: 768px) {
            input, select, textarea { font-size: unset; }
        }

        /* ---- Mobile card list (visible only on < md) ---- */
        .mobile-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            transition: box-shadow 0.2s;
        }
        .mobile-card:active { box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        @media (prefers-color-scheme: dark) {
            .mobile-card { background: #0f172a; border-color: #1e293b; }
        }
        .mobile-card-header { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
        .mobile-card-body  { display: flex; flex-direction: column; gap: 0.375rem; }
        .mobile-card-row   { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; }
        .mobile-card-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; flex-shrink: 0; width: 2.5rem; }
        .mobile-card-value { font-weight: 600; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
        @media (prefers-color-scheme: dark) { .mobile-card-value { color: #cbd5e1; } }
        .mobile-card-footer { display: flex; align-items: center; gap: 0.5rem; border-top: 1px solid #f1f5f9; padding-top: 0.625rem; flex-wrap: wrap; }
        @media (prefers-color-scheme: dark) { .mobile-card-footer { border-color: #1e293b; } }

        /* hide desktop table on mobile, hide mobile cards on desktop */
        @media (max-width: 767px) {
            .desktop-table-wrap { display: none !important; }
            .mobile-cards-wrap  { display: flex !important; }
            main { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
        }
        @media (min-width: 768px) {
            .mobile-cards-wrap  { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 min-h-screen pb-24">

    <?php include 'navbar.php'; ?>

    <main class="max-w-[1600px] mx-auto px-4 mt-20 md:mt-24 space-y-4 md:space-y-6">
        
        <!-- Alerta de Contas Falhadas (User) -->
        <?php if ($contasFalhadas > 0): ?>
            <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 p-6 rounded-[2rem] flex flex-wrap items-center justify-between gap-4 text-sm font-bold text-amber-800 dark:text-amber-400 shadow-sm">
                <span class="flex items-center gap-3">
                    <i data-lucide="alert-triangle" class="w-6 h-6 text-amber-500"></i>
                    Atenção: Identificamos <?= $contasFalhadas ?> <?= $contasFalhadas == 1 ? 'conta que falhou' : 'contas que falharam' ?> na geração de dados aleatórios (ficando com o nome temporário "User").
                </span>
                <form method="POST" action="processa.php" class="inline">
                    <input type="hidden" name="acao" value="regerar_todas_falhadas">
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-2.5 rounded-2xl shadow-lg shadow-amber-600/20 hover:scale-105 active:scale-95 transition-all text-xs font-black uppercase">
                        Regerar Todas Agora
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4">
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Total de Contas</p>
                    <h3 class="text-3xl font-black mt-1"><?= $totalContas ?></h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Criadas na Semana</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasCriadasSemana ?></h3>
                    <p class="text-xs text-slate-400 mt-1 font-medium"><span class="text-green-600 dark:text-green-400 font-bold"><?= $contasCriadasHoje ?></span> hoje</p>
                    <?php if ($contasNaoSync > 0 && $faltamParaSlack > 0): ?>
                    <p class="text-xs text-slate-400 mt-1 font-medium">
                        <span style="color:#ef4444;font-weight:800;font-size:0.7rem;background:rgba(239,68,68,0.1);padding:1px 5px;border-radius:6px;border:1px solid rgba(239,68,68,0.25);" title="Faltam para notificar no Slack"><?= $faltamParaSlack ?> p/ Slack</span>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="relative w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-2xl flex items-center justify-center text-green-600 dark:text-green-400">
                    <i data-lucide="trending-up" class="w-6 h-6"></i>
                    <?php if ($contasNaoSync > 0 && $faltamParaSlack > 0): ?>
                    <span style="position:absolute;top:-5px;right:-5px;background:#ef4444;color:#fff;font-size:0.6rem;font-weight:900;min-width:16px;height:16px;border-radius:9999px;display:flex;align-items:center;justify-content:center;padding:0 3px;border:2px solid white;box-shadow:0 1px 4px rgba(239,68,68,0.5);line-height:1;" title="Faltam <?= $faltamParaSlack ?> contas para notificar no Slack"><?= $faltamParaSlack ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Autenticadas na Semana</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasAutenticadasSemana ?></h3>
                    <p class="text-xs text-slate-400 mt-1 font-medium"><span class="text-purple-600 dark:text-purple-400 font-bold"><?= $contasAutenticadasHoje ?></span> hoje</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-2xl flex items-center justify-center text-purple-600 dark:text-purple-400">
                    <i data-lucide="shield" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Autenticadas</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasAutenticadas ?></h3>
                </div>
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-2xl flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between col-span-2 lg:col-span-1">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Pendente / Não Pronta</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasNaoProntas ?></h3>
                </div>
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-2xl flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <i data-lucide="clock" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Uso por Pessoal</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasPessoal ?></h3>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-2xl flex items-center justify-center text-purple-600 dark:text-purple-400">
                    <i data-lucide="user" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Uso por Administrador</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasAdmin ?></h3>
                </div>
                <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                    <i data-lucide="shield" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Contas Livres</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasLivres ?></h3>
                </div>
                <div class="w-12 h-12 bg-sky-100 dark:bg-sky-900/30 rounded-2xl flex items-center justify-center text-sky-600 dark:text-sky-400">
                    <i data-lucide="unlock" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Livres com BM</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasLivresComBm ?></h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <i data-lucide="check-square" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <!-- Barra de Filtros e Controles -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm px-5 py-3 flex flex-wrap items-center gap-3">
            <span class="text-[11px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                Filtros
            </span>

            <!-- Filtro: Comentário -->
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1">
                <span class="text-[10px] font-black text-slate-400 uppercase px-2">Comentário:</span>
                <?php
                $qTodos = buildQuery(['comentario' => '', 'page' => 1]);
                $qCom   = buildQuery(['comentario' => 'com', 'page' => 1]);
                $qSem   = buildQuery(['comentario' => 'sem', 'page' => 1]);
                ?>
                <a href="<?= $qTodos ?>" class="px-3 py-1 rounded-lg text-[11px] font-black transition <?= $filtroComentario === '' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' ?>">Todos</a>
                <a href="<?= $qCom ?>" class="px-3 py-1 rounded-lg text-[11px] font-black transition flex items-center gap-1 <?= $filtroComentario === 'com' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' ?>">
                    <i data-lucide="message-square" class="w-3 h-3"></i> Com
                </a>
                <a href="<?= $qSem ?>" class="px-3 py-1 rounded-lg text-[11px] font-black transition flex items-center gap-1 <?= $filtroComentario === 'sem' ? 'bg-slate-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' ?>">
                    <i data-lucide="message-square-off" class="w-3 h-3"></i> Sem
                </a>
            </div>

            <div class="flex-1"></div>

            <!-- Por página -->
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Por página:</span>
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1">
                    <?php foreach ([25, 50, 100, 200, 500] as $op): ?>
                        <a href="<?= buildQuery(['limit' => $op, 'page' => 1]) ?>"
                           class="px-3 py-1 rounded-lg text-[11px] font-black transition <?= $limit === $op ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' ?>"
                           onclick="localStorage.setItem('dollfinn_limit','<?= $op ?>')">
                            <?= $op ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <span class="text-[11px] font-semibold text-slate-400">
                <?= $totalContasQuery ?> conta<?= $totalContasQuery != 1 ? 's' : '' ?> encontrada<?= $totalContasQuery != 1 ? 's' : '' ?>
            </span>
        </div>

        <!-- Desktop Table (hidden on mobile) -->
        <div class="desktop-table-wrap">
        <!-- Table Container -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-500 font-bold uppercase text-[10px] tracking-wider">
                            <th class="px-2 py-3.5 w-10 text-center"><input type="checkbox" id="selectAll" class="w-4 h-4 rounded-md border-slate-300 dark:border-slate-600 focus:ring-blue-500"></th>
                            <th class="px-2 py-3.5 w-14 text-center"><?= linkSort('id', 'ID', $sort, $dir) ?></th>
                            <th class="px-2 py-3.5"><?= linkSort('username', 'Usuário', $sort, $dir) ?></th>
                            <th class="px-2 py-3.5"><?= linkSort('nome', 'Nome', $sort, $dir) ?></th>
                            <th class="px-2 py-3.5"><?= linkSort('email', 'E-mail', $sort, $dir) ?></th>
                            <th class="px-2 py-3.5">Credenciais</th>
                            <th class="px-2 py-3.5">2FA & Cookies</th>
                            <th class="px-2 py-3.5 text-center"><?= linkSort('status', 'Status', $sort, $dir) ?></th>
                            <th class="px-2 py-3.5">Dono</th>
                            <th class="px-2 py-3.5 text-right pr-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($contas as $conta): 
                            $restante = 0;
                            if (!empty($conta['criacao_unix'])) $restante = 3600 - ($tempoDb - $conta['criacao_unix']);
                            $dias_vida = 0;
                            $tempo_referencia = !empty($conta['export_unix']) ? $conta['export_unix'] : (!empty($conta['auth_unix']) ? $conta['auth_unix'] : $conta['criacao_unix']);
                            if (in_array($conta['status'], ['autenticada', 'exportado']) && !empty($tempo_referencia)) {
                                $dias_vida = floor(($tempoDb - $tempo_referencia) / 86400);
                            }
                        ?>
                        <tr class="tr-hover group transition-colors <?= $conta['status'] === 'exportado' ? 'opacity-80 grayscale-[0.2]' : '' ?>" data-id="<?= $conta['id'] ?>" data-json='<?= json_encode($conta) ?>'>
                            <td class="px-2 py-3 text-center"><input type="checkbox" class="check-conta w-4 h-4 rounded-md border-slate-300 dark:border-slate-600"></td>
                            <td class="px-2 py-3 text-center font-bold text-slate-400">#<?= $conta['id'] ?></td>
                            <td class="px-2 py-3">
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800 dark:text-slate-200 max-w-[120px] truncate block" title="<?= htmlspecialchars($conta['username']) ?>"><?= htmlspecialchars($conta['username']) ?></span>
                                    <span class="text-xs text-slate-400 max-w-[120px] truncate block" title="<?= htmlspecialchars($email_dominio) ?>"><?= htmlspecialchars($email_dominio) ?></span>
                                </div>
                            </td>
                            <td class="px-2 py-3">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1.5 group/copy-nome">
                                        <span class="font-medium max-w-[120px] truncate block" title="<?= htmlspecialchars($conta['nome']) ?>"><?= htmlspecialchars($conta['nome']) ?></span>
                                        <button onclick="copiar('<?= addslashes($conta['nome']) ?>', 'Nome copiado')" class="opacity-0 group-hover/copy-nome:opacity-100 transition flex-shrink-0"><i data-lucide="copy" class="w-3 h-3 text-slate-400 hover:text-blue-500"></i></button>
                                    </div>
                                    <div class="flex items-center gap-1.5 group/copy-sobrenome">
                                        <span class="font-medium text-slate-500 max-w-[120px] truncate block" title="<?= htmlspecialchars($conta['sobrenome']) ?>"><?= htmlspecialchars($conta['sobrenome']) ?></span>
                                        <button onclick="copiar('<?= addslashes($conta['sobrenome']) ?>', 'Sobrenome copiado')" class="opacity-0 group-hover/copy-sobrenome:opacity-100 transition flex-shrink-0"><i data-lucide="copy" class="w-3 h-3 text-slate-400 hover:text-blue-500"></i></button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-3">
                                <div class="flex items-center gap-1.5 group/copy">
                                    <span class="font-mono text-xs text-blue-600 dark:text-blue-400 truncate max-w-[130px] inline-block align-middle" title="<?= htmlspecialchars($conta['email']) ?>"><?= htmlspecialchars($conta['email']) ?></span>
                                    <button onclick="copiar('<?= $conta['email'] ?>', 'E-mail copiado')" class="opacity-0 group-hover/copy:opacity-100 transition flex-shrink-0 align-middle"><i data-lucide="copy" class="w-3 h-3 text-slate-400 hover:text-blue-500"></i></button>
                                </div>
                            </td>
                            <td class="px-2 py-3">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1.5 text-xs font-mono group/copy-senha">
                                        <span class="text-slate-400 w-8 flex-shrink-0">SEN:</span>
                                        <span class="font-bold max-w-[90px] truncate block" title="<?= htmlspecialchars($conta['senha']) ?>"><?= htmlspecialchars($conta['senha']) ?></span>
                                        <button onclick="copiar('<?= addslashes($conta['senha']) ?>', 'Senha copiada')" class="opacity-0 group-hover/copy-senha:opacity-100 transition flex-shrink-0"><i data-lucide="copy" class="w-3 h-3 text-slate-400 hover:text-blue-500"></i></button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-3">
                                <div class="flex flex-col gap-1.5">
                                    <?php if ($conta['codigo_2fa']): ?>
                                        <div class="flex items-center gap-1.5 text-[10px] font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-lg max-w-[110px]">
                                            <span class="text-green-600 font-bold uppercase flex-shrink-0">2FA:</span>
                                            <span class="truncate w-8" title="Salvo">Salvo</span>
                                            <button onclick="colar2FADireto(<?= $conta['id'] ?>)" class="text-slate-500 hover:text-blue-500 ml-auto flex-shrink-0" title="Editar 2FA"><i data-lucide="edit-3" class="w-3 h-3"></i></button>
                                            <button onclick="copiar(JSON.parse(this.closest('tr').dataset.json).codigo_2fa, 'Código 2FA copiado')" class="flex-shrink-0"><i data-lucide="copy" class="w-3 h-3"></i></button>
                                        </div>
                                    <?php else: ?>
                                        <button onclick="colar2FADireto(<?= $conta['id'] ?>)" class="text-[10px] font-bold text-blue-500 hover:underline text-left">+ Colar 2FA</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($conta['cookies']): ?>
                                        <div class="flex items-center gap-1.5 text-[10px] font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-lg max-w-[110px]">
                                            <span class="text-indigo-600 font-bold uppercase flex-shrink-0">CK:</span>
                                            <span class="truncate w-8" title="Salvo">Salvo</span>
                                            <button onclick="colarCookiesDireto(<?= $conta['id'] ?>)" class="text-slate-500 hover:text-blue-500 ml-auto flex-shrink-0" title="Editar Cookies"><i data-lucide="edit-3" class="w-3 h-3"></i></button>
                                            <button onclick="copiar(JSON.parse(this.closest('tr').dataset.json).cookies, 'Cookies copiados')" class="flex-shrink-0"><i data-lucide="copy" class="w-3 h-3"></i></button>
                                        </div>
                                    <?php else: ?>
                                        <button onclick="colarCookiesDireto(<?= $conta['id'] ?>)" class="text-[10px] font-bold text-slate-500 hover:underline text-left">+ Colar Cookies</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-2 py-3 text-center">
                                <form method="POST" action="processa.php">
                                    <input type="hidden" name="acao" value="mudar_status_direto">
                                    <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                    <select name="novo_status" onchange="this.form.submit()" class="text-[10px] font-black uppercase px-2 py-1 rounded-full border cursor-pointer outline-none transition-all dark:bg-slate-900
                                        <?= $conta['status'] === 'pendente' ? 'bg-slate-100 text-slate-600 border-slate-200 dark:text-slate-400 dark:border-slate-700' : '' ?>
                                        <?= $conta['status'] === 'criada' ? 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800' : '' ?>
                                        <?= $conta['status'] === 'autenticada' ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' : '' ?>
                                        <?= $conta['status'] === 'exportado' ? 'bg-purple-50 text-purple-600 border-purple-200 dark:bg-purple-900/20 dark:border-purple-800' : '' ?>">
                                        <option value="pendente" <?= $conta['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="criada" <?= $conta['status'] === 'criada' ? 'selected' : '' ?>>Criada</option>
                                        <option value="autenticada" <?= $conta['status'] === 'autenticada' ? 'selected' : '' ?>>Autenticada</option>
                                        <option value="exportado" <?= $conta['status'] === 'exportado' ? 'selected' : '' ?>>Exportado</option>
                                    </select>
                                </form>
                                
                                <?php if ($conta['status'] === 'criada'): ?>
                                    <div class="countdown-timer text-[10px] mt-1.5 font-black text-amber-600 animate-pulse" data-restante="<?= $restante ?>"></div>
                                <?php elseif (in_array($conta['status'], ['autenticada', 'exportado'])): ?>
                                    <div class="text-[10px] mt-1 font-bold text-emerald-600">Vida: <?= $dias_vida ?> <?= $dias_vida == 1 ? 'dia' : 'dias' ?></div>
                                    
                                    <!-- Bloco de BM e Página -->
                                    <div class="mt-1.5 pt-1.5 border-t border-slate-100 dark:border-slate-800 flex flex-col gap-1">
                                        <!-- BM -->
                                        <div class="flex items-center justify-center gap-1">
                                            <?php if ($conta['bm_criada'] == 1): 
                                                $dias_bm = 0;
                                                if (!empty($conta['data_bm_criada'])) {
                                                    $dias_bm = floor((time() - strtotime($conta['data_bm_criada'])) / 86400);
                                                }
                                            ?>
                                                <div class="inline-flex items-center gap-1 text-[9px] font-black uppercase text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 pl-1.5 pr-0.5 py-0.5 rounded">
                                                    <i data-lucide="check-square" class="w-2.5 h-2.5"></i>
                                                    BM (<?= $dias_bm ?>d)
                                                    <form method="POST" action="processa.php" class="inline" onsubmit="event.preventDefault(); var f = this; showConfirmCard('Desfazer BM', 'Deseja desfazer a criação da BM desta conta?', 'Desfazer BM', 'bg-blue-600 hover:bg-blue-700 shadow-blue-600/30', () => f.submit());">
                                                        <input type="hidden" name="acao" value="remover_bm">
                                                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                                        <button type="submit" class="hover:bg-blue-200 dark:hover:bg-blue-800 p-0.5 rounded text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition" title="Desfazer BM">
                                                            <i data-lucide="x" class="w-2 h-2"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($dias_vida >= 7): ?>
                                                    <form method="POST" action="processa.php" class="inline-block">
                                                        <input type="hidden" name="acao" value="criar_bm">
                                                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-[9px] uppercase px-1.5 py-0.5 rounded shadow-sm transition active:scale-95 flex items-center gap-0.5">
                                                            <i data-lucide="plus-circle" class="w-2.5 h-2.5"></i>
                                                            Criar BM
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="inline-block text-[9px] font-bold text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-800/40 px-1 py-0.5 rounded" title="Aguardando maturação de 7 dias">
                                                        BM: Falta <?= 7 - $dias_vida ?>d
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Página -->
                                        <div class="flex items-center justify-center gap-1">
                                            <?php if (isset($conta['pagina_criada']) && $conta['pagina_criada'] == 1): 
                                                $dias_pag = 0;
                                                if (!empty($conta['data_pagina_criada'])) {
                                                    $dias_pag = floor((time() - strtotime($conta['data_pagina_criada'])) / 86400);
                                                }
                                            ?>
                                                <div class="inline-flex items-center gap-1 text-[9px] font-black uppercase text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 pl-1.5 pr-0.5 py-0.5 rounded">
                                                    <i data-lucide="check-square" class="w-2.5 h-2.5"></i>
                                                    Pág (<?= $dias_pag ?>d)
                                                    <form method="POST" action="processa.php" class="inline" onsubmit="event.preventDefault(); var f = this; showConfirmCard('Desfazer Página', 'Deseja desfazer a criação da Página desta conta?', 'Desfazer Página', 'bg-purple-600 hover:bg-purple-700 shadow-purple-600/30', () => f.submit());">
                                                        <input type="hidden" name="acao" value="remover_pagina">
                                                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                                        <button type="submit" class="hover:bg-purple-200 dark:hover:bg-purple-800 p-0.5 rounded text-purple-500 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300 transition" title="Desfazer Página">
                                                            <i data-lucide="x" class="w-2 h-2"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($dias_vida >= 7): ?>
                                                    <form method="POST" action="processa.php" class="inline-block">
                                                        <input type="hidden" name="acao" value="criar_pagina">
                                                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-[9px] uppercase px-1.5 py-0.5 rounded shadow-sm transition active:scale-95 flex items-center gap-0.5">
                                                            <i data-lucide="plus-circle" class="w-2.5 h-2.5"></i>
                                                            Criar Pág
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="inline-block text-[9px] font-bold text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-800/40 px-1 py-0.5 rounded" title="Aguardando maturação de 7 dias">
                                                        Pág: Falta <?= 7 - $dias_vida ?>d
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Meta for Developers -->
                                        <div class="flex items-center justify-center gap-1">
                                            <?php if (isset($conta['dev_criada']) && $conta['dev_criada'] == 1): 
                                                $dias_dev = 0;
                                                if (!empty($conta['data_dev_criada'])) {
                                                    $dias_dev = floor((time() - strtotime($conta['data_dev_criada'])) / 86400);
                                                }
                                            ?>
                                                <div class="inline-flex items-center gap-1 text-[9px] font-black uppercase text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 pl-1.5 pr-0.5 py-0.5 rounded">
                                                    <i data-lucide="code-2" class="w-2.5 h-2.5"></i>
                                                    Dev (<?= $dias_dev ?>d)
                                                    <form method="POST" action="processa.php" class="inline" onsubmit="event.preventDefault(); var f = this; showConfirmCard('Desfazer Dev', 'Deseja desfazer a criação do Meta for Developers desta conta?', 'Desfazer Dev', 'bg-orange-600 hover:bg-orange-700 shadow-orange-600/30', () => f.submit());">
                                                        <input type="hidden" name="acao" value="remover_dev">
                                                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                                        <button type="submit" class="hover:bg-orange-200 dark:hover:bg-orange-800 p-0.5 rounded text-orange-500 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300 transition" title="Desfazer Dev">
                                                            <i data-lucide="x" class="w-2.5 h-2.5"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($dias_vida >= 7): ?>
                                                    <form method="POST" action="processa.php" class="inline-block">
                                                        <input type="hidden" name="acao" value="criar_dev">
                                                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                                        <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-extrabold text-[9px] uppercase px-1.5 py-0.5 rounded shadow-sm transition active:scale-95 flex items-center gap-0.5">
                                                            <i data-lucide="plus-circle" class="w-2.5 h-2.5"></i>
                                                            Criar Dev
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="inline-block text-[9px] font-bold text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-800/40 px-1 py-0.5 rounded" title="Aguardando maturação de 7 dias">
                                                        Dev: Falta <?= 7 - $dias_vida ?>d
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-3">
                                <form method="POST" action="processa.php">
                                    <input type="hidden" name="acao" value="vincular_pessoa"><input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                    <select name="pessoa_id" onchange="this.form.submit()" class="bg-transparent dark:bg-slate-900 text-xs font-semibold outline-none border-b border-slate-200 dark:border-slate-700 cursor-pointer p-1 rounded max-w-[100px]">
                                        <option value="">-- Livre --</option>
                                        <?php foreach ($pessoas as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= ($conta['destinada_a'] == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td class="px-2 py-3 text-right pr-4">
                                <div class="flex items-center justify-end gap-1">
                                    <button onclick="copiarContaUnica(this)" class="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded transition" title="Copiar Perfil"><i data-lucide="clipboard-copy" class="w-3.5 h-3.5"></i></button>
                                    <div class="w-px h-3 bg-slate-200 dark:border-slate-700"></div>
                                    <button onclick="abrirNotaConta(<?= $conta['id'] ?>)" class="p-1 rounded transition <?= !empty($conta['nota_conta']) ? 'text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' ?>" title="<?= !empty($conta['nota_conta']) ? htmlspecialchars($conta['nota_conta']) : 'Adicionar comentário' ?>"><i data-lucide="message-square<?= !empty($conta['nota_conta']) ? '' : '-plus' ?>" class="w-3.5 h-3.5"></i></button>
                                    <button onclick="abrirModalEditarConta(<?= $conta['id'] ?>)" class="p-1 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition" title="Editar Conta"><i data-lucide="edit" class="w-3.5 h-3.5"></i></button>
                                    <button onclick="abrirModal('Regerar dados desta conta?', this.closest('tr').querySelector('.form-regerar'))" class="p-1 text-purple-500 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded transition"><i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i></button>
                                    <button onclick="abrirModal('Excluir conta permanentemente?', this.closest('tr').querySelector('.form-del'))" class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                    
                                    <form method="POST" action="processa.php" class="form-regerar hidden"><input type="hidden" name="acao" value="regerar_conta"><input type="hidden" name="conta_id" value="<?= $conta['id'] ?>"></form>
                                    <form method="POST" action="processa.php" class="form-del hidden"><input type="hidden" name="acao" value="del_conta"><input type="hidden" name="conta_id" value="<?= $conta['id'] ?>"></form>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Verification Row (SMS / E-mail) -->
                        <?php if ($conta['status'] !== 'autenticada' && $conta['status'] !== 'exportado'): ?>
                        <tr class="bg-slate-50/50 dark:bg-slate-800/20">
                            <td colspan="10" class="p-3 border-t border-dashed border-slate-200 dark:border-slate-800">
                                <div class="flex flex-wrap items-center gap-6 px-4 md:px-8">
                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded">SMS API</span>
                                        <select id="sms_servico_<?= $conta['id'] ?>" class="bg-white dark:bg-slate-800 text-xs font-bold p-1 rounded-lg border">
                                            <option value="fb">Facebook</option>
                                            <option value="ig">Instagram</option>
                                            <option value="go">Google</option>
                                        </select>
                                        <button onclick="apiGerarNumeroRow(<?= $conta['id'] ?>, this)" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-xs font-bold shadow-md shadow-blue-600/20 hover:scale-105 transition">Pedir Número</button>
                                        <input type="text" id="sms_numero_<?= $conta['id'] ?>" placeholder="---" readonly class="w-28 text-center text-xs font-mono bg-white dark:bg-slate-800 border p-1 rounded-lg font-bold">
                                        <button onclick="apiLerSmsRow(<?= $conta['id'] ?>, this)" class="bg-amber-500 text-white px-3 py-1 rounded-lg text-xs font-bold shadow-md shadow-amber-600/20 hover:scale-105 transition">Ler SMS</button>
                                        <input type="text" id="sms_codigo_<?= $conta['id'] ?>" placeholder="CÓD" readonly class="w-16 text-center text-xs font-mono bg-emerald-50 dark:bg-emerald-900/30 border-emerald-200 text-emerald-700 p-1 rounded-lg font-bold">
                                    </div>

                                    <div class="w-px h-6 bg-slate-200 dark:bg-slate-800"></div>

                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] font-black text-rose-500 uppercase tracking-widest bg-rose-50 dark:bg-rose-900/30 px-2 py-0.5 rounded">E-mail Hostinger</span>
                                        <button onclick="apiLerEmailRow(<?= $conta['id'] ?>, this)" class="bg-rose-500 text-white px-3 py-1 rounded-lg text-xs font-bold shadow-md shadow-rose-600/20 hover:scale-105 transition <?= ($conta['status'] === 'criada' && $restante > 0) ? 'opacity-50 pointer-events-none' : '' ?>">Ler E-mail</button>
                                        <input type="text" id="email_codigo_<?= $conta['id'] ?>" placeholder="CÓD" readonly class="w-16 text-center text-xs font-mono bg-rose-50 dark:bg-rose-900/30 border-rose-200 text-rose-700 p-1 rounded-lg font-bold">
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Barra de Paginação -->
            <?php if ($totalPages > 1): ?>
                <div class="bg-slate-50 dark:bg-slate-800/20 px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4 flex-wrap">
                    <div class="text-xs text-slate-500 font-semibold">
                        Página <span class="text-slate-800 dark:text-slate-200 font-extrabold"><?= $page ?></span>
                        de <span class="text-slate-800 dark:text-slate-200 font-extrabold"><?= $totalPages ?></span>
                        &nbsp;·&nbsp; <?= $totalContasQuery ?> conta<?= $totalContasQuery != 1 ? 's' : '' ?> total
                    </div>
                    <div class="flex items-center gap-1.5">
                        <!-- Botão Anterior -->
                        <?php if ($page > 1): ?>
                            <a href="<?= buildQuery(['page' => $page - 1]) ?>" class="p-2 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-xl transition-all shadow-sm flex items-center justify-center text-slate-600 dark:text-slate-300">
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
                        for ($pg = $startPage; $pg <= $endPage; $pg++):
                        ?>
                            <a href="<?= buildQuery(['page' => $pg]) ?>" class="px-3.5 py-1.5 text-xs font-black rounded-xl border transition-all shadow-sm <?= $pg === $page ? 'bg-blue-600 border-blue-600 text-white shadow-blue-600/10' : 'bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' ?>">
                                <?= $pg ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Botão Próximo -->
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= buildQuery(['page' => $page + 1]) ?>" class="p-2 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-xl transition-all shadow-sm flex items-center justify-center text-slate-600 dark:text-slate-300">
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
        </div> <!-- /table-container -->
        </div> <!-- /desktop-table-wrap -->

        <!-- Mobile Cards (hidden on desktop) -->
        <div class="mobile-cards-wrap flex-col gap-3">
            <?php foreach ($contas as $conta):
                $statusLabel = ['pendente'=>'Pendente','criada'=>'Criada','autenticada'=>'Autenticada','exportado'=>'Exportado'][$conta['status']] ?? $conta['status'];
                $statusColor = [
                    'pendente'    => 'bg-slate-100 text-slate-600',
                    'criada'      => 'bg-amber-50 text-amber-600',
                    'autenticada' => 'bg-emerald-50 text-emerald-600',
                    'exportado'   => 'bg-purple-50 text-purple-600',
                ][$conta['status']] ?? 'bg-slate-100 text-slate-600';
            ?>
            <div class="mobile-card" data-id="<?= $conta['id'] ?>" data-json='<?= json_encode($conta) ?>'>
                <div class="mobile-card-header">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" class="check-conta w-5 h-5 rounded-md border-slate-300 flex-shrink-0">
                        <span class="text-xs font-black text-slate-400">#<?= $conta['id'] ?></span>
                        <span class="font-bold text-sm text-slate-800 dark:text-slate-200 truncate max-w-[140px]"><?= htmlspecialchars($conta['nome']) ?> <?= htmlspecialchars($conta['sobrenome']) ?></span>
                    </div>
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full <?= $statusColor ?>"><?= $statusLabel ?></span>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">User</span>
                        <span class="mobile-card-value font-mono text-blue-600 dark:text-blue-400"><?= htmlspecialchars($conta['username']) ?></span>
                        <button onclick="copiar('<?= addslashes($conta['username']) ?>', 'Usuário copiado')" class="flex-shrink-0 p-1"><i data-lucide="copy" class="w-3.5 h-3.5 text-slate-400"></i></button>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Email</span>
                        <span class="mobile-card-value font-mono text-xs"><?= htmlspecialchars($conta['email']) ?></span>
                        <button onclick="copiar('<?= addslashes($conta['email']) ?>', 'E-mail copiado')" class="flex-shrink-0 p-1"><i data-lucide="copy" class="w-3.5 h-3.5 text-slate-400"></i></button>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Senha</span>
                        <span class="mobile-card-value font-mono"><?= htmlspecialchars($conta['senha']) ?></span>
                        <button onclick="copiar('<?= addslashes($conta['senha']) ?>', 'Senha copiada')" class="flex-shrink-0 p-1"><i data-lucide="copy" class="w-3.5 h-3.5 text-slate-400"></i></button>
                    </div>
                    <?php if ($conta['codigo_2fa']): ?>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">2FA</span>
                        <span class="mobile-card-value text-green-600 font-bold">Salvo</span>
                        <button onclick="copiar(JSON.parse(this.closest('[data-id]').dataset.json).codigo_2fa, '2FA copiado')" class="flex-shrink-0 p-1"><i data-lucide="copy" class="w-3.5 h-3.5 text-slate-400"></i></button>
                        <button onclick="colar2FADireto(<?= $conta['id'] ?>)" class="flex-shrink-0 p-1"><i data-lucide="edit-3" class="w-3.5 h-3.5 text-slate-400"></i></button>
                    </div>
                    <?php endif; ?>
                    <?php if ($conta['destinada_a']): ?>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Dono</span>
                        <?php foreach ($pessoas as $p): if ($p['id'] == $conta['destinada_a']): ?>
                        <span class="mobile-card-value"><?= htmlspecialchars($p['nome']) ?></span>
                        <?php endif; endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mobile-card-footer">
                    <!-- Status select -->
                    <form method="POST" action="processa.php" class="flex-1 min-w-0">
                        <input type="hidden" name="acao" value="mudar_status_direto">
                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                        <select name="novo_status" onchange="this.form.submit()" class="w-full text-xs font-bold px-2 py-2 rounded-xl border cursor-pointer outline-none <?= $statusColor ?> dark:bg-slate-800">
                            <option value="pendente" <?= $conta['status']==='pendente'?'selected':'' ?>>Pendente</option>
                            <option value="criada"   <?= $conta['status']==='criada'?'selected':'' ?>>Criada</option>
                            <option value="autenticada" <?= $conta['status']==='autenticada'?'selected':'' ?>>Autenticada</option>
                            <option value="exportado" <?= $conta['status']==='exportado'?'selected':'' ?>>Exportado</option>
                        </select>
                    </form>
                    <!-- Quick actions -->
                    <button onclick="copiarContaUnica(this)" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition" title="Copiar dados">
                        <i data-lucide="clipboard-copy" class="w-4 h-4 text-slate-500"></i>
                    </button>
                    <button onclick="abrirNotaConta(<?= $conta['id'] ?>)" class="p-2 rounded-xl transition <?= !empty($conta['nota_conta']) ? 'text-amber-500' : 'text-slate-400' ?>" title="Comentário">
                        <i data-lucide="message-square<?= !empty($conta['nota_conta']) ? '' : '-plus' ?>" class="w-4 h-4"></i>
                    </button>
                    <button onclick="abrirModalEditarConta(<?= $conta['id'] ?>)" class="p-2 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-xl transition" title="Editar">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                    </button>
                    <button onclick="abrirModal('Excluir conta permanentemente?', this.closest('[data-id]').querySelector('.form-del-mobile'))" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition" title="Excluir">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                    <form method="POST" action="processa.php" class="form-del-mobile hidden">
                        <input type="hidden" name="acao" value="del_conta">
                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Mobile Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between gap-2 py-2 px-1">
                <div class="text-xs text-slate-500 font-semibold">Pág <strong class="text-slate-800 dark:text-slate-200"><?= $page ?></strong> / <?= $totalPages ?></div>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= buildQuery(['page' => $page - 1]) ?>" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold flex items-center gap-1">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= buildQuery(['page' => $page + 1]) ?>" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold flex items-center gap-1">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div> <!-- /mobile-cards-wrap -->
    </main>

    <!-- Footer Action Bar -->
    <div class="footer-bar fixed bottom-0 left-0 w-full bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 shadow-2xl z-40">
        <div class="max-w-[1600px] mx-auto px-4">

            <div id="barraLote" class="hidden py-3 border-b border-slate-200 dark:border-slate-800">
                <div class="flex flex-wrap items-center gap-2">
                    <span id="loteContador" class="text-xs font-black text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-full">0 selecionadas</span>
                    
                    <!-- Mudar Status em Lote -->
                    <form id="formStatusMassa" method="POST" action="processa.php" class="flex items-center gap-1">
                        <input type="hidden" name="acao" value="mudar_status_massa">
                        <input type="hidden" name="ids" id="idsStatusMassa">
                        <select name="novo_status" id="selectStatusMassa" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold rounded-lg px-2 py-1.5 outline-none cursor-pointer">
                            <option value="pendente">Pendente</option>
                            <option value="criada">Criada</option>
                            <option value="autenticada">Autenticada</option>
                            <option value="exportado">Exportado</option>
                        </select>
                        <button type="button" onclick="submitLoteComIds('formStatusMassa','idsStatusMassa')" class="bg-slate-700 dark:bg-slate-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:scale-105 transition">
                            <i data-lucide="check-circle" class="w-3 h-3 inline mr-1"></i>Status
                        </button>
                    </form>

                    <div class="w-px h-6 bg-slate-200 dark:bg-slate-700"></div>

                    <!-- Mudar Dono em Lote -->
                    <form id="formDonoMassa" method="POST" action="processa.php" class="flex items-center gap-1">
                        <input type="hidden" name="acao" value="vincular_pessoa_massa">
                        <input type="hidden" name="ids" id="idsDonoMassa">
                        <select name="pessoa_id" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold rounded-lg px-2 py-1.5 outline-none cursor-pointer">
                            <option value="">-- Livre --</option>
                            <?php foreach ($pessoas as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="submitLoteComIds('formDonoMassa','idsDonoMassa')" class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:scale-105 transition">
                            <i data-lucide="user-check" class="w-3 h-3 inline mr-1"></i>Dono
                        </button>
                    </form>

                    <div class="w-px h-6 bg-slate-200 dark:bg-slate-700"></div>

                    <!-- Regerar em Lote -->
                    <form id="formRegerarMassa" method="POST" action="processa.php" class="flex items-center">
                        <input type="hidden" name="acao" value="regerar_massa">
                        <input type="hidden" name="ids" id="idsRegerarMassa">
                        <button type="button" onclick="confirmarLote('Regerar dados das contas selecionadas?','formRegerarMassa','idsRegerarMassa')" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:scale-105 transition flex items-center gap-1">
                            <i data-lucide="refresh-cw" class="w-3 h-3"></i>Regerar
                        </button>
                    </form>

                    <div class="w-px h-6 bg-slate-200 dark:bg-slate-700"></div>

                    <!-- Deletar em Lote -->
                    <form id="formDelMassa" method="POST" action="processa.php" class="flex items-center">
                        <input type="hidden" name="acao" value="del_massa">
                        <input type="hidden" name="ids" id="idsDelMassa">
                        <button type="button" onclick="confirmarLote('Excluir permanentemente as contas selecionadas?','formDelMassa','idsDelMassa')" class="bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:scale-105 transition flex items-center gap-1">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>Excluir
                        </button>
                    </form>

                    <div class="w-px h-6 bg-slate-200 dark:bg-slate-700"></div>

                    <!-- Exportar em Lote -->
                    <form id="formExportMassa" method="POST" action="processa.php" class="flex items-center">
                        <input type="hidden" name="acao" value="exportar_csv">
                        <input type="hidden" name="ids" id="idsExportMassa">
                        <button type="button" onclick="submitLoteComIds('formExportMassa','idsExportMassa')" class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:scale-105 transition flex items-center gap-1">
                            <i data-lucide="download" class="w-3 h-3"></i>Exportar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Barra principal de geracao -->
            <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                <div class="hidden sm:flex items-center gap-3 text-slate-400 font-bold text-xs uppercase tracking-widest">
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                    Gerar Nova Conta
                </div>

                <form method="POST" action="processa.php" class="flex flex-wrap items-center gap-2 flex-1">
                    <input type="hidden" name="acao" value="gerar_conta">
                    
                    <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">
                        <select name="genero" id="genSelect" onchange="saveSettings()" class="bg-white dark:bg-slate-900 border-none rounded-lg px-2 py-1.5 text-xs font-bold outline-none cursor-pointer shadow-sm">
                            <option value="homem">Homem</option>
                            <option value="mulher">Mulher</option>
                        </select>
                        <select name="pais" id="paisSelect" onchange="saveSettings()" class="bg-white dark:bg-slate-900 border-none rounded-lg px-2 py-1.5 text-xs font-bold outline-none cursor-pointer shadow-sm">
                            <option value="br">BR</option>
                            <option value="us">EUA</option>
                        </select>
                    </div>

                    <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl px-3 py-1.5 gap-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Qtd:</span>
                        <input type="number" name="quantidade" value="1" min="1" max="200" class="bg-transparent w-10 text-sm font-bold outline-none text-center">
                    </div>

                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-xl font-black text-sm shadow-xl shadow-blue-600/30 transition-all hover:scale-105 active:scale-95 flex-shrink-0">
                        GERAR
                    </button>
                </form>

                <div class="hidden lg:block text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                    Facebook Account Manager v4.3
                </div>
            </div>
        </div>
    </div>

    <!-- Popover Global de Nota (único, fora da tabela) -->
    <div id="nota-popover-global" class="hidden fixed z-[9999] bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-72" style="pointer-events:auto">
        <p class="text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Comentário da Conta</p>
        <form method="POST" action="processa.php" class="space-y-2">
            <input type="hidden" name="acao" value="salvar_nota_conta">
            <input type="hidden" name="conta_id" id="nota-global-conta-id">
            <textarea name="nota_conta" id="nota-global-textarea" rows="3" placeholder="Ex: bm tanana feita, sem cookie..." class="w-full text-xs p-2 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-900 outline-none focus:border-blue-500 resize-none transition"></textarea>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-1.5 rounded-xl text-xs font-bold transition">Salvar</button>
                <button type="button" onclick="fecharNotaConta()" class="px-3 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 rounded-xl text-xs font-bold transition">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- UI Components -->
    <div id="toast" class="fixed bottom-24 right-8 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl shadow-2xl font-bold flex items-center gap-3 transform translate-y-32 opacity-0 transition-all z-50">
        <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center text-white"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg"></span>
    </div>

    <div id="modalConfirmacao" class="hidden fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-[100] items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 p-8 rounded-[2rem] shadow-2xl max-w-sm w-full border border-slate-200 dark:border-slate-800 transform scale-90 transition-all">
            <div class="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-2xl flex items-center justify-center mb-6 mx-auto">
                <i data-lucide="alert-triangle" class="w-10 h-10"></i>
            </div>
            <h3 class="text-2xl font-black text-center mb-2">Confirmação</h3>
            <p id="textoModal" class="text-slate-500 dark:text-slate-400 text-center mb-8 font-medium"></p>
            <div class="flex gap-3">
                <button onclick="fecharModal()" class="flex-1 px-4 py-3 bg-slate-100 dark:bg-slate-800 rounded-2xl font-bold transition hover:bg-slate-200">Cancelar</button>
                <button onclick="confirmarModal()" class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-2xl font-bold shadow-lg shadow-blue-600/30 transition hover:bg-blue-700">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- Modal Editar Conta -->
    <div id="modalEditarConta" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="modalEditarContaCard">
            <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-lg font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <i data-lucide="edit" class="w-5 h-5 text-blue-500"></i> Editar Conta <span id="editContaIdText" class="text-blue-500"></span>
                </h3>
                <button type="button" onclick="fecharModalEditarConta()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 bg-slate-100 dark:bg-slate-800 rounded-xl transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form method="POST" action="processa.php" class="p-6 space-y-4">
                <input type="hidden" name="acao" value="editar_conta_completa">
                <input type="hidden" name="conta_id" id="editContaId">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Nome</label>
                        <input type="text" name="nome" id="editContaNome" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Sobrenome</label>
                        <input type="text" name="sobrenome" id="editContaSobrenome" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 transition">
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-500 uppercase">E-mail</label>
                    <input type="text" name="email" id="editContaEmail" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Usuário</label>
                        <input type="text" name="username" id="editContaUsername" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Senha</label>
                        <input type="text" name="senha" id="editContaSenha" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 transition">
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-500 uppercase">2FA</label>
                    <input type="text" name="codigo_2fa" id="editConta2fa" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 transition">
                </div>
                
                <div class="space-y-1 pt-2">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-blue-600/20 transition-all active:scale-95">
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            const msg = urlParams.get('msg');
            if (msg === 'ok') {
                setTimeout(() => mostrarToast('Ação realizada com sucesso!'), 300);
            } else if (msg === 'erro_email_duplicado') {
                setTimeout(() => mostrarToast('Erro: Este e-mail já está cadastrado em outra conta.', 'error'), 300);
            }
            
            // Limpar o parâmetro msg da URL para evitar reapresentar o toast ao atualizar a página
            urlParams.delete('msg');
            const newSearch = urlParams.toString();
            const newUrl = window.location.pathname + (newSearch ? '?' + newSearch : '') + window.location.hash;
            window.history.replaceState({}, '', newUrl);
        }

        // ── Scroll Restore (Gerenciado Globalmente no common.js) ──────

        function abrirModalEditarConta(id) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (!row) return;
            const data = JSON.parse(row.dataset.json);
            
            document.getElementById('editContaId').value = data.id;
            document.getElementById('editContaIdText').innerText = `#${data.id}`;
            document.getElementById('editContaNome').value = data.nome || '';
            document.getElementById('editContaSobrenome').value = data.sobrenome || '';
            document.getElementById('editContaEmail').value = data.email || '';
            document.getElementById('editContaUsername').value = data.username || '';
            document.getElementById('editContaSenha').value = data.senha || '';
            document.getElementById('editConta2fa').value = data.codigo_2fa || '';
            
            const modal = document.getElementById('modalEditarConta');
            const card = document.getElementById('modalEditarContaCard');
            
            modal.classList.remove('hidden');
            void modal.offsetWidth; // Force reflow
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
            
            lucide.createIcons();
        }

        function fecharModalEditarConta() {
            const modal = document.getElementById('modalEditarConta');
            const card = document.getElementById('modalEditarContaCard');
            
            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // ── Configurações persistentes ────────────────────────────────
        function saveSettings() {
            localStorage.setItem('dollfinn_gen_genero', document.getElementById('genSelect').value);
            localStorage.setItem('dollfinn_gen_pais', document.getElementById('paisSelect').value);
        }
        function loadSettings() {
            const g = localStorage.getItem('dollfinn_gen_genero');
            const p = localStorage.getItem('dollfinn_gen_pais');
            if(g) document.getElementById('genSelect').value = g;
            if(p) document.getElementById('paisSelect').value = p;
        }
        loadSettings();

        // ── Persistência de itens por página ─────────────────────────
        // Se a URL não tem &limit=, aplica o valor salvo no localStorage
        (function() {
            const params = new URLSearchParams(window.location.search);
            if (!params.has('limit')) {
                const saved = localStorage.getItem('dollfinn_limit');
                const validos = ['25','50','100','200','500'];
                if (saved && validos.includes(saved) && saved !== '50') {
                    params.set('limit', saved);
                    window.location.replace('?' + params.toString());
                }
            }
        })();

        // ── Ações em Lote ─────────────────────────────────────────────
        function getSelecionados() {
            return [...document.querySelectorAll('.check-conta:checked')]
                .map(c => c.closest('tr[data-id]')?.dataset.id)
                .filter(Boolean);
        }

        function atualizarBarraLote() {
            const ids = getSelecionados();
            const barra = document.getElementById('barraLote');
            const contador = document.getElementById('loteContador');
            if (ids.length > 0) {
                barra.classList.remove('hidden');
                contador.textContent = `${ids.length} selecionada${ids.length > 1 ? 's' : ''}`;
            } else {
                barra.classList.add('hidden');
            }
        }

        // Preenche o campo de ids e submete o form (para ações sem confirmação)
        function submitLoteComIds(formId, idsFieldId) {
            const ids = getSelecionados();
            if (ids.length === 0) return mostrarToast('Selecione ao menos uma conta');
            document.getElementById(idsFieldId).value = ids.join(',');
            document.getElementById(formId).submit();
        }

        // Preenche ids e abre modal de confirmação antes de submeter
        function confirmarLote(txt, formId, idsFieldId) {
            const ids = getSelecionados();
            if (ids.length === 0) return mostrarToast('Selecione ao menos uma conta');
            document.getElementById(idsFieldId).value = ids.join(',');
            abrirModal(txt, document.getElementById(formId));
        }

        // Observar mudanças nos checkboxes
        document.addEventListener('change', e => {
            if (e.target.classList.contains('check-conta') || e.target.id === 'selectAll') {
                atualizarBarraLote();
            }
        });

        const smsPedidos = {};

        // SMS API
        async function apiGerarNumeroRow(contaId, btn) {
            const servico = document.getElementById('sms_servico_' + contaId).value;
            const original = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
            lucide.createIcons();
            
            try {
                const fd = new FormData(); fd.append('acao', 'gerar_numero_sms'); fd.append('servico', servico);
                const res = await fetch(window.location.href, { method: 'POST', body: fd });
                const data = await res.json();
                if(data.sucesso) {
                    document.getElementById('sms_numero_' + contaId).value = data.numero;
                    smsPedidos[contaId] = data.id_pedido;
                    mostrarToast('Número gerado com sucesso!');
                } else mostrarToast(data.erro);
            } catch (e) { mostrarToast('Erro na API HeroSMS'); }
            btn.innerHTML = original; lucide.createIcons();
        }

        async function apiLerSmsRow(contaId, btn) {
            const pid = smsPedidos[contaId];
            if(!pid) return mostrarToast('Peça um número primeiro!');
            const original = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
            lucide.createIcons();
            try {
                const fd = new FormData(); fd.append('acao', 'receber_codigo_sms'); fd.append('id_pedido', pid);
                const res = await fetch(window.location.href, { method: 'POST', body: fd });
                const data = await res.json();
                if(data.sucesso) {
                    document.getElementById('sms_codigo_' + contaId).value = data.codigo;
                    mostrarToast('Código SMS recebido!');
                } else mostrarToast(data.erro === 'STATUS_WAIT_CODE' ? 'Aguardando SMS...' : data.erro);
            } catch (e) { mostrarToast('Erro ao ler SMS'); }
            btn.innerHTML = original; lucide.createIcons();
        }

        // Email API
        async function apiLerEmailRow(contaId, btn) {
            const original = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
            lucide.createIcons();
            try {
                const fd = new FormData(); fd.append('acao', 'ler_email_codigo'); fd.append('conta_id', contaId);
                const res = await fetch('processa.php', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.sucesso) {
                    document.getElementById('email_codigo_' + contaId).value = data.codigo;
                    mostrarToast('Código E-mail encontrado!');
                } else mostrarToast(data.erro);
            } catch (e) { mostrarToast('Erro ao ler Hostinger'); }
            btn.innerHTML = original; lucide.createIcons();
        }

        async function colar2FADireto(id) {
            let text = '';
            try {
                text = await navigator.clipboard.readText();
            } catch (err) {
                console.log('Erro ao ler clipboard diretamente:', err);
            }
            
            if (!text || !text.trim()) {
                const tr = document.querySelector(`tr[data-id="${id}"]`);
                let existing2FA = '';
                if (tr && tr.dataset.json) {
                    try {
                        const data = JSON.parse(tr.dataset.json);
                        if (data.codigo_2fa) {
                            existing2FA = data.codigo_2fa;
                        }
                    } catch (e) {}
                }
                text = prompt("Cole o código 2FA abaixo e clique em OK:", existing2FA);
            }
            
            if (text && text.trim()) {
                const text2fa = text.trim();
                const fd = new FormData();
                fd.append('acao', 'salvar_2fa');
                fd.append('conta_id', id);
                fd.append('codigo_2fa', text2fa);
                
                try {
                    const res = await fetch('processa.php', { method: 'POST', body: fd });
                    if (res.ok) {
                        mostrarToast('2FA salvo com sucesso!');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        mostrarToast('Erro ao salvar 2FA.');
                    }
                } catch (e) {
                    mostrarToast('Erro de rede ao salvar 2FA.');
                }
            }
        }

        async function colarCookiesDireto(id) {
            let text = '';
            try {
                text = await navigator.clipboard.readText();
            } catch (err) {
                console.log('Erro ao ler clipboard diretamente:', err);
            }
            
            if (!text || !text.trim()) {
                const tr = document.querySelector(`tr[data-id="${id}"]`);
                let existingCookies = '';
                if (tr && tr.dataset.json) {
                    try {
                        const data = JSON.parse(tr.dataset.json);
                        if (data.cookies) {
                            existingCookies = data.cookies;
                        }
                    } catch (e) {}
                }
                text = prompt("Cole os cookies abaixo (JSON ou Texto) e clique em OK:", existingCookies);
            }
            
            if (text && text.trim()) {
                const cookiesText = text.trim();
                const fd = new FormData();
                fd.append('acao', 'salvar_cookies');
                fd.append('conta_id', id);
                fd.append('cookies', cookiesText);
                
                try {
                    const res = await fetch('processa.php', { method: 'POST', body: fd });
                    if (res.ok) {
                        mostrarToast('Cookies salvos com sucesso!');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        mostrarToast('Erro ao salvar cookies.');
                    }
                } catch (e) {
                    mostrarToast('Erro de rede ao salvar cookies.');
                }
            }
        }

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

        // ── Nota / Comentário por Conta (popover global) ─────────────
        let notaAberta = null;
        let notaBtnAberto = null;

        function abrirNotaConta(id) {
            const pop = document.getElementById('nota-popover-global');

            // Toggle: fechar se já está aberto para o mesmo id
            if (notaAberta === id && !pop.classList.contains('hidden')) {
                fecharNotaConta();
                return;
            }

            // Buscar nota atual no data-json da linha
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            let notaValor = '';
            if (tr && tr.dataset.json) {
                try { notaValor = JSON.parse(tr.dataset.json).nota_conta || ''; } catch(e) {}
            }

            // Preencher o popover global
            document.getElementById('nota-global-conta-id').value = id;
            document.getElementById('nota-global-textarea').value = notaValor;
            notaAberta = id;

            // Encontrar o botão clicado
            const btn = document.querySelector(`[onclick*="abrirNotaConta(${id})"]`);
            notaBtnAberto = btn;

            // Posicionar corretamente
            pop.style.visibility = 'hidden';
            pop.classList.remove('hidden');
            const popH = pop.offsetHeight;
            const popW = pop.offsetWidth;

            if (btn) {
                const rect = btn.getBoundingClientRect();
                const footerH = 90; // barra fixa do rodapé
                const usableBottom = window.innerHeight - footerH;
                const spaceBelow = usableBottom - rect.bottom;

                let top = (spaceBelow < popH + 10)
                    ? Math.max(8, rect.top - popH - 8)  // abre para cima
                    : rect.bottom + 8;                   // abre para baixo

                let left = rect.right - popW;
                left = Math.min(left, window.innerWidth - popW - 8);
                left = Math.max(left, 8);

                pop.style.top  = top  + 'px';
                pop.style.left = left + 'px';
            }

            pop.style.visibility = '';
            setTimeout(() => document.getElementById('nota-global-textarea').focus(), 50);
        }

        function fecharNotaConta() {
            const pop = document.getElementById('nota-popover-global');
            pop.classList.add('hidden');
            notaAberta = null;
            notaBtnAberto = null;
        }

        // Fechar ao clicar fora do popover e fora do botão
        document.addEventListener('click', function(e) {
            if (!notaAberta) return;
            const pop = document.getElementById('nota-popover-global');
            if (pop.contains(e.target)) return;         // clique dentro do popover
            if (notaBtnAberto && notaBtnAberto.contains(e.target)) return; // clique no botão
            fecharNotaConta();
        }, true);

        function copiarContaUnica(btn) {
            const row = btn.closest('tr');
            const data = JSON.parse(row.dataset.json);
            const txt = `nome: ${data.nome} ${data.sobrenome}\nuser: ${data.username}\nacesso: ${data.email}\nsenha: ${data.senha}\ncódigo 2fa: ${data.codigo_2fa || 'N/A'}\n\ncookies: ${data.cookies || 'N/A'}`;
            copiar(txt, 'Dados da conta copiados!');
        }

        // Timer
        setInterval(() => {
            document.querySelectorAll('.countdown-timer').forEach(t => {
                let r = parseInt(t.dataset.restante);
                if(r <= 0) {
                    t.innerHTML = "PRONTO PARA E-MAIL";
                    t.classList.remove('text-amber-600');
                    t.classList.add('text-emerald-600');
                } else {
                    const m = Math.floor(r/60); const s = r%60;
                    t.innerHTML = `Libera em ${m}m ${s < 10 ? '0'+s : s}s`;
                    t.dataset.restante = r - 1;
                }
            });
        }, 1000);

        // selectAll works for both desktop table and mobile cards
        const selectAllEl = document.getElementById('selectAll');
        if (selectAllEl) {
            selectAllEl.addEventListener('change', (e) => {
                document.querySelectorAll('.check-conta').forEach(c => c.checked = e.target.checked);
                atualizarBarraLote();
            });
        }
        // Mobile card checkboxes also trigger batch bar
        document.querySelectorAll('.mobile-cards-wrap .check-conta').forEach(cb => {
            cb.addEventListener('change', atualizarBarraLote);
        });
    </script>
</body>
</html>