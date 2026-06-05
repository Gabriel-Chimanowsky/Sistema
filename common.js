// Funções globais para o Navbar e ações comuns
function mostrarToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    const msgEl = document.getElementById('toastMsg');
    if (msgEl) msgEl.innerText = msg;
    t.classList.remove('translate-y-32', 'opacity-0');
    setTimeout(() => t.classList.add('translate-y-32', 'opacity-0'), 3000);
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
        if (!confirm(`Regerar dados de ${checks.length} conta(s)?`)) return;
        submitLote('regerar_massa', { ids });
        return;
    }

    // Deletar
    if (acao === 'deletar') {
        if (!confirm(`Excluir permanentemente ${checks.length} conta(s)?`)) return;
        submitLote('del_massa', { ids });
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
