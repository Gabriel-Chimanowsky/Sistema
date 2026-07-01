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
    <title>Cloudflare - Facebook Account Manager V4.3</title>
    <script src="tailwind.js?v=1"></script>
    <script>
        tailwind.config = {
            darkMode: 'media'
        }
    </script>
    <script src="lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
    <script src="common.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        @media (prefers-color-scheme: dark) {
            .glass-nav { background: rgba(15, 23, 42, 0.95) !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
        }
        .dragover { border-color: #3b82f6 !important; background-color: rgba(59, 130, 246, 0.05) !important; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen pb-20">
    
    <?php include 'navbar.php'; ?>

    <div class="max-w-[1200px] mx-auto px-4 mt-24">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black flex items-center gap-3">
                <div class="w-10 h-10 bg-orange-500 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-orange-500/30">
                    <i data-lucide="mail-check" class="w-6 h-6"></i>
                </div>
                Cloudflare Email Routing Bot
            </h1>
            <div class="bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-ping"></span>
                API Direct v2.0
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Coluna da Esquerda: Configurações & Credenciais -->
            <div class="lg:col-span-1 space-y-8">
                
                <!-- Card 1: Credenciais -->
                <div class="bg-white dark:bg-slate-900 rounded-[2rem] p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-6">
                    <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800/80 pb-3">
                        <span class="w-7 h-7 bg-orange-100 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 rounded-lg flex items-center justify-center text-xs font-black">01</span>
                        <h2 class="font-extrabold text-base">Credenciais</h2>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">API Token</label>
                            <div class="relative">
                                <input type="password" id="api-token" value="<?= htmlspecialchars($config['cloudflare_token'] ?? '') ?>" 
                                    class="w-full bg-slate-50 dark:bg-slate-800/50 border-2 border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3.5 pr-12 rounded-xl outline-none transition-all font-mono text-sm" placeholder="Seu Cloudflare API Token..." autocomplete="off">
                                <button onclick="toggleVis('api-token')" class="absolute right-3 top-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Zone ID</label>
                            <input type="text" id="zone-id" value="<?= htmlspecialchars($config['cloudflare_zone_id'] ?? '') ?>" 
                                class="w-full bg-slate-50 dark:bg-slate-800/50 border-2 border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3.5 rounded-xl outline-none transition-all font-mono text-sm" placeholder="Ex: a1b2c3d4e5f6..." autocomplete="off">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Email de Destino</label>
                            <input type="email" id="destination-email" value="<?= htmlspecialchars($config['cloudflare_dest_email'] ?? '') ?>" 
                                class="w-full bg-slate-50 dark:bg-slate-800/50 border-2 border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3.5 rounded-xl outline-none transition-all font-bold text-sm" placeholder="seuemail@gmail.com" autocomplete="off">
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button onclick="testCredentials()" id="btn-test" class="flex-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 py-3 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95">
                            <i data-lucide="activity" class="w-4 h-4"></i> Testar Conexão
                        </button>
                        <button onclick="saveCredentials()" id="btn-save-creds" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 shadow-md shadow-blue-600/10">
                            <i data-lucide="save" class="w-4 h-4"></i> Salvar Chaves
                        </button>
                    </div>

                    <div id="test-result" class="hidden text-xs font-semibold p-3.5 rounded-xl text-center"></div>
                </div>

                <!-- Card 2: Opções de Execução -->
                <div class="bg-white dark:bg-slate-900 rounded-[2rem] p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-6">
                    <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800/80 pb-3">
                        <span class="w-7 h-7 bg-orange-100 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 rounded-lg flex items-center justify-center text-xs font-black">03</span>
                        <h2 class="font-extrabold text-base">Opções de Criação</h2>
                    </div>

                    <div class="space-y-3">
                        <label class="flex items-center gap-3 bg-slate-50 dark:bg-slate-800/30 p-3 rounded-xl border border-transparent hover:border-slate-200 dark:hover:border-slate-800 cursor-pointer transition">
                            <input type="checkbox" id="opt-skip-existing" checked class="w-4 h-4 text-blue-600 border-slate-300 dark:border-slate-700 rounded focus:ring-blue-500">
                            <div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Pular regras existentes</div>
                                <div class="text-[10px] text-slate-400">Evita erro se o alias já estiver cadastrado</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 bg-slate-50 dark:bg-slate-800/30 p-3 rounded-xl border border-transparent hover:border-slate-200 dark:hover:border-slate-800 cursor-pointer transition">
                            <input type="checkbox" id="opt-enabled" checked class="w-4 h-4 text-blue-600 border-slate-300 dark:border-slate-700 rounded focus:ring-blue-500">
                            <div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Criar como ativo</div>
                                <div class="text-[10px] text-slate-400">As regras criadas já começam funcionando</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 bg-slate-50 dark:bg-slate-800/30 p-3 rounded-xl border border-transparent hover:border-slate-200 dark:hover:border-slate-800 cursor-pointer transition">
                            <input type="checkbox" id="opt-delay" class="w-4 h-4 text-blue-600 border-slate-300 dark:border-slate-700 rounded focus:ring-blue-500">
                            <div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Delay entre requisições</div>
                                <div class="text-[10px] text-slate-400">Adiciona pausa para evitar limite da API</div>
                            </div>
                        </label>
                    </div>

                    <div id="delay-config" class="hidden space-y-1">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Delay (em milissegundos)</label>
                        <input type="number" id="delay-ms" value="300" min="0" max="5000" step="50" 
                            class="w-full bg-slate-50 dark:bg-slate-800/50 border-2 border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3 rounded-xl outline-none transition-all font-bold text-sm">
                    </div>
                </div>

            </div>

            <!-- Coluna da Direita: Lista de Emails & Progresso -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Card 3: Lista de Emails -->
                <div class="bg-white dark:bg-slate-900 rounded-[2rem] p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-6">
                    <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800/80 pb-3">
                        <span class="w-7 h-7 bg-orange-100 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 rounded-lg flex items-center justify-center text-xs font-black">02</span>
                        <h2 class="font-extrabold text-base">Lista de E-mails</h2>
                    </div>

                    <!-- Tabs de modo -->
                    <div class="flex bg-slate-100 dark:bg-slate-800/50 p-1.5 rounded-2xl gap-2 font-bold text-xs">
                        <button onclick="switchTab('manual')" id="tab-manual" class="flex-1 py-3 text-center rounded-xl bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm border border-slate-100 dark:border-slate-800 transition duration-150">
                            ✏️ Manual / Colar
                        </button>
                        <button onclick="switchTab('generate')" id="tab-generate" class="flex-1 py-3 text-center rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition duration-150">
                            ⚡ Gerar Auto
                        </button>
                        <button onclick="switchTab('csv')" id="tab-csv" class="flex-1 py-3 text-center rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition duration-150">
                            📄 Importar CSV
                        </button>
                    </div>

                    <!-- Panel: Manual -->
                    <div id="panel-manual" class="tab-panel space-y-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Aliases (Um por linha, apenas antes do @)</label>
                        <textarea id="email-list" rows="6" placeholder="suporte&#10;vendas&#10;contato" 
                            class="w-full bg-slate-50 dark:bg-slate-800/50 border-2 border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-4 rounded-2xl outline-none transition-all font-mono text-sm leading-relaxed"></textarea>
                    </div>

                    <!-- Panel: Generate -->
                    <div id="panel-generate" class="tab-panel hidden space-y-4">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div class="space-y-1">
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Prefixo</label>
                                <input type="text" id="gen-prefix" value="user" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3 rounded-xl outline-none font-bold text-sm">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Sufixo (Opcional)</label>
                                <input type="text" id="gen-suffix" placeholder="ex: _2026" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3 rounded-xl outline-none font-bold text-sm">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Início</label>
                                <input type="number" id="gen-start" value="1" min="0" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3 rounded-xl outline-none font-bold text-sm">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Fim</label>
                                <input type="number" id="gen-end" value="100" min="1" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3 rounded-xl outline-none font-bold text-sm">
                            </div>
                            <div class="space-y-1 col-span-2 sm:col-span-1">
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1">Zeros à Esquerda</label>
                                <input type="number" id="gen-padding" value="0" min="0" max="6" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800/80 p-3 rounded-xl outline-none font-bold text-sm">
                            </div>
                        </div>
                        <button onclick="generateEmails()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 shadow-md shadow-blue-600/10 transition active:scale-95">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i> Gerar e Adicionar
                        </button>
                        <div id="gen-preview" class="hidden p-3 bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 rounded-xl font-mono text-[11px] text-slate-500 whitespace-pre-line"></div>
                    </div>

                    <!-- Panel: CSV -->
                    <div id="panel-csv" class="tab-panel hidden">
                        <div class="border-2 border-dashed border-slate-200 dark:border-slate-800 hover:border-blue-500 rounded-2xl p-8 text-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-850/20 transition relative"
                             id="upload-area" onclick="document.getElementById('csv-file').click()" ondragover="handleDragOver(event)" ondrop="handleDrop(event)">
                            <input type="file" id="csv-file" accept=".csv,.txt" style="display:none" onchange="handleFileUpload(event)">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <div class="w-12 h-12 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="upload-cloud" class="w-6 h-6"></i>
                                </div>
                                <span class="font-extrabold text-sm text-slate-700 dark:text-slate-200">Arraste ou clique para importar CSV</span>
                                <span class="text-[10px] text-slate-400">Uma coluna com aliases, aceita arquivos TXT e CSV</span>
                            </div>
                        </div>
                    </div>

                    <!-- Preview area -->
                    <div id="email-preview-wrap" class="hidden space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800/80">
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span id="email-count-label" class="text-slate-500">0 aliases carregados</span>
                            <button onclick="clearEmails()" class="text-rose-500 hover:text-rose-600 flex items-center gap-1 transition">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Limpar Lista
                            </button>
                        </div>
                        <div id="email-preview-chips" class="flex flex-wrap gap-2 max-h-[160px] overflow-y-auto p-4 bg-slate-50 dark:bg-slate-800/30 rounded-2xl border border-slate-200 dark:border-slate-800/50"></div>
                    </div>
                </div>

                <!-- Card 4: Run & Progress -->
                <div class="bg-white dark:bg-slate-900 rounded-[2rem] p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-6">
                    <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800/80 pb-3">
                        <span class="w-7 h-7 bg-orange-100 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 rounded-lg flex items-center justify-center text-xs font-black">04</span>
                        <h2 class="font-extrabold text-base">Executar Criação</h2>
                    </div>

                    <!-- Summary -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-slate-50 dark:bg-slate-800/20 p-4 rounded-2xl border border-slate-100 dark:border-slate-800/50">
                        <div class="space-y-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Domínio</span>
                            <span id="sum-domain" class="text-xs font-extrabold text-slate-700 dark:text-slate-300 overflow-hidden text-ellipsis whitespace-nowrap block">—</span>
                        </div>
                        <div class="space-y-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Destino</span>
                            <span id="sum-dest" class="text-xs font-extrabold text-slate-700 dark:text-slate-300 overflow-hidden text-ellipsis whitespace-nowrap block">—</span>
                        </div>
                        <div class="space-y-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">E-mails</span>
                            <span id="sum-count" class="text-xs font-black text-slate-700 dark:text-slate-300 block">0</span>
                        </div>
                        <div class="space-y-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">API Cloudflare</span>
                            <span id="sum-api" class="text-xs font-black text-rose-500 block">Não Testado</span>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button onclick="runBot()" id="btn-run" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-2xl font-black shadow-lg shadow-blue-600/20 transition hover:scale-105 active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i data-lucide="play" class="w-5 h-5"></i> Iniciar Criação em Massa
                        </button>
                        <button onclick="stopBot()" id="btn-stop" class="hidden flex-1 bg-red-600 hover:bg-red-700 text-white py-4 rounded-2xl font-black shadow-lg shadow-red-600/20 transition hover:scale-105 active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i data-lucide="square" class="w-5 h-5"></i> Parar Processo
                        </button>
                    </div>

                    <!-- Progress Section -->
                    <div id="progress-section" class="hidden space-y-4 border-t border-slate-100 dark:border-slate-800/80 pt-6">
                        
                        <!-- Mini counters -->
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div class="bg-slate-50 dark:bg-slate-800/30 p-2.5 rounded-xl border border-slate-200/50 dark:border-slate-800/50">
                                <span id="stat-total" class="block font-black text-slate-600 dark:text-slate-300 text-sm">0</span>
                                <span class="text-[9px] font-bold text-slate-400 uppercase block">Total</span>
                            </div>
                            <div class="bg-emerald-50 dark:bg-emerald-950/20 p-2.5 rounded-xl border border-emerald-100 dark:border-emerald-900/20">
                                <span id="stat-ok" class="block font-black text-emerald-600 dark:text-emerald-400 text-sm">0</span>
                                <span class="text-[9px] font-bold text-emerald-400 uppercase block">Criados</span>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-950/20 p-2.5 rounded-xl border border-amber-100 dark:border-amber-900/20">
                                <span id="stat-skip" class="block font-black text-amber-600 dark:text-amber-400 text-sm">0</span>
                                <span class="text-[9px] font-bold text-amber-400 uppercase block">Pulados</span>
                            </div>
                            <div class="bg-rose-50 dark:bg-rose-950/20 p-2.5 rounded-xl border border-rose-100 dark:border-rose-900/20">
                                <span id="stat-err" class="block font-black text-rose-600 dark:text-rose-400 text-sm">0</span>
                                <span class="text-[9px] font-bold text-rose-400 uppercase block">Erros</span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center text-xs font-bold">
                                <span id="progress-current" class="text-slate-500 overflow-hidden text-ellipsis whitespace-nowrap max-w-[80%]">Preparando...</span>
                                <span id="progress-pct" class="text-blue-600 dark:text-blue-400">0%</span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-slate-800 h-3 rounded-full overflow-hidden">
                                <div id="progress-fill" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-full w-0 transition-all duration-300"></div>
                            </div>
                        </div>

                        <!-- Log console -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold">
                                <span class="text-slate-500 flex items-center gap-1.5"><i data-lucide="terminal" class="w-4 h-4"></i> Console de Execução</span>
                                <button onclick="copyLog()" class="text-blue-500 hover:text-blue-600 flex items-center gap-1 transition">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copiar Log
                                </button>
                            </div>
                            <div id="log" class="bg-slate-950 text-slate-200 border border-slate-800 rounded-2xl p-4 font-mono text-[11px] leading-relaxed h-52 overflow-y-auto max-h-52"></div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-24 right-8 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl shadow-2xl font-bold flex items-center gap-3 transform translate-y-32 opacity-0 transition-all z-50">
        <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center text-white"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg"></span>
    </div>

    <script>
        // ── State ──────────────────────────────────────────────────────
        const state = {
          emails: [],           // aliases to create (just the part before @)
          running: false,
          stopped: false,
          apiOk: false,
          zoneId: null,
          domain: null,         // detected from API
          stats: { total: 0, ok: 0, skip: 0, err: 0 },
          logLines: [],
        };

        // ── Helpers ────────────────────────────────────────────────────
        const $ = (id) => document.getElementById(id);
        const apiBase = 'https://api.cloudflare.com/client/v4';
        const PROXY = 'cloudflare_proxy.php'; // Secured PHP proxy

        function getCredentials() {
          return {
            token: $('api-token').value.trim(),
            zoneId: $('zone-id').value.trim(),
            destination: $('destination-email').value.trim(),
          };
        }

        function sleep(ms) {
          return new Promise((res) => setTimeout(res, ms));
        }

        function now() {
          return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }

        function appendLog(msg, type = 'info') {
          const log = $('log');
          const line = document.createElement('div');
          line.className = 'py-0.5 border-b border-slate-900/40 flex items-start';
          
          let colorClass = 'text-slate-300';
          if (type === 'ok') colorClass = 'text-emerald-400 font-semibold';
          else if (type === 'warn') colorClass = 'text-amber-400 font-semibold';
          else if (type === 'err') colorClass = 'text-rose-400 font-semibold';
          
          line.innerHTML = `<span class="text-slate-500 mr-2 shrink-0 select-none">[${now()}]</span><span class="${colorClass} break-all">${escHtml(msg)}</span>`;
          log.appendChild(line);
          log.scrollTop = log.scrollHeight;
          state.logLines.push(`[${now()}] ${msg}`);
        }

        function escHtml(str) {
          return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        }

        function updateStats() {
          const { total, ok, skip, err } = state.stats;
          $('stat-total').textContent = total;
          $('stat-ok').textContent = ok;
          $('stat-skip').textContent = skip;
          $('stat-err').textContent = err;
          const done = ok + skip + err;
          const pct = total > 0 ? Math.round((done / total) * 100) : 0;
          $('progress-fill').style.width = pct + '%';
          $('progress-pct').textContent = pct + '%';
        }

        // ── Save credentials to database ──────────────────────────────
        async function saveCredentials() {
          const { token, zoneId, destination } = getCredentials();
          const saveBtn = $('btn-save-creds');
          saveBtn.disabled = true;
          saveBtn.innerHTML = '⏳ Salvando...';
          
          try {
            const formData = new FormData();
            formData.append('acao', 'salvar_cloudflare_config');
            formData.append('cloudflare_token', token);
            formData.append('cloudflare_zone_id', zoneId);
            formData.append('cloudflare_dest_email', destination);
            
            const response = await fetch('processa.php', {
              method: 'POST',
              body: formData
            });
            
            const resData = await response.json();
            if (resData.sucesso) {
              mostrarToast(resData.mensagem || 'Credenciais salvas com sucesso!');
            } else {
              alert('Erro ao salvar: ' + resData.erro);
            }
          } catch (e) {
            alert('Erro de conexão ao salvar: ' + e.message);
          } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Salvar Chaves';
            lucide.createIcons();
          }
        }

        // ── Eye toggle ─────────────────────────────────────────────────
        function toggleVis(inputId) {
          const el = $(inputId);
          el.type = el.type === 'password' ? 'text' : 'password';
        }

        // ── Test credentials ───────────────────────────────────────────
        async function testCredentials() {
          const { token, zoneId } = getCredentials();
          const result = $('test-result');

          if (!token || !zoneId) {
            showResult(result, '⚠️ Preencha o API Token e o Zone ID primeiro.', 'bg-rose-50 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400');
            return;
          }

          showResult(result, '⏳ Testando conexão...', 'bg-blue-50 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400');
          $('btn-test').disabled = true;

          try {
            const data = await cfFetch(token, `${apiBase}/zones/${zoneId}`, 'GET');

            if (data.success) {
              state.apiOk = true;
              state.zoneId = zoneId;
              state.domain = data.result.name;
              showResult(result, `✅ Conectado! Domínio: ${state.domain}`, 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400');
              $('sum-domain').textContent = state.domain;
              $('sum-api').textContent = '✅ Conectado';
              $('sum-api').className = 'text-xs font-black text-emerald-500';
            } else {
              const errMsg = data.errors?.[0]?.message || 'Erro desconhecido';
              showResult(result, `❌ Erro: ${errMsg}`, 'bg-rose-50 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400');
              state.apiOk = false;
              $('sum-api').textContent = '❌ Falhou';
              $('sum-api').className = 'text-xs font-black text-rose-500';
            }
          } catch (e) {
            showResult(result, `❌ Erro de rede: ${e.message}`, 'bg-rose-50 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400');
            state.apiOk = false;
          } finally {
            $('btn-test').disabled = false;
          }
        }

        function showResult(el, msg, classes) {
          el.textContent = msg;
          el.className = `text-xs font-semibold p-3.5 rounded-xl text-center ${classes}`;
        }

        // ── Tab switching ──────────────────────────────────────────────
        function switchTab(name) {
          ['manual', 'generate', 'csv'].forEach((t) => {
            const tabBtn = $(`tab-${t}`);
            const tabPnl = $(`panel-${t}`);
            
            if (t === name) {
              tabBtn.className = 'flex-1 py-3 text-center rounded-xl bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm border border-slate-100 dark:border-slate-800 transition duration-150';
              tabPnl.classList.remove('hidden');
            } else {
              tabBtn.className = 'flex-1 py-3 text-center rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition duration-150';
              tabPnl.classList.add('hidden');
            }
          });
        }

        // ── Generate emails ────────────────────────────────────────────
        function generateEmails() {
          const prefix = $('gen-prefix').value.trim();
          const suffix = $('gen-suffix').value.trim();
          const start = parseInt($('gen-start').value, 10);
          const end = parseInt($('gen-end').value, 10);
          const pad = parseInt($('gen-padding').value, 10);

          if (!prefix) { alert('Informe um prefixo.'); return; }
          if (isNaN(start) || isNaN(end) || end < start) {
            alert('Intervalo inválido.'); return;
          }

          const list = [];
          for (let i = start; i <= end; i++) {
            const num = pad > 0 ? String(i).padStart(pad, '0') : String(i);
            list.push(`${prefix}${num}${suffix}`);
          }

          // Show preview
          const preview = $('gen-preview');
          const previewList = list.slice(0, 15).join('\n') + (list.length > 15 ? `\n... e mais ${list.length - 15} aliases` : '');
          preview.textContent = "Prévia de aliases gerados:\n" + previewList;
          preview.classList.remove('hidden');

          // Load into state
          loadEmailList(list);
        }

        // ── File upload ────────────────────────────────────────────────
        function handleDragOver(e) {
          e.preventDefault();
          $('upload-area').classList.add('dragover');
        }

        function handleDrop(e) {
          e.preventDefault();
          $('upload-area').classList.remove('dragover');
          const file = e.dataTransfer.files[0];
          if (file) processFile(file);
        }

        function handleFileUpload(e) {
          const file = e.target.files[0];
          if (file) processFile(file);
        }

        function processFile(file) {
          const reader = new FileReader();
          reader.onload = (e) => {
            const text = e.target.result;
            const lines = text
              .split(/[\r\n]+/)
              .map((l) => l.split(',')[0].trim())  // support CSV: takes first column
              .filter(Boolean);
            loadEmailList(lines);
          };
          reader.readAsText(file);
        }

        // ── Manual list parsing ────────────────────────────────────────
        $('email-list').addEventListener('input', () => {
          const lines = $('email-list').value
            .split('\n')
            .map((l) => l.trim())
            .filter(Boolean);
          if (lines.length > 0) loadEmailList(lines);
          else {
            state.emails = [];
            updateEmailPreview();
            updateSummary();
          }
        });

        // ── Load email list ────────────────────────────────────────────
        function loadEmailList(list) {
          // Normalize: remove @domain if user pasted full emails
          state.emails = [...new Set(
            list.map((e) => {
              const clean = e.toLowerCase().trim();
              return clean.includes('@') ? clean.split('@')[0] : clean;
            }).filter((e) => /^[a-z0-9._+-]+$/.test(e))
          )];

          updateEmailPreview();
          updateSummary();
        }

        function clearEmails() {
          state.emails = [];
          $('email-list').value = '';
          $('gen-preview').classList.add('hidden');
          updateEmailPreview();
          updateSummary();
        }

        function updateEmailPreview() {
          const wrap = $('email-preview-wrap');
          const chips = $('email-preview-chips');
          const count = state.emails.length;

          if (count === 0) {
            wrap.classList.add('hidden');
            return;
          }

          wrap.classList.remove('hidden');
          $('email-count-label').textContent = `${count} alias${count !== 1 ? 'es' : ''} carregado${count !== 1 ? 's' : ''}`;

          chips.innerHTML = '';
          const max = 40;
          state.emails.slice(0, max).forEach((e) => {
            const chip = document.createElement('span');
            chip.className = 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-200/50 dark:border-slate-700/50';
            chip.textContent = e;
            chips.appendChild(chip);
          });
          if (count > max) {
            const more = document.createElement('span');
            more.className = 'bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 px-3 py-1.5 rounded-xl text-xs font-bold border border-blue-100/40 dark:border-blue-900/30';
            more.textContent = `+ ${count - max} mais`;
            chips.appendChild(more);
          }
        }

        function updateSummary() {
          const { token, zoneId, destination } = getCredentials();
          $('sum-dest').textContent = destination || '—';
          $('sum-count').textContent = state.emails.length;
          if (state.domain) $('sum-domain').textContent = state.domain;
        }

        // Update summary when destination changes
        $('destination-email').addEventListener('input', updateSummary);

        // ── Options ────────────────────────────────────────────────────
        $('opt-delay').addEventListener('change', (e) => {
          $('delay-config').style.display = e.target.checked ? 'block' : 'none';
        });

        // ── Run Bot ────────────────────────────────────────────────────
        async function runBot() {
          const { token, zoneId, destination } = getCredentials();

          // Validation
          if (!token) { alert('⚠️ Informe o API Token.'); return; }
          if (!zoneId) { alert('⚠️ Informe o Zone ID.'); return; }
          if (!destination) { alert('⚠️ Informe o email de destino.'); return; }
          if (state.emails.length === 0) { alert('⚠️ Adicione pelo menos um alias para criar.'); return; }

          if (!state.apiOk) {
            const ok = confirm('A conexão com a API da Cloudflare não foi testada com sucesso. Deseja continuar mesmo assim?');
            if (!ok) return;
          }

          // Setup
          state.running = true;
          state.stopped = false;
          state.stats = { total: state.emails.length, ok: 0, skip: 0, err: 0 };
          state.logLines = [];

          const skipExisting = $('opt-skip-existing').checked;
          const enabledFlag = $('opt-enabled').checked;
          const useDelay = $('opt-delay').checked;
          const delayMs = useDelay ? parseInt($('delay-ms').value, 10) || 300 : 0;
          const domain = state.domain || 'seu-dominio.com';

          // UI
          $('btn-run').classList.add('hidden');
          $('btn-stop').classList.remove('hidden');
          $('progress-section').classList.remove('hidden');
          $('log').innerHTML = '';

          updateStats();
          appendLog(`🚀 Iniciando criação de ${state.emails.length} aliases → ${destination}`, 'info');
          appendLog(`Domínio: ${domain} | Zone: ${zoneId}`, 'info');
          appendLog('─'.repeat(40), 'info');

          // Fetch existing rules first (to detect duplicates)
          let existingRules = new Set();
          if (skipExisting) {
            try {
              appendLog('Buscando regras existentes na Cloudflare (timeout: 15s)...', 'info');
              // Race against a 15-second timeout
              existingRules = await Promise.race([
                fetchExistingRules(token, zoneId),
                new Promise((_, reject) =>
                  setTimeout(() => reject(new Error('Timeout ao buscar regras existentes — pulando verificação')), 15000)
                ),
              ]);
              appendLog(`${existingRules.size} regras existentes encontradas na Cloudflare.`, 'info');
            } catch (e) {
              appendLog(`⚠️ ${e.message} — continuando sem verificação de duplicatas.`, 'warn');
            }
          }

          // Main loop
          for (let i = 0; i < state.emails.length; i++) {
            if (state.stopped) {
              appendLog('⛔ Processamento interrompido pelo usuário.', 'warn');
              break;
            }

            const alias = state.emails[i];
            const fullEmail = `${alias}@${domain}`;

            $('progress-current').textContent = `Processando ${i + 1}/${state.emails.length}: ${fullEmail}`;
            updateStats();

            // Skip if exists
            if (skipExisting && existingRules.has(alias.toLowerCase())) {
              state.stats.skip++;
              appendLog(`PULADO  ${fullEmail} (já existe)`, 'warn');
              updateStats();
              if (delayMs > 0) await sleep(delayMs);
              continue;
            }

            // Create rule
            try {
              const result = await createEmailRule(token, zoneId, fullEmail, destination, enabledFlag);
              if (result.success) {
                state.stats.ok++;
                appendLog(`CRIADO  ${fullEmail} → ${destination}`, 'ok');
                existingRules.add(alias.toLowerCase()); // prevent dupe if retried
              } else {
                const errMsg = result.errors?.[0]?.message || 'Erro desconhecido';
                if (skipExisting && (errMsg.toLowerCase().includes('already') || errMsg.toLowerCase().includes('exist') || errMsg.toLowerCase().includes('duplicate'))) {
                  state.stats.skip++;
                  appendLog(`PULADO  ${fullEmail} (duplicado: ${errMsg})`, 'warn');
                } else {
                  state.stats.err++;
                  appendLog(`ERRO    ${fullEmail}: ${errMsg}`, 'err');
                }
              }
            } catch (e) {
              state.stats.err++;
              appendLog(`ERRO    ${fullEmail}: ${e.message}`, 'err');
            }

            updateStats();
            if (delayMs > 0) await sleep(delayMs);
          }

          // Done
          state.running = false;
          const { ok, skip, err, total } = state.stats;
          appendLog('─'.repeat(40), 'info');
          appendLog(`✅ Concluído! Criados: ${ok} | Pulados: ${skip} | Erros: ${err} | Total: ${total}`, 'ok');

          $('progress-current').textContent = `Concluído! ✅ ${ok} criados, ⚠️ ${skip} pulados, ❌ ${err} erros`;
          $('progress-fill').style.width = '100%';
          $('progress-pct').textContent = '100%';

          $('btn-stop').classList.add('hidden');
          $('btn-run').classList.remove('hidden');
        }

        function stopBot() {
          state.stopped = true;
          $('btn-stop').disabled = true;
          $('btn-stop').innerHTML = '⏳ Parando...';
          setTimeout(() => {
            $('btn-stop').classList.add('hidden');
            $('btn-run').classList.remove('hidden');
            $('btn-stop').disabled = false;
            $('btn-stop').innerHTML = `<i data-lucide="square" class="w-5 h-5"></i> Parar Processo`;
            lucide.createIcons();
          }, 1500);
        }

        // ── Cloudflare API calls ───────────────────────────────────────

        /**
         * Fetch all existing email routing rules for the zone.
         * Returns a Set of alias names (lowercase, before @).
         */
        async function fetchExistingRules(token, zoneId) {
          const rules = new Set();
          let page = 1;
          const perPage = 50;

          while (true) {
            const data = await cfFetch(
              token,
              `${apiBase}/zones/${zoneId}/email/routing/rules?page=${page}&per_page=${perPage}`,
              'GET'
            );
            if (!data.success) break;

            for (const rule of data.result || []) {
              for (const matcher of rule.matchers || []) {
                if (matcher.field === 'to' && matcher.value) {
                  const local = matcher.value.split('@')[0].toLowerCase();
                  rules.add(local);
                }
              }
            }

            const info = data.result_info;
            if (!info || page >= info.total_pages) break;
            page++;
          }

          return rules;
        }

        /**
         * Create a single email routing rule via the Cloudflare API.
         */
        async function createEmailRule(token, zoneId, fromEmail, toEmail, enabled) {
          const body = {
            name: `Redirect: ${fromEmail}`,
            enabled: enabled,
            matchers: [
              {
                type: 'literal',
                field: 'to',
                value: fromEmail,
              },
            ],
            actions: [
              {
                type: 'forward',
                value: [toEmail],
              },
            ],
            priority: 0,
          };

          return cfFetch(
            token,
            `${apiBase}/zones/${zoneId}/email/routing/rules`,
            'POST',
            body
          );
        }

        /**
         * All Cloudflare API calls go through the local PHP proxy
         * to avoid CORS restrictions in the browser.
         * Aborts automatically after 20 seconds.
         */
        async function cfFetch(token, url, method = 'GET', body = null) {
          const controller = new AbortController();
          const timer = setTimeout(() => controller.abort(), 20000);

          try {
            const res = await fetch(PROXY, {
              method: 'POST',
              signal: controller.signal,
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ url, method, token, body }),
            });

            if (!res.ok) {
              const text = await res.text();
              throw new Error(`Proxy HTTP ${res.status}: ${text.slice(0, 200)}`);
            }

            return res.json();
          } catch (e) {
            if (e.name === 'AbortError') throw new Error('Timeout: proxy não respondeu em 20s');
            throw e;
          } finally {
            clearTimeout(timer);
          }
        }

        // ── Copy log ───────────────────────────────────────────────────
        function copyLog() {
          const text = state.logLines.join('\n');
          navigator.clipboard.writeText(text).then(() => {
            mostrarToast('Log copiado para a área de transferência!');
          });
        }

        // Initialize Lucide Icons & Update Summary
        lucide.createIcons();
        updateSummary();
    </script>
</body>
</html>
