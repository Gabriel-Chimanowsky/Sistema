<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

// Pegar mês selecionado ou atual
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$preco_unidade = 20;

$meses_nomes = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

$ano = substr($mes_filtro, 0, 4);
$mes_num = substr($mes_filtro, 5, 2);
$mes_por_extenso = $meses_nomes[$mes_num] . " " . $ano;

// 1. Clientes COM movimentação no mês
$sql_ativos = "SELECT p.id, p.nome, COUNT(c.id) as total 
                FROM pessoas p 
                INNER JOIN contas c ON p.id = c.destinada_a 
                WHERE DATE_FORMAT(c.data_vinculo, '%Y-%m') = ?
                AND LOWER(p.nome) != 'administrador'
                GROUP BY p.id 
                ORDER BY total DESC";
$stmtAtivos = $pdo->prepare($sql_ativos);
$stmtAtivos->execute([$mes_filtro]);
$ativos = $stmtAtivos->fetchAll();

// 2. Clientes SEM movimentação no mês
$ids_ativos = array_column($ativos, 'id');
$in_query = "";
if (!empty($ids_ativos)) {
    $in_query = "AND id NOT IN (" . implode(',', $ids_ativos) . ")";
}
$sql_outros = "SELECT nome FROM pessoas 
               WHERE LOWER(nome) != 'administrador' 
               $in_query 
               ORDER BY nome ASC";
$outros = $pdo->query($sql_outros)->fetchAll();

// Estatísticas Gerais do Período
$stmtTotalGeral = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE DATE_FORMAT(data_vinculo, '%Y-%m') = ?");
$stmtTotalGeral->execute([$mes_filtro]);
$total_geral_mes = $stmtTotalGeral->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - Facebook Account Manager V4.3</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media'
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="common.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        @media (prefers-color-scheme: dark) {
            .glass-nav { background: rgba(15, 23, 42, 0.95) !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
        }
        @media print {
            .no-print { display: none !important; }
            .print-break { 
                margin-bottom: 20px !important; 
                border-bottom: 2px dashed #eee !important;
                padding-bottom: 20px !important;
            }
            body { background: white !important; color: black !important; padding: 0 !important; margin: 0 !important; }
            .shadow-2xl, .shadow-xl, .shadow-lg { box-shadow: none !important; }
            .bg-white, .dark\:bg-slate-900 { background: white !important; }
            .border { border-color: #eee !important; }
            .max-w-\[1000px\] { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .rounded-\[2\.5rem\], .rounded-3xl, .rounded-2xl { border-radius: 0 !important; border: none !important; }
            .mt-24 { margin-top: 0 !important; }
            .p-10 { padding: 10px !important; }
            .mb-10 { margin-bottom: 10px !important; }
            .pb-20 { padding-bottom: 0 !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            .pt-10 { padding-top: 10px !important; }
            /* Evitar que uma pessoa seja cortada ao meio, a menos que seja muito longa */
            .client-card { break-inside: avoid !important; page-break-inside: avoid !important; }
            .space-y-10 > :not([class*="no-print"]) ~ :not([class*="no-print"]) { margin-top: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen pb-20">
    
    <?php include 'navbar.php'; ?>

    <div class="max-w-[1000px] mx-auto px-4 mt-24">
        
        <!-- DASHBOARD RESUMO (VISÍVEL APENAS NA TELA) -->
        <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-10 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-10 no-print mb-10">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 class="text-3xl font-black flex items-center gap-3">
                        <i data-lucide="file-text" class="w-8 h-8 text-blue-600"></i>
                        Painel Financeiro
                    </h1>
                    <p class="text-slate-400 font-bold text-sm uppercase tracking-widest mt-1"><?= $mes_por_extenso ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <form method="GET" class="flex items-center bg-slate-100 dark:bg-slate-800 p-1.5 rounded-2xl gap-2">
                        <input type="month" name="mes" value="<?= $mes_filtro ?>" onchange="this.form.submit()" 
                            class="bg-white dark:bg-slate-900 border-none rounded-xl px-4 py-2 text-sm font-bold outline-none cursor-pointer">
                    </form>
                    <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-black flex items-center gap-2 shadow-xl shadow-blue-600/20 hover:scale-105 transition-all">
                        <i data-lucide="printer" class="w-4 h-4"></i> Imprimir Tudo
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-slate-50 dark:bg-slate-800/50 p-8 rounded-[2rem] border border-slate-100 dark:border-slate-800">
                    <div class="text-xs font-black uppercase text-slate-400 mb-2 tracking-widest">Total de Contas Pegas</div>
                    <div class="text-5xl font-black text-slate-900 dark:text-white"><?= $total_geral_mes ?></div>
                </div>
                <div class="bg-slate-50 dark:bg-slate-800/50 p-8 rounded-[2rem] border border-slate-100 dark:border-slate-800">
                    <div class="text-xs font-black uppercase text-slate-400 mb-2 tracking-widest">Valor Total do Mês</div>
                    <div class="text-5xl font-black text-emerald-600">R$ <?= number_format($total_geral_mes * $preco_unidade, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <!-- LOOP DE IMPRESSÃO - UMA FOLHA POR PESSOA ATIVA -->
        <?php foreach ($ativos as $d): 
            $stmtContas = $pdo->prepare("SELECT nome, sobrenome, email, data_vinculo FROM contas WHERE destinada_a = ? AND DATE_FORMAT(data_vinculo, '%Y-%m') = ? ORDER BY data_vinculo ASC");
            $stmtContas->execute([$d['id'], $mes_filtro]);
            $contas_pessoa = $stmtContas->fetchAll();
        ?>
        <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-10 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-10 print-break mb-10 client-card">
            <div class="flex justify-between items-start border-b pb-8 dark:border-slate-800">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white font-black text-lg">
                            <?= strtoupper(substr($d['nome'], 0, 1)) ?>
                        </div>
                        <h2 class="text-2xl font-black"><?= htmlspecialchars($d['nome']) ?></h2>
                    </div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Extrato de Contas - <?= $mes_por_extenso ?></p>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-black uppercase text-slate-400 tracking-[0.2em] mb-1">Total a Pagar</div>
                    <div class="text-3xl font-black text-blue-600">R$ <?= number_format($d['total'] * $preco_unidade, 2, ',', '.') ?></div>
                    <div class="text-[10px] font-bold text-slate-400 italic"><?= $d['total'] ?> contas x R$ 20,00</div>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Detalhamento das Contas</h3>
                <div class="overflow-hidden rounded-2xl border border-slate-100 dark:border-slate-800">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 font-black uppercase text-slate-400 tracking-widest">
                                <th class="p-4">Data Vínculo</th>
                                <th class="p-4">Nome Completo</th>
                                <th class="p-4">E-mail de Acesso</th>
                                <th class="p-4 text-right">Custo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php foreach ($contas_pessoa as $c): ?>
                                <tr>
                                    <td class="p-4 font-mono text-slate-400"><?= date('d/m/Y H:i', strtotime($c['data_vinculo'])) ?></td>
                                    <td class="p-4 font-bold"><?= htmlspecialchars($c['nome'] . ' ' . $c['sobrenome']) ?></td>
                                    <td class="p-4 font-bold text-blue-500"><?= htmlspecialchars($c['email']) ?></td>
                                    <td class="p-4 text-right font-black">R$ 20,00</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 font-black">
                                <td colspan="3" class="p-4 text-right uppercase tracking-widest">Subtotal do Cliente:</td>
                                <td class="p-4 text-right text-blue-600 text-lg">R$ <?= number_format($d['total'] * $preco_unidade, 2, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="pt-8 flex justify-between items-center text-[10px] font-bold uppercase tracking-widest text-slate-400">
                <div>Facebook Account Manager v4.3</div>
                <div>Data: <?= date('d/m/Y - H:i') ?></div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- SEÇÃO DE OUTROS (RESUMO FINAL) -->
        <?php if (!empty($outros)): ?>
        <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-10 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-6 client-card">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400 border-b pb-4 dark:border-slate-800">Clientes sem movimentação em <?= $mes_por_extenso ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($outros as $o): ?>
                    <div class="bg-slate-50 dark:bg-slate-800/30 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 flex items-center gap-3">
                        <div class="w-1.5 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full"></div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase"><?= htmlspecialchars($o['nome']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-24 right-8 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl shadow-2xl font-bold flex items-center gap-3 transform translate-y-32 opacity-0 transition-all z-50">
        <div class="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center text-white"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg"></span>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>