<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

if (isFinanceiro()) {
    header("Location: relatorio.php");
    exit;
}

global $apiHerosms, $urlApi;
$urlApi = 'https://hero-sms.com/stubs/handler_api.php';

// Verificações de banco rápidas (migrações automáticas leves)
try { $pdo->query("SELECT data_autenticacao FROM contas LIMIT 1"); } catch (Exception $e) { $pdo->query("ALTER TABLE contas ADD COLUMN data_autenticacao DATETIME NULL"); }
try { $pdo->query("SELECT cookies FROM contas LIMIT 1"); } catch (Exception $e) { $pdo->query("ALTER TABLE contas ADD COLUMN cookies LONGTEXT NULL"); }

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
        header("Location: index.php"); exit;
    }

    if ($_POST['acao'] === 'editar_2fa_direto') {
        $pdo->prepare("UPDATE contas SET codigo_2fa = ?, chave_2fa = ? WHERE id = ?")->execute([$_POST['codigo_2fa'], $_POST['chave_2fa'], $_POST['conta_id']]);
        header("Location: index.php"); exit;
    }
}

// Stats
$totalContas = $pdo->query("SELECT COUNT(*) FROM contas")->fetchColumn();
$contasCriadasHoje = $pdo->query("SELECT COUNT(*) FROM contas WHERE DATE(data_criacao) = CURDATE()")->fetchColumn();
$contasAutenticadas = $pdo->query("SELECT COUNT(*) FROM contas WHERE status IN ('autenticada', 'exportado')")->fetchColumn();

// Listagem
$sort = $_GET['sort'] ?? 'id';
$dir = strtoupper($_GET['dir'] ?? 'DESC');
$colunasValidas = ['id', 'username', 'nome', 'sobrenome', 'email', 'status'];
if (!in_array($sort, $colunasValidas)) $sort = 'id';
if ($dir !== 'ASC' && $dir !== 'DESC') $dir = 'DESC';

$stmtPessoas = $pdo->query("SELECT * FROM pessoas ORDER BY nome ASC");
$pessoas = $stmtPessoas->fetchAll();

$ordemSQL = "{$sort} {$dir}";
$stmtContas = $pdo->query("SELECT *, UNIX_TIMESTAMP(data_criacao) as criacao_unix, UNIX_TIMESTAMP(data_autenticacao) as auth_unix FROM contas ORDER BY {$ordemSQL}");
$contas = $stmtContas->fetchAll();
$tempoDb = time();

function linkSort($coluna, $nomeExibicao, $sortAtual, $dirAtual) {
    $novoDir = ($sortAtual === $coluna && $dirAtual === 'ASC') ? 'DESC' : 'ASC';
    $icone = ($sortAtual === $coluna) ? ($dirAtual === 'ASC' ? ' 🔼' : ' 🔽') : '';
    return "<a href='?sort={$coluna}&dir={$novoDir}' class='flex items-center gap-1 hover:text-blue-500 transition'>{$nomeExibicao}{$icone}</a>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas - Facebook Account Manager V4.3</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="common.js"></script>
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
    </style>
</head>
<body class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 min-h-screen pb-24">

    <?php include 'navbar.php'; ?>

    <main class="max-w-[1600px] mx-auto px-4 mt-24 space-y-6">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Criadas Hoje</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasCriadasHoje ?></h3>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-2xl flex items-center justify-center text-green-600 dark:text-green-400">
                    <i data-lucide="trending-up" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-semibold uppercase tracking-wider">Autenticadas</p>
                    <h3 class="text-3xl font-black mt-1"><?= $contasAutenticadas ?></h3>
                </div>
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-2xl flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-500 font-bold uppercase text-[11px] tracking-widest">
                            <th class="p-4 w-12 text-center"><input type="checkbox" id="selectAll" class="w-4 h-4 rounded-md border-slate-300 dark:border-slate-600 focus:ring-blue-500"></th>
                            <th class="p-4 w-16 text-center"><?= linkSort('id', 'ID', $sort, $dir) ?></th>
                            <th class="p-4"><?= linkSort('username', 'Usuário', $sort, $dir) ?></th>
                            <th class="p-4"><?= linkSort('nome', 'Nome', $sort, $dir) ?></th>
                            <th class="p-4"><?= linkSort('email', 'E-mail', $sort, $dir) ?></th>
                            <th class="p-4">Credenciais</th>
                            <th class="p-4">2FA & Cookies</th>
                            <th class="p-4 text-center"><?= linkSort('status', 'Status', $sort, $dir) ?></th>
                            <th class="p-4">Dono</th>
                            <th class="p-4 text-right pr-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($contas as $conta): 
                            $restante = 0;
                            if (!empty($conta['criacao_unix'])) $restante = 3600 - ($tempoDb - $conta['criacao_unix']);
                            $dias_vida = 0;
                            if (in_array($conta['status'], ['autenticada', 'exportado']) && !empty($conta['auth_unix'])) $dias_vida = floor(($tempoDb - $conta['auth_unix']) / 86400);
                        ?>
                        <tr class="tr-hover group transition-colors <?= $conta['status'] === 'exportado' ? 'opacity-80 grayscale-[0.2]' : '' ?>" data-id="<?= $conta['id'] ?>" data-json='<?= json_encode($conta) ?>'>
                            <td class="p-4 text-center"><input type="checkbox" class="check-conta w-4 h-4 rounded-md border-slate-300 dark:border-slate-600"></td>
                            <td class="p-4 text-center font-bold text-slate-400">#<?= $conta['id'] ?></td>
                            <td class="p-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($conta['username']) ?></span>
                                    <span class="text-xs text-slate-400">@dollfinn</span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="font-medium"><?= htmlspecialchars($conta['nome'] . ' ' . $conta['sobrenome']) ?></span>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2 group/copy">
                                    <span class="font-mono text-xs text-blue-600 dark:text-blue-400"><?= htmlspecialchars($conta['email']) ?></span>
                                    <button onclick="copiar('<?= $conta['email'] ?>', 'E-mail copiado')" class="opacity-0 group-hover/copy:opacity-100 transition"><i data-lucide="copy" class="w-3 h-3 text-slate-400 hover:text-blue-500"></i></button>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2 text-xs font-mono">
                                        <span class="text-slate-400 w-10">SEN:</span>
                                        <span class="font-bold"><?= htmlspecialchars($conta['senha']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex flex-col gap-2">
                                    <?php if ($conta['codigo_2fa']): ?>
                                        <div class="flex items-center gap-2 text-[10px] font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-lg">
                                            <span class="text-green-600 font-bold uppercase">2FA:</span>
                                            <span><?= $conta['codigo_2fa'] ?></span>
                                            <button onclick="copiar('<?= $conta['codigo_2fa'] ?>', 'Código 2FA copiado')" class="ml-auto"><i data-lucide="copy" class="w-3 h-3"></i></button>
                                        </div>
                                    <?php else: ?>
                                        <button onclick="toggleEdit2FA(<?= $conta['id'] ?>)" class="text-[10px] font-bold text-blue-500 hover:underline">+ Adicionar 2FA</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($conta['cookies']): ?>
                                        <div class="flex items-center gap-2 text-[10px] font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-lg">
                                            <span class="text-indigo-600 font-bold uppercase">CK:</span>
                                            <span class="truncate w-20">Salvo</span>
                                            <button onclick="copiar(this.closest('tr').dataset.json.cookies, 'Cookies copiados')" class="ml-auto"><i data-lucide="copy" class="w-3 h-3"></i></button>
                                        </div>
                                    <?php else: ?>
                                        <button onclick="toggleEditCookies(<?= $conta['id'] ?>)" class="text-[10px] font-bold text-slate-500 hover:underline">+ Colar Cookies</button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Edit Popovers (Hidden by default) -->
                                <div id="edit-2fa-<?= $conta['id'] ?>" class="hidden absolute z-10 bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 mt-2 w-64">
                                    <form method="POST" action="processa.php" class="space-y-3">
                                        <input type="hidden" name="acao" value="salvar_2fa">
                                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                        <input type="text" name="codigo_2fa" placeholder="Código..." class="w-full text-xs p-2 rounded-lg border dark:bg-slate-900 outline-none">
                                        <input type="text" name="chave_2fa" placeholder="Chave..." class="w-full text-xs p-2 rounded-lg border dark:bg-slate-900 outline-none">
                                        <div class="flex gap-2">
                                            <button type="submit" class="flex-1 bg-blue-600 text-white py-1.5 rounded-lg text-xs font-bold">Salvar</button>
                                            <button type="button" onclick="toggleEdit2FA(<?= $conta['id'] ?>)" class="px-3 bg-slate-100 dark:bg-slate-700 rounded-lg text-xs font-bold">X</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <form method="POST" action="processa.php">
                                    <input type="hidden" name="acao" value="mudar_status_direto">
                                    <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                    <select name="novo_status" onchange="this.form.submit()" class="text-[11px] font-black uppercase px-3 py-1.5 rounded-full border-2 cursor-pointer outline-none transition-all dark:bg-slate-900
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
                                    <div class="countdown-timer text-[10px] mt-2 font-black text-amber-600 animate-pulse" data-restante="<?= $restante ?>"></div>
                                <?php elseif (in_array($conta['status'], ['autenticada', 'exportado'])): ?>
                                    <div class="text-[10px] mt-2 font-bold text-emerald-600">Vida: <?= $dias_vida ?> dias</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <form method="POST" action="processa.php">
                                    <input type="hidden" name="acao" value="vincular_pessoa"><input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                    <select name="pessoa_id" onchange="this.form.submit()" class="bg-transparent dark:bg-slate-900 text-xs font-semibold outline-none border-b border-slate-200 dark:border-slate-700 cursor-pointer p-1 rounded">
                                        <option value="">-- Livre --</option>
                                        <?php foreach ($pessoas as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= ($conta['destinada_a'] == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td class="p-4 text-right pr-6">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="copiarContaUnica(this)" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" title="Copiar Perfil"><i data-lucide="clipboard-copy" class="w-4 h-4"></i></button>
                                    <div class="w-px h-4 bg-slate-200 dark:border-slate-700"></div>
                                    <button onclick="abrirModal('Regerar dados desta conta?', this.closest('tr').querySelector('.form-regerar'))" class="p-2 text-purple-500 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg transition"><i data-lucide="refresh-cw" class="w-4 h-4"></i></button>
                                    <button onclick="abrirModal('Excluir conta permanentemente?', this.closest('tr').querySelector('.form-del'))" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                    
                                    <form method="POST" action="processa.php" class="form-regerar hidden"><input type="hidden" name="acao" value="regerar_conta"><input type="hidden" name="conta_id" value="<?= $conta['id'] ?>"></form>
                                    <form method="POST" action="processa.php" class="form-del hidden"><input type="hidden" name="acao" value="del_conta"><input type="hidden" name="conta_id" value="<?= $conta['id'] ?>"></form>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Verification Row (SMS / E-mail) -->
                        <?php if ($conta['status'] !== 'autenticada' && $conta['status'] !== 'exportado'): ?>
                        <tr class="bg-slate-50/50 dark:bg-slate-800/20">
                            <td colspan="10" class="p-3 border-t border-dashed border-slate-200 dark:border-slate-800">
                                <div class="flex flex-wrap items-center gap-6 px-12">
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
        </div>
    </main>

    <!-- Footer Action Bar -->
    <div class="fixed bottom-0 left-0 w-full bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 p-4 shadow-2xl z-40">
        <div class="max-w-[1600px] mx-auto flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 text-slate-400 font-bold text-xs uppercase tracking-widest">
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                Gerar Nova Conta
            </div>

            <form method="POST" action="processa.php" class="flex items-center gap-3">
                <input type="hidden" name="acao" value="gerar_conta">
                
                <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">
                    <select name="genero" id="genSelect" onchange="saveSettings()" class="bg-white dark:bg-slate-900 border-none rounded-lg px-3 py-1.5 text-xs font-bold outline-none cursor-pointer shadow-sm">
                        <option value="homem">👨 Homem</option>
                        <option value="mulher">👩 Mulher</option>
                    </select>
                    <select name="pais" id="paisSelect" onchange="saveSettings()" class="bg-white dark:bg-slate-900 border-none rounded-lg px-3 py-1.5 text-xs font-bold outline-none cursor-pointer shadow-sm">
                        <option value="br">🇧🇷 Brasil</option>
                        <option value="us">🇺🇸 EUA</option>
                    </select>
                </div>

                <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl px-3 py-1.5 gap-2">
                    <span class="text-[10px] font-black text-slate-400 uppercase">Qtd:</span>
                    <input type="number" name="quantidade" value="1" min="1" max="50" class="bg-transparent w-10 text-sm font-bold outline-none text-center">
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-2 rounded-xl font-black text-sm shadow-xl shadow-blue-600/30 transition-all hover:scale-105 active:scale-95">
                    GERAR AGORA
                </button>
            </form>

            <div class="hidden lg:block text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                Facebook Account Manager v4.3
            </div>
        </div>
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

    <script>
        lucide.createIcons();
        if (window.location.search.includes('msg=ok')) {
            setTimeout(() => mostrarToast('Ação realizada com sucesso!'), 300);
        }
        
        // Persistence
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

        function toggleEdit2FA(id) {
            const el = document.getElementById('edit-2fa-' + id);
            el.classList.toggle('hidden');
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

        document.getElementById('selectAll').addEventListener('change', (e) => {
            document.querySelectorAll('.check-conta').forEach(c => c.checked = e.target.checked);
        });
    </script>
</body>
</html>