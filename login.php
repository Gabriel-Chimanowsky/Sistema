<?php
require_once 'conexao.php';
session_start();

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE login = ?");
    $stmt->execute([$login]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_login'] = $usuario['login'];
        
        // Se for a Kamilla, redireciona direto para o financeiro
        if ($usuario['login'] === 'Kamilla') {
            header("Location: relatorio.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $erro = 'Login ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Facebook Account Manager V4.3</title>
    <script src="tailwind.js?v=1"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            950: '#020617',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        .glass { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); }
        @media (prefers-color-scheme: dark) {
            .glass { background: rgba(15, 23, 42, 0.8) !important; }
        }
    </style>
</head>
<body class="bg-slate-100 dark:bg-slate-950 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-black text-blue-600 dark:text-blue-400 tracking-tight">Facebook</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Gerenciador de Contas Profissional</p>
        </div>

        <div class="glass p-8 rounded-3xl shadow-2xl border border-white/20 dark:border-slate-800">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 text-center">Acesse sua conta</h2>

            <?php if ($erro): ?>
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 p-3 rounded-xl mb-6 text-sm font-semibold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= $erro ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Login</label>
                    <input type="text" name="login" required 
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition-all dark:text-white"
                        placeholder="Seu usuário">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Senha</label>
                    <input type="password" name="senha" required 
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition-all dark:text-white"
                        placeholder="••••••••">
                </div>

                <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-600/30 transition-all hover:scale-[1.02] active:scale-[0.98]">
                    Entrar no Painel
                </button>
            </form>
        </div>

        <p class="text-center mt-8 text-slate-400 text-xs font-medium uppercase tracking-widest">
            &copy; <?= date('Y') ?> Facebook SaaS System - V4.3
        </p>
    </div>
</body>
</html>
