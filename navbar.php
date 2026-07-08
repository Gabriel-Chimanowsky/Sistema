<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="glass-nav fixed top-0 w-full z-50 no-print" style="padding-top: env(safe-area-inset-top);">
    <div class="max-w-[1600px] mx-auto px-4 h-16 flex items-center justify-between gap-2">
        <!-- Logo -->
        <div class="flex items-center gap-2 flex-shrink-0">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white shadow-lg shadow-blue-600/30">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            </div>
            <span class="font-extrabold text-xl tracking-tight hidden sm:block">Facebook</span>
        </div>
        
        <!-- Navigation Links — desktop -->
        <div class="hidden md:flex justify-center items-center gap-1 font-semibold text-sm flex-1">
            <?php if (!isFinanceiro()): ?>
                <a href="index.php" class="px-4 py-2 rounded-xl <?= $current_page == 'index.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Contas</a>
                <a href="pessoas.php" class="px-4 py-2 rounded-xl <?= $current_page == 'pessoas.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Pessoas</a>
                <a href="apps.php" class="px-4 py-2 rounded-xl <?= $current_page == 'apps.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Apps</a>
                <a href="slack.php" class="px-4 py-2 rounded-xl <?= $current_page == 'slack.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Slack</a>
                <a href="cloudflare.php" class="px-4 py-2 rounded-xl <?= $current_page == 'cloudflare.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Cloudflare</a>
                <a href="config.php" class="px-4 py-2 rounded-xl <?= $current_page == 'config.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Ajustes</a>
            <?php endif; ?>
            <a href="relatorio.php" class="px-4 py-2 rounded-xl <?= $current_page == 'relatorio.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' ?> transition">Financeiro</a>
        </div>

        <!-- Right Actions — desktop -->
        <div class="hidden lg:flex items-center justify-end gap-4 flex-shrink-0">
            <?php if (!isFinanceiro()): ?>
                <div class="flex items-center gap-2 border-r border-slate-200 dark:border-slate-800 pr-4 mr-2">
                    <select id="status_massa" onchange="toggleSelectPessoa(this.value)" class="bg-white dark:bg-slate-900 border dark:border-slate-800 text-sm font-bold outline-none cursor-pointer p-1 rounded-lg">
                        <option value="">Ação em Lote</option>
                        <optgroup label="── Status ──">
                            <option value="status:pendente">Status: Pendente</option>
                            <option value="status:criada">Status: Criada</option>
                            <option value="status:autenticada">Status: Autenticada</option>
                            <option value="status:exportado">Status: Exportado</option>
                        </optgroup>
                        <optgroup label="── Outras ──">
                            <option value="dono">Mudar Dono</option>
                            <option value="regerar">Regerar Dados</option>
                            <option value="deletar">Deletar</option>
                        </optgroup>
                    </select>
                    <!-- Select de pessoas (só aparece ao escolher "Mudar Dono") -->
                    <select id="select_pessoa_massa" class="hidden bg-white dark:bg-slate-900 border dark:border-slate-800 text-sm font-bold outline-none cursor-pointer p-1 rounded-lg">
                        <option value="">-- Livre --</option>
                        <?php
                        if (!isFinanceiro()) {
                            $stmtP = $pdo->query("SELECT * FROM pessoas ORDER BY nome ASC");
                            foreach ($stmtP->fetchAll() as $p) {
                                echo "<option value=\"{$p['id']}\">" . htmlspecialchars($p['nome']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <button onclick="executarAcaoLoteNavbar()" class="p-1.5 bg-slate-900 text-white dark:bg-white dark:text-slate-900 rounded-lg hover:scale-105 transition active:scale-95 shadow-sm">
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

        <!-- Mobile right: logout + hamburger -->
        <div class="flex items-center gap-2 md:hidden">
            <a href="logout.php" class="text-slate-400 hover:text-red-500 transition p-2"><i data-lucide="log-out" class="w-5 h-5"></i></a>
            <button id="hamburger-btn" onclick="toggleMobileMenu()" class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition text-slate-600 dark:text-slate-300" aria-label="Menu">
                <i data-lucide="menu" class="w-6 h-6" id="hamburger-icon"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Drawer Overlay -->
<div id="mobile-overlay" onclick="closeMobileMenu()" class="fixed inset-0 bg-slate-950/50 backdrop-blur-sm z-[60] hidden opacity-0 transition-opacity duration-300"></div>

<!-- Mobile Drawer -->
<div id="mobile-drawer" class="fixed top-0 right-0 h-full w-72 max-w-[85vw] bg-white dark:bg-slate-900 z-[70] shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
     style="padding-top: calc(env(safe-area-inset-top) + 0px); padding-bottom: env(safe-area-inset-bottom);">
    
    <!-- Drawer Header -->
    <div class="flex items-center justify-between px-5 pt-5 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 bg-blue-600 rounded-lg flex items-center justify-center text-white shadow shadow-blue-600/30">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
            </div>
            <span class="font-extrabold text-lg tracking-tight">Facebook</span>
        </div>
        <button onclick="closeMobileMenu()" class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition text-slate-500">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Drawer Nav Links -->
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
        <?php if (!isFinanceiro()): ?>
            <a href="index.php" onclick="closeMobileMenu()" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold text-sm transition <?= $current_page == 'index.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                <i data-lucide="users" class="w-5 h-5 flex-shrink-0"></i> Contas
            </a>
            <a href="pessoas.php" onclick="closeMobileMenu()" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold text-sm transition <?= $current_page == 'pessoas.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                <i data-lucide="user" class="w-5 h-5 flex-shrink-0"></i> Pessoas
            </a>
            <a href="apps.php" onclick="closeMobileMenu()" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold text-sm transition <?= $current_page == 'apps.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                <i data-lucide="grid" class="w-5 h-5 flex-shrink-0"></i> Apps
            </a>
            <a href="slack.php" onclick="closeMobileMenu()" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold text-sm transition <?= $current_page == 'slack.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                <i data-lucide="hash" class="w-5 h-5 flex-shrink-0"></i> Slack
            </a>
            <a href="cloudflare.php" onclick="closeMobileMenu()" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold text-sm transition <?= $current_page == 'cloudflare.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                <i data-lucide="cloud" class="w-5 h-5 flex-shrink-0"></i> Cloudflare
            </a>
            <a href="config.php" onclick="closeMobileMenu()" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold text-sm transition <?= $current_page == 'config.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
                <i data-lucide="settings" class="w-5 h-5 flex-shrink-0"></i> Ajustes
            </a>
        <?php endif; ?>
        <a href="relatorio.php" onclick="closeMobileMenu()" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold text-sm transition <?= $current_page == 'relatorio.php' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">
            <i data-lucide="bar-chart-2" class="w-5 h-5 flex-shrink-0"></i> Financeiro
        </a>
    </div>

    <!-- Drawer Footer Actions -->
    <?php if (!isFinanceiro()): ?>
    <div class="px-4 pb-4 pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2">
        <button onclick="copiarSelecionados(); closeMobileMenu();" class="w-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 py-3 rounded-2xl font-bold flex items-center justify-center gap-2 transition active:scale-95 text-sm">
            <i data-lucide="copy" class="w-4 h-4"></i> Copiar Selecionados
        </button>
        <button onclick="exportarContas(); closeMobileMenu();" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-2xl font-bold flex items-center justify-center gap-2 shadow-lg shadow-blue-600/20 transition active:scale-95 text-sm">
            <i data-lucide="download" class="w-4 h-4"></i> Exportar
        </button>
        <a href="logout.php" class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl text-sm font-bold text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
            <i data-lucide="log-out" class="w-4 h-4"></i> Sair
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleMobileMenu() {
    const drawer = document.getElementById('mobile-drawer');
    const overlay = document.getElementById('mobile-overlay');
    const isOpen = !drawer.classList.contains('translate-x-full');
    if (isOpen) { closeMobileMenu(); } else { openMobileMenu(); }
}
function openMobileMenu() {
    const drawer = document.getElementById('mobile-drawer');
    const overlay = document.getElementById('mobile-overlay');
    overlay.classList.remove('hidden');
    requestAnimationFrame(() => {
        overlay.classList.add('opacity-100');
        overlay.classList.remove('opacity-0');
        drawer.classList.remove('translate-x-full');
    });
    document.body.style.overflow = 'hidden';
}
function closeMobileMenu() {
    const drawer = document.getElementById('mobile-drawer');
    const overlay = document.getElementById('mobile-overlay');
    drawer.classList.add('translate-x-full');
    overlay.classList.remove('opacity-100');
    overlay.classList.add('opacity-0');
    setTimeout(() => { overlay.classList.add('hidden'); }, 300);
    document.body.style.overflow = '';
}
// Close on ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMobileMenu(); });
</script>
