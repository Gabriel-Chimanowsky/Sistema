<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="glass-nav fixed top-0 w-full z-50 no-print">
    <div class="max-w-[1600px] mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <!-- Logo -->
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white shadow-lg shadow-blue-600/30">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            </div>
            <span class="font-extrabold text-xl tracking-tight">Facebook</span>
        </div>
        
        <!-- Navigation Links -->
        <div class="flex justify-center items-center gap-1 font-semibold text-sm">
            <?php if (!isFinanceiro()): ?>
                <a href="index.php" class="px-4 py-2 rounded-xl <?= $current_page == 'index.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Contas</a>
                <a href="pessoas.php" class="px-4 py-2 rounded-xl <?= $current_page == 'pessoas.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Pessoas</a>
                <a href="config.php" class="px-4 py-2 rounded-xl <?= $current_page == 'config.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Ajustes</a>
            <?php endif; ?>
            <a href="relatorio.php" class="px-4 py-2 rounded-xl <?= $current_page == 'relatorio.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Financeiro</a>
        </div>

        <!-- Right Actions (Batch actions, export, etc) -->
        <div class="flex items-center justify-end gap-4">
            <?php if (!isFinanceiro()): ?>
                <div class="hidden lg:flex items-center gap-2 border-r border-slate-200 dark:border-slate-800 pr-4 mr-2">
                    <select id="status_massa" class="bg-white dark:bg-slate-900 border dark:border-slate-800 text-sm font-bold outline-none cursor-pointer p-1 rounded-lg">
                        <option value="">Ação em Lote</option>
                        <option value="pendente">Status: Pendente</option>
                        <option value="criada">Status: Criada</option>
                        <option value="autenticada">Status: Autenticada</option>
                        <option value="exportado">Status: Exportado</option>
                    </select>
                    <button onclick="mudarStatusMassa()" class="p-1.5 bg-slate-900 text-white dark:bg-white dark:text-slate-900 rounded-lg hover:scale-105 transition active:scale-95 shadow-sm">
                        <i data-lucide="play" class="w-4 h-4 fill-current"></i>
                    </button>
                </div>
                
                <button onclick="copiarSelecionados()" class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-4 py-2.5 rounded-xl font-bold flex items-center gap-2 transition-all active:scale-95 hover:bg-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                    Copiar Selecionados
                </button>
                <button onclick="exportarContas()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-bold flex items-center gap-2 shadow-lg shadow-blue-600/20 transition-all active:scale-95">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    Exportar
                </button>
            <?php endif; ?>
            <a href="logout.php" class="text-slate-400 hover:text-red-500 transition p-2"><i data-lucide="log-out" class="w-5 h-5"></i></a>
        </div>
    </div>
</nav>
