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
    <script src="tailwind.js?v=1"></script>
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

            <!-- Nova Seção: Financeiro -->
            <div class="border-t pt-10 dark:border-slate-800 grid grid-cols-1 md:grid-cols-3 gap-10">
                <div class="space-y-6 md:col-span-3">
                    <div class="flex items-center justify-between border-b pb-2 dark:border-slate-800">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Valores Financeiros (Tabela de Preços)</h3>
                        <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 rounded-full border border-emerald-200 dark:border-emerald-800">
                            🛡️ Histórico Protegido
                        </span>
                    </div>
                    
                    <div class="bg-blue-50/70 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/50 p-4 rounded-2xl text-xs flex items-start gap-3">
                        <i data-lucide="shield-check" class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5"></i>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                            <strong>Separação Inteligente:</strong> Ao atualizar os valores abaixo, os novos preços serão aplicados apenas às <strong>novas contas e adicionais vinculados a partir de agora</strong>. As contas que já foram vinculadas/cobradas anteriormente permanecerão com os <strong>valores históricos congelados intactos</strong>.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Preço Base do Perfil (R$)</label>
                            <input type="number" step="0.01" name="preco_perfil" value="<?= htmlspecialchars($config['preco_perfil'] ?? '20.00') ?>" 
                                class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Adicional BM (R$)</label>
                            <input type="number" step="0.01" name="preco_bm" value="<?= htmlspecialchars($config['preco_bm'] ?? '30.00') ?>" 
                                class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Adicional Página (R$)</label>
                            <input type="number" step="0.01" name="preco_pagina" value="<?= htmlspecialchars($config['preco_pagina'] ?? '10.00') ?>" 
                                class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nova Seção: Integração Slack & Alertas -->
            <div class="border-t pt-10 dark:border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-10">
                <div class="space-y-6">
                    <div class="flex items-center justify-between border-b pb-2 dark:border-slate-800">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Integração Slack & Alertas</h3>
                        <a href="slack.php" class="text-xs font-bold text-blue-500 hover:underline flex items-center gap-1">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Abrir Painel Slack
                        </a>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Token do Slack (Bot OAuth Token)</label>
                        <input type="text" id="slack_token_input" name="slack_token" value="<?= htmlspecialchars($config['slack_token'] ?? '') ?>" 
                            class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold font-mono" placeholder="xoxb-...">
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Destinatários dos Alertas dos Apps & Listas</label>
                            <span id="destinatariosCount" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300">0 pessoa(s)/canal(is)</span>
                        </div>
                        <textarea id="slack_canal_notificacao" name="slack_canal_notificacao" rows="3" oninput="atualizarPreviewDestinatarios()"
                            class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold font-mono text-sm leading-relaxed" 
                            placeholder="Ex: U0123456789, U0987654321, #alertas, C0123456789"><?= htmlspecialchars($config['slack_canal_notificacao'] ?? '') ?></textarea>
                        
                        <!-- Badges preview dos destinatários -->
                        <div id="destinatariosPreviewContainer" class="flex flex-wrap gap-1.5 pt-1"></div>
                    </div>

                    <!-- Botão Disparar Alerta de Teste -->
                    <div class="pt-2">
                        <button type="button" id="btnTestarSlack" onclick="testarEnvioAlertaSlack()" 
                            class="w-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold py-3 px-5 rounded-2xl transition active:scale-95 flex items-center justify-center gap-2 text-xs cursor-pointer border border-slate-200 dark:border-slate-700 shadow-sm">
                            <i data-lucide="send" id="iconTestarSlack" class="w-4 h-4 text-purple-600 dark:text-purple-400"></i>
                            <span id="textoTestarSlack">Disparar Alerta de Teste para Todos</span>
                        </button>
                        <div id="resultadoTesteSlack" class="hidden mt-3 p-3 rounded-2xl text-xs font-semibold space-y-1"></div>
                    </div>
                </div>

                <div class="space-y-6 flex flex-col justify-start">
                    <div class="bg-purple-50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/50 p-6 rounded-3xl text-xs space-y-3">
                        <div class="font-bold text-purple-800 dark:text-purple-400 flex items-center gap-2">
                            <i data-lucide="users" class="w-4 h-4"></i>
                            Como Adicionar Múltiplas Pessoas no Slack
                        </div>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                            Agora o sistema suporta <strong>múltiplos destinatários</strong> para os alertas de aplicativos (queda de app, retorno online e aprovações no Meta).
                        </p>
                        <div class="bg-white/80 dark:bg-slate-900/80 p-3.5 rounded-xl border border-purple-200/50 dark:border-purple-800/40 space-y-2">
                            <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <i data-lucide="user-plus" class="w-3.5 h-3.5 text-purple-600"></i>
                                Para pegar o ID de uma pessoa no Slack:
                            </div>
                            <ol class="list-decimal list-inside space-y-1 text-slate-600 dark:text-slate-400 text-[11px]">
                                <li>Abra o Slack e clique na foto/perfil da pessoa.</li>
                                <li>Clique no botão de <strong>três pontinhos (...)</strong> ao lado da mensagem.</li>
                                <li>Clique em <strong>"Copiar ID do membro"</strong> (ex: <code class="font-mono bg-purple-100 dark:bg-purple-950 px-1 py-0.5 rounded font-bold">U08ABCDEF12</code>).</li>
                                <li>Cole no campo ao lado separando por <strong>vírgula</strong> ou quebra de linha.</li>
                            </ol>
                        </div>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium text-[11px]">
                            💡 Se quiser que o bot mande para um canal em grupo, basta colocar o ID do canal (ex: <code class="font-mono bg-purple-100 dark:bg-purple-950 px-1 py-0.5 rounded font-bold">C0123456789</code>) ou o nome com # (ex: <code class="font-mono bg-purple-100 dark:bg-purple-950 px-1 py-0.5 rounded font-bold">#geral</code>) e certificar-se de ter feito <code class="font-mono bg-purple-100 dark:bg-purple-950 px-1 py-0.5 rounded font-bold">/invite @Bot</code> no canal.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Nova Seção: Integração Cloudflare Email Routing -->
            <div class="border-t pt-10 dark:border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-10">
                <div class="space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400 border-b pb-2 dark:border-slate-800">Credenciais Cloudflare</h3>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Cloudflare API Token</label>
                        <input type="password" name="cloudflare_token" value="<?= htmlspecialchars($config['cloudflare_token'] ?? '') ?>" 
                            class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold font-mono" placeholder="Seu Cloudflare API Token...">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">Zone ID</label>
                        <input type="text" name="cloudflare_zone_id" value="<?= htmlspecialchars($config['cloudflare_zone_id'] ?? '') ?>" 
                            class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold font-mono" placeholder="Zone ID do Domínio...">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-500 uppercase tracking-wider ml-1">E-mail de Destino Padrão</label>
                        <input type="email" name="cloudflare_dest_email" value="<?= htmlspecialchars($config['cloudflare_dest_email'] ?? '') ?>" 
                            class="w-full bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold" placeholder="seuemail@gmail.com">
                    </div>
                </div>

                <div class="space-y-6 flex flex-col justify-end">
                    <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/50 p-6 rounded-3xl text-xs space-y-3">
                        <div class="font-bold text-blue-800 dark:text-blue-400 flex items-center gap-2">
                            <i data-lucide="info" class="w-4 h-4"></i>
                            Sobre a Automação Cloudflare
                        </div>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                            Estas chaves são utilizadas na aba <strong>Cloudflare</strong> para a criação em massa de redirecionamentos de e-mail automaticamente.
                        </p>
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                            Certifique-se de que o token possua as permissões de <code>Email Routing Rules: Edit</code> e <code>Zone: Read</code> na Cloudflare.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Link para Central de Ferramentas -->
    <div class="max-w-[1000px] mx-auto px-4 mt-6">
        <a href="tools/index.php" class="flex items-center gap-3 p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl hover:border-amber-400 dark:hover:border-amber-600 transition-all group">
            <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center flex-shrink-0">
                <i data-lucide="wrench" class="w-5 h-5 text-amber-600 dark:text-amber-400"></i>
            </div>
            <div>
                <div class="font-bold text-sm">Central de Ferramentas e Diagnósticos</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Acesse diagnósticos do Slack, Cloudflare, reconstrução de listas e operações avançadas de banco de dados.</div>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-slate-400 group-hover:text-amber-500 transition ml-auto"></i>
        </a>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-24 right-8 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl shadow-2xl font-bold flex items-center gap-3 transform translate-y-32 opacity-0 transition-all z-50">
        <div id="toastIcon" class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center text-white"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg"></span>
    </div>

    <script>
        function showCustomToast(msg, isError = false) {
            const toast = document.getElementById('toast');
            const toastMsg = document.getElementById('toastMsg');
            const toastIcon = document.getElementById('toastIcon');
            
            toastMsg.textContent = msg;
            if (isError) {
                toastIcon.className = 'w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-white';
                toastIcon.innerHTML = '<i data-lucide="x" class="w-4 h-4"></i>';
            } else {
                toastIcon.className = 'w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center text-white';
                toastIcon.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
            }
            lucide.createIcons();
            
            toast.classList.remove('translate-y-32', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-32', 'opacity-0');
            }, 4000);
        }

        function extrairDestinatarios(str) {
            if (!str) return [];
            return str.split(/[\r\n,;\s]+/)
                .map(s => s.trim().replace(/^["']|["']$/g, ''))
                .filter(s => s.length > 0)
                .filter((val, idx, arr) => arr.indexOf(val) === idx);
        }

        function atualizarPreviewDestinatarios() {
            const val = document.getElementById('slack_canal_notificacao')?.value || '';
            const lista = extrairDestinatarios(val);
            const countElem = document.getElementById('destinatariosCount');
            const container = document.getElementById('destinatariosPreviewContainer');

            if (countElem) {
                countElem.textContent = `${lista.length} pessoa(s)/canal(is)`;
            }

            if (!container) return;
            container.innerHTML = '';

            lista.forEach(dest => {
                const badge = document.createElement('span');
                const isUser = dest.startsWith('U') || dest.startsWith('W');
                const isChannel = dest.startsWith('C') || dest.startsWith('#') || dest.startsWith('G');
                
                let icon = isUser ? '👤' : (isChannel ? '💬' : '📍');
                let colorClass = isUser 
                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-900' 
                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900';

                badge.className = `inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-mono font-bold border shadow-xs ${colorClass}`;
                badge.innerHTML = `<span>${icon}</span> <span>${dest}</span>`;
                container.appendChild(badge);
            });
        }

        async function testarEnvioAlertaSlack() {
            const btn = document.getElementById('btnTestarSlack');
            const texto = document.getElementById('textoTestarSlack');
            const icon = document.getElementById('iconTestarSlack');
            const resultado = document.getElementById('resultadoTesteSlack');
            const token = document.getElementById('slack_token_input')?.value.trim() || '';
            const destinatarios = document.getElementById('slack_canal_notificacao')?.value.trim() || '';

            if (!token) {
                showCustomToast('Preencha o Token do Slack primeiro.', true);
                return;
            }

            const destList = extrairDestinatarios(destinatarios);
            if (destList.length === 0) {
                showCustomToast('Adicione pelo menos 1 destinatário (ID de usuário ou canal).', true);
                return;
            }

            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            texto.textContent = `Enviando para ${destList.length} destinatário(s)...`;
            icon.setAttribute('data-lucide', 'loader');
            icon.classList.add('animate-spin');
            lucide.createIcons();

            resultado.classList.add('hidden');
            resultado.innerHTML = '';

            try {
                const formData = new FormData();
                formData.append('acao', 'testar_slack_alerta');
                formData.append('token', token);
                formData.append('destinatarios', destinatarios);

                const resp = await fetch('processa.php', {
                    method: 'POST',
                    body: formData
                });
                const dados = await resp.json();

                resultado.classList.remove('hidden');

                if (dados.sucesso) {
                    resultado.className = 'mt-3 p-3 rounded-2xl text-xs font-semibold space-y-1.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900';
                    let html = `<div class="flex items-center gap-2 font-bold"><i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i> ${dados.mensagem}</div>`;
                    
                    if (dados.detalhes && dados.detalhes.length > 0) {
                        html += '<div class="pt-1 text-[11px] space-y-1">';
                        dados.detalhes.forEach(d => {
                            if (d.ok) {
                                html += `<div class="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">✅ <strong>${d.destinatario}</strong>: Recebido com sucesso!</div>`;
                            } else {
                                html += `<div class="flex items-center gap-1.5 text-red-600 dark:text-red-400">❌ <strong>${d.destinatario}</strong>: Falha (${d.erro})</div>`;
                            }
                        });
                        html += '</div>';
                    }
                    resultado.innerHTML = html;
                    showCustomToast(`Alerta de teste enviado com sucesso! (${dados.enviados}/${dados.total})`);
                } else {
                    resultado.className = 'mt-3 p-3 rounded-2xl text-xs font-semibold space-y-1.5 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-900';
                    let html = `<div class="flex items-center gap-2 font-bold"><i data-lucide="alert-circle" class="w-4 h-4 text-red-500"></i> ${dados.mensagem || 'Erro no envio do teste.'}</div>`;
                    if (dados.detalhes && dados.detalhes.length > 0) {
                        html += '<div class="pt-1 text-[11px] space-y-1">';
                        dados.detalhes.forEach(d => {
                            html += `<div class="flex items-center gap-1.5 text-red-600 dark:text-red-400">❌ <strong>${d.destinatario}</strong>: ${d.erro || 'Erro'}</div>`;
                        });
                        html += '</div>';
                    }
                    resultado.innerHTML = html;
                    showCustomToast('Falha ao enviar alerta de teste.', true);
                }
            } catch (err) {
                resultado.classList.remove('hidden');
                resultado.className = 'mt-3 p-3 rounded-2xl text-xs font-semibold bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-900';
                resultado.textContent = 'Erro ao conectar ao servidor: ' + err.message;
                showCustomToast('Erro de comunicação.', true);
            } finally {
                btn.disabled = false;
                btn.classList.remove('opacity-70', 'cursor-not-allowed');
                texto.textContent = 'Disparar Alerta de Teste para Todos';
                icon.setAttribute('data-lucide', 'send');
                icon.classList.remove('animate-spin');
                lucide.createIcons();
            }
        }

        // Executar inicialização do preview
        document.addEventListener('DOMContentLoaded', () => {
            atualizarPreviewDestinatarios();
        });
        atualizarPreviewDestinatarios();
        lucide.createIcons();
    </script>
</body>
</html>