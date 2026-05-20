<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

if (isFinanceiro()) {
    header("Location: relatorio.php");
    exit;
}

$stmtPessoas = $pdo->query("SELECT * FROM pessoas ORDER BY nome ASC");
$pessoas = $stmtPessoas->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pessoas - Facebook Account Manager V4.3</title>
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

    <div class="max-w-[800px] mx-auto px-4 mt-24">
        <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-10 border border-slate-200 dark:border-slate-800 shadow-2xl">
            <h1 class="text-3xl font-black mb-8 flex items-center gap-3">
                <i data-lucide="user-plus" class="w-8 h-8 text-blue-600"></i>
                Gerenciar Clientes
            </h1>

            <form method="POST" action="processa.php" class="flex gap-3 mb-12">
                <input type="hidden" name="acao" value="add_pessoa">
                <input type="text" name="nome_pessoa" placeholder="Nome do Cliente / Destinatário" required 
                    class="flex-1 bg-slate-50 dark:bg-slate-800 border-2 border-transparent focus:border-blue-500 p-4 rounded-2xl outline-none transition-all font-bold">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-2xl font-black shadow-lg shadow-blue-600/30 transition-all active:scale-95">
                    Adicionar
                </button>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($pessoas as $p): ?>
                    <div class="group flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-transparent hover:border-slate-200 dark:hover:border-slate-700 transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white dark:bg-slate-800 rounded-xl flex items-center justify-center text-slate-400 group-hover:text-blue-500 transition-colors">
                                <i data-lucide="user" class="w-5 h-5"></i>
                            </div>
                            <span class="font-bold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($p['nome']) ?></span>
                        </div>
                        
                        <button type="button" onclick="abrirModal('Excluir <?= htmlspecialchars(addslashes($p['nome'])) ?>? As contas vinculadas a ele ficarão sem dono.', this.nextElementSibling)" 
                            class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                        <form method="POST" action="processa.php" class="hidden">
                            <input type="hidden" name="acao" value="del_pessoa">
                            <input type="hidden" name="pessoa_id" value="<?= $p['id'] ?>">
                        </form>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($pessoas)): ?>
                    <div class="col-span-2 text-center py-10">
                        <i data-lucide="users" class="w-12 h-12 text-slate-300 mx-auto mb-4"></i>
                        <p class="text-slate-400 font-medium">Nenhum cliente cadastrado ainda.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Layout -->
    <div id="modalConfirmacao" class="hidden fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-[100] items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 p-8 rounded-[2rem] shadow-2xl max-w-sm w-full border border-slate-200 dark:border-slate-800 transform scale-90 transition-all">
            <div class="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-2xl flex items-center justify-center mb-6 mx-auto">
                <i data-lucide="alert-triangle" class="w-10 h-10"></i>
            </div>
            <h3 class="text-2xl font-black text-center mb-2">Tem certeza?</h3>
            <p id="textoModal" class="text-slate-500 dark:text-slate-400 text-center mb-8 font-medium"></p>
            <div class="flex gap-3">
                <button onclick="fecharModal()" class="flex-1 px-4 py-3 bg-slate-100 dark:bg-slate-800 rounded-2xl font-bold transition hover:bg-slate-200">Cancelar</button>
                <button onclick="confirmarModal()" class="flex-1 px-4 py-3 bg-red-600 text-white rounded-2xl font-bold shadow-lg shadow-red-600/30 transition hover:bg-red-700">Excluir</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
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
    </script>
    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-24 right-8 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl shadow-2xl font-bold flex items-center gap-3 transform translate-y-32 opacity-0 transition-all z-50">
        <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center text-white"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg"></span>
    </div>

</body>
</html>