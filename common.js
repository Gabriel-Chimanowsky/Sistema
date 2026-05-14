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
    navigator.clipboard.writeText(txt).then(() => {
        if (typeof mostrarToast === 'function') mostrarToast(msg);
        else alert(msg);
    });
}

function mudarStatusMassa() {
    const checks = document.querySelectorAll('.check-conta:checked');
    const status = document.getElementById('status_massa').value;
    if (!status || checks.length === 0) {
        if (window.location.pathname.includes('index.php')) {
            return mostrarToast('Selecione contas e status');
        } else {
            window.location.href = 'index.php';
            return;
        }
    }
    const ids = Array.from(checks).map(c => c.closest('tr').dataset.id);
    const f = document.createElement('form'); f.method = 'POST'; f.action = 'processa.php';
    f.innerHTML = `<input name="acao" value="mudar_status_massa"><input name="ids" value="${ids.join(',')}"><input name="novo_status" value="${status}">`;
    document.body.appendChild(f); f.submit();
}

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
