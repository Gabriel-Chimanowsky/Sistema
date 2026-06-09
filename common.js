// Funções globais para o Navbar e ações comuns
function mostrarToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    const msgEl = document.getElementById('toastMsg');
    if (msgEl) msgEl.innerText = msg;
    t.classList.remove('translate-y-32', 'opacity-0');
    setTimeout(() => t.classList.add('translate-y-32', 'opacity-0'), 3000);
}

function showConfirmCard(title, text, confirmLabel, confirmColorClass, onConfirm) {
    // Se o modal já existe, remove
    let existing = document.getElementById('customConfirmModal');
    if (existing) existing.remove();

    const colorClasses = confirmColorClass || 'bg-blue-600 hover:bg-blue-700 shadow-blue-600/30';
    
    const html = `
    <div id="customConfirmModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4 transition-opacity duration-200 opacity-0">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-8 max-w-sm w-full shadow-2xl border border-slate-200 dark:border-slate-800 transform scale-95 transition-all duration-200" id="customConfirmContent">
            <h3 class="text-xl font-black mb-3 text-slate-900 dark:text-white">${title}</h3>
            <p class="text-slate-600 dark:text-slate-400 mb-8 font-medium">${text}</p>
            <div class="flex justify-end gap-3">
                <button type="button" id="btnCancelConfirm" class="px-5 py-2.5 rounded-xl font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition">Cancelar</button>
                <button type="button" id="btnOkConfirm" class="px-5 py-2.5 rounded-xl font-bold text-white shadow-lg ${colorClasses} transition">${confirmLabel}</button>
            </div>
        </div>
    </div>`;
    
    document.body.insertAdjacentHTML('beforeend', html);
    const modal = document.getElementById('customConfirmModal');
    const content = document.getElementById('customConfirmContent');
    const btnCancel = document.getElementById('btnCancelConfirm');
    const btnOk = document.getElementById('btnOkConfirm');

    // Fade in
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    });

    const close = () => {
        modal.classList.add('opacity-0');
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
        setTimeout(() => modal.remove(), 200);
    };

    btnCancel.addEventListener('click', close);
    btnOk.addEventListener('click', () => {
        close();
        if(onConfirm) onConfirm();
    });
}

function copiar(txt, msg) {
    if (!txt) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(() => {
            if (typeof mostrarToast === 'function') mostrarToast(msg);
            else alert(msg);
        }).catch(err => {
            copiarFallback(txt, msg);
        });
    } else {
        copiarFallback(txt, msg);
    }
}

function copiarFallback(txt, msg) {
    const textArea = document.createElement("textarea");
    textArea.value = txt;
    // Prevent scrolling on mobile devices
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            if (typeof mostrarToast === 'function') mostrarToast(msg);
            else alert(msg);
        } else {
            console.error('Fallback: Copy command was unsuccessful');
        }
    } catch (err) {
        console.error('Fallback: Unable to copy', err);
    }
    document.body.removeChild(textArea);
}


function toggleSelectPessoa(val) {
    const sp = document.getElementById('select_pessoa_massa');
    if (!sp) return;
    sp.classList.toggle('hidden', val !== 'dono');
}

function executarAcaoLoteNavbar() {
    const sel = document.getElementById('status_massa');
    const acao = sel ? sel.value : '';
    const checks = document.querySelectorAll('.check-conta:checked');

    if (!acao) return mostrarToast('Selecione uma ação em lote');

    if (!window.location.pathname.includes('index.php')) {
        window.location.href = 'index.php';
        return;
    }

    if (checks.length === 0) return mostrarToast('Selecione ao menos uma conta');

    const ids = Array.from(checks).map(c => c.closest('tr[data-id]').dataset.id).join(',');

    // Status
    if (acao.startsWith('status:')) {
        const novo_status = acao.split(':')[1];
        submitLote('mudar_status_massa', { ids, novo_status });
        return;
    }

    // Mudar Dono
    if (acao === 'dono') {
        const sp = document.getElementById('select_pessoa_massa');
        const pessoa_id = sp ? sp.value : '';
        submitLote('vincular_pessoa_massa', { ids, pessoa_id });
        return;
    }

    // Regerar
    if (acao === 'regerar') {
        showConfirmCard('Regerar Contas', `Deseja regerar os dados de ${checks.length} conta(s)?`, 'Sim, Regerar', 'bg-orange-500 hover:bg-orange-600 shadow-orange-500/30', () => {
            submitLote('regerar_massa', { ids });
        });
        return;
    }

    // Deletar
    if (acao === 'deletar') {
        showConfirmCard('Excluir Contas', `Tem certeza que deseja excluir permanentemente ${checks.length} conta(s)?`, 'Sim, Excluir', 'bg-red-600 hover:bg-red-700 shadow-red-600/30', () => {
            submitLote('del_massa', { ids });
        });
        return;
    }
}

function submitLote(acao, campos) {
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = 'processa.php';
    let html = `<input name="acao" value="${acao}">`;
    for (const [k, v] of Object.entries(campos)) {
        html += `<input name="${k}" value="${v}">`;
    }
    f.innerHTML = html;
    document.body.appendChild(f);
    f.submit();
}

// Mantida por compatibilidade (navbar antigo)
function mudarStatusMassa() { executarAcaoLoteNavbar(); }


function exportarContas() {
    const checks = document.querySelectorAll('.check-conta:checked');
    const ids = Array.from(checks).map(c => c.closest('tr').dataset.id);
    
    // Se estiver em outra página e não houver selecionados, apenas exporta as pendentes (comportamento padrão do PHP)
    const f = document.createElement('form'); f.method = 'POST'; f.action = 'processa.php';
    f.innerHTML = `<input name="acao" value="exportar_csv"><input name="ids" value="${ids.join(',')}">`;
    document.body.appendChild(f); f.submit();
}

function copiarSelecionados() {
    const checks = document.querySelectorAll('.check-conta:checked');
    if (checks.length === 0) {
        if (window.location.pathname.includes('index.php')) {
            return mostrarToast('Selecione ao menos uma conta');
        } else {
            window.location.href = 'index.php';
            return;
        }
    }
    
    let textoFinal = "";
    checks.forEach((c) => {
        const row = c.closest('tr');
        const data = JSON.parse(row.dataset.json);
        textoFinal += `nome: ${data.nome} ${data.sobrenome}\nuser: ${data.username}\nacesso: ${data.email}\nsenha: ${data.senha}\ncódigo 2fa: ${data.codigo_2fa || 'N/A'}\n\ncookies: ${data.cookies || 'N/A'}\n\n-----------------------------------------------\n\n`;
    });
    
    copiar(textoFinal.trim(), `${checks.length} conta(s) copiada(s)!`);
}

// ── Global Scroll Preservation ────────────────────────────────
(function() {
    const PATH = window.location.pathname;
    
    // Restaurar scroll
    window.addEventListener('DOMContentLoaded', () => {
        const saved = sessionStorage.getItem('global_scroll_y_' + PATH);
        if (saved !== null) {
            requestAnimationFrame(() => {
                window.scrollTo({ top: parseInt(saved, 10), behavior: 'instant' });
                sessionStorage.removeItem('global_scroll_y_' + PATH);
            });
        }
    });

    // Salvar scroll ao descarregar (antes de submits e recarregamentos)
    window.addEventListener('beforeunload', () => {
        sessionStorage.setItem('global_scroll_y_' + PATH, window.scrollY);
    });

    // Limpar scroll salvo ao navegar explicitamente pelo navbar
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('nav a, header a, a.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                sessionStorage.removeItem('global_scroll_y_' + PATH);
                try {
                    const url = new URL(link.href);
                    sessionStorage.removeItem('global_scroll_y_' + url.pathname);
                } catch(e) {}
            });
        });
    });
})();
