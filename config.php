<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

if (isFinanceiro()) {
    header("Location: relatorio.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM configuracoes LIMIT 1");
$config = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Facebook Account Manager V4.3</title>
    <script src="tailwind.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'media'
        }
    </script>
    <script src="lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="common.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        @media (prefers-color-scheme: dark) {
            .glass-nav { background: rgba(15, 23, 42, 0.95) !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen pb-20">
    
    <?php include 'navbar.php'; ?>

    <div class="max-w-[1000px] mx-auto px-4 mt-24">
        <form method="POST" action="processa.php" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-10 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-10">
            <input type="hidden" name="acao" value="atualizar_config">
            
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-black flex items-center gap-3">
                    <i data-lucide="sliders" class="w-8 h-8 text-blue-600"></i>
                    Configurações do Sistema
                </h1>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-4 rounded-2xl font-black shadow-lg shadow-blue-600/30 transition-all hover:scale-105 active:scale-95">
                    Salvar Alterações
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div class="space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400 border-b pb-2 dark:border-slate-800">Automação de Contas</h3>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Senha Padrão das Contas</label>
                        <input type="text" name="senha_padrao" value="<?= htmlspecialchars($config['senha_padrao']) ?>" 
                            class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Gênero Padrão</label>
                            <select name="genero_padrao" class="w-full bg-slate-50 dark:bg-slate-900 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold">
                                <option value="homem" <?= ($config['genero_padrao'] == 'homem') ? 'selected' : '' ?>>👨 Homem</option>
                                <option value="mulher" <?= ($config['genero_padrao'] == 'mulher') ? 'selected' : '' ?>>👩 Mulher</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">País Padrão</label>
                            <select name="pais_padrao" class="w-full bg-slate-50 dark:bg-slate-900 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold">
                                <option value="br" <?= ($config['pais_padrao'] == 'br') ? 'selected' : '' ?>>🇧🇷 Brasil</option>
                                <option value="us" <?= ($config['pais_padrao'] == 'us') ? 'selected' : '' ?>>🇺🇸 EUA</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400 border-b pb-2 dark:border-slate-800">Estrutura de E-mail (Hostinger)</h3>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Prefixo do E-mail</label>
                        <input type="text" name="email_prefixo" value="<?= htmlspecialchars($config['email_prefixo']) ?>" 
                            class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold" placeholder="ex: conta">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Contador Atual</label>
                            <input type="number" name="email_contador" value="<?= $config['email_contador'] ?>" 
                                class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Domínio (@...)</label>
                            <input type="text" name="email_dominio" value="<?= htmlspecialchars($config['email_dominio']) ?>" 
                                class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold" placeholder="@meudominio.com">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-24 right-8 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl shadow-2xl font-bold flex items-center gap-3 transform translate-y-32 opacity-0 transition-all z-50">
        <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center text-white"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg"></span>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>