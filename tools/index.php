<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../auth.php';
checkAuth();

if (isFinanceiro()) {
    header("Location: ../relatorio.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Ferramentas — Sistema</title>
    <script src="../tailwind.js?v=1"></script>
    <script>
        tailwind.config = { darkMode: 'media' }
    </script>
    <script src="../lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-nav { background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        @media (prefers-color-scheme: dark) {
            .glass-nav { background: rgba(15,23,42,0.95) !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen pb-20">

    <?php include __DIR__ . '/../navbar.php'; ?>

    <main class="max-w-4xl mx-auto px-4 mt-28 pb-16 space-y-8">

        <!-- Header -->
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-2xl flex items-center justify-center">
                <i data-lucide="wrench" class="w-6 h-6 text-amber-600 dark:text-amber-400"></i>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">Central de Ferramentas</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Diagnósticos, manutenção e operações avançadas do sistema.</p>
            </div>
        </div>

        <!-- Aviso de Segurança -->
        <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-700/40 rounded-2xl p-4 flex items-start gap-3">
            <i data-lucide="shield-alert" class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5"></i>
            <p class="text-sm text-amber-800 dark:text-amber-300 font-medium">
                Área restrita a administradores. Algumas operações aqui são <strong>irreversíveis</strong>. Leia as descrições antes de executar qualquer ferramenta.
            </p>
        </div>

        <!-- Grupo: Diagnóstico Slack -->
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 px-1">🔵 Diagnóstico — Slack</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <a href="debug_slack.php" class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:border-blue-500 dark:hover:border-blue-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <i data-lucide="activity" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <span class="font-bold text-sm">Diagnóstico Slack</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Testa a conexão com o token do Slack e envia uma mensagem de teste para o canal configurado.</p>
                </a>

                <a href="debug_badge_slack.php" class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:border-blue-500 dark:hover:border-blue-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <i data-lucide="bar-chart-2" class="w-4 h-4 text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <span class="font-bold text-sm">Diagnóstico de Badge</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Mostra a contagem exata de contas não sincronizadas, agrupadas por status e domínio, como o auto-sync vê.</p>
                </a>

                <a href="verificar_config.php" class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:border-blue-500 dark:hover:border-blue-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                            <i data-lucide="settings-2" class="w-4 h-4 text-slate-600 dark:text-slate-400"></i>
                        </div>
                        <span class="font-bold text-sm">Verificar Configurações Slack</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Exibe as credenciais ativas, pendências de sincronização e histórico de listas criadas no banco.</p>
                </a>

                <a href="verificar_log.php" class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:border-blue-500 dark:hover:border-blue-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                            <i data-lucide="scroll-text" class="w-4 h-4 text-slate-600 dark:text-slate-400"></i>
                        </div>
                        <span class="font-bold text-sm">Log de Diagnóstico</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Painel de diagnóstico completo de Cloudflare e Slack em tempo real. Permite forçar sincronizações e limpar logs.</p>
                </a>

            </div>
        </section>

        <!-- Grupo: Manutenção Slack -->
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 px-1">🛠️ Manutenção — Slack</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <a href="reconstruir_slack.php" class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:border-orange-500 dark:hover:border-orange-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <i data-lucide="refresh-ccw" class="w-4 h-4 text-orange-600 dark:text-orange-400"></i>
                        </div>
                        <span class="font-bold text-sm">Reconstruir Listas Slack</span>
                        <span class="ml-auto text-[10px] font-bold text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-950/30 px-2 py-0.5 rounded-full border border-orange-200 dark:border-orange-800">Destrutivo</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Apaga todas as listas antigas no Slack, reseta o sincronismo e recria tudo do zero com todos os lotes em ordem.</p>
                </a>

                <a href="limpar_bug_slack.php" class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:border-orange-500 dark:hover:border-orange-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <i data-lucide="bug-off" class="w-4 h-4 text-orange-600 dark:text-orange-400"></i>
                        </div>
                        <span class="font-bold text-sm">Limpar Bug de Semana</span>
                        <span class="ml-auto text-[10px] font-bold text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-950/30 px-2 py-0.5 rounded-full border border-orange-200 dark:border-orange-800">Destrutivo</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Remove entradas duplicadas de uma semana específica no Slack List e reseta o sincronismo para reconstruí-la corretamente.</p>
                </a>

            </div>
        </section>

        <!-- Grupo: Diagnóstico Cloudflare -->
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 px-1">🟠 Diagnóstico — Cloudflare</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <a href="debug_cf.php" class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:border-orange-500 dark:hover:border-orange-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <i data-lucide="cloud" class="w-4 h-4 text-orange-600 dark:text-orange-400"></i>
                        </div>
                        <span class="font-bold text-sm">Diagnóstico Cloudflare API</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Verifica a validade do token, acesso à zone e status das regras de roteamento de e-mail.</p>
                </a>

            </div>
        </section>

        <!-- Grupo: Dados e Banco -->
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 px-1">🔴 Operações de Banco de Dados</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <a href="remover_teste.php" class="group bg-white dark:bg-slate-900 border border-red-200 dark:border-red-900/40 rounded-2xl p-5 hover:border-red-500 dark:hover:border-red-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                            <i data-lucide="trash-2" class="w-4 h-4 text-red-600 dark:text-red-400"></i>
                        </div>
                        <span class="font-bold text-sm">Remover Contas de Teste</span>
                        <span class="ml-auto text-[10px] font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 px-2 py-0.5 rounded-full border border-red-200 dark:border-red-800">Irreversível</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Remove as N contas mais recentes e seus registros de log. Útil para limpar dados de teste criados durante desenvolvimento.</p>
                </a>

                <a href="migrar_ids.php" class="group bg-white dark:bg-slate-900 border border-red-200 dark:border-red-900/40 rounded-2xl p-5 hover:border-red-500 dark:hover:border-red-600 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-9 h-9 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                            <i data-lucide="arrow-right-left" class="w-4 h-4 text-red-600 dark:text-red-400"></i>
                        </div>
                        <span class="font-bold text-sm">Migrar IDs de Contas</span>
                        <span class="ml-auto text-[10px] font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 px-2 py-0.5 rounded-full border border-red-200 dark:border-red-800">Avançado</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Reorganiza os IDs das contas por domínio de e-mail. Operação avançada que requer confirmação explícita.</p>
                </a>

            </div>
        </section>

        <a href="../config.php" class="flex items-center gap-2 text-sm text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition font-semibold">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Voltar para Configurações
        </a>

    </main>

    <script>lucide.createIcons();</script>
</body>
</html>
