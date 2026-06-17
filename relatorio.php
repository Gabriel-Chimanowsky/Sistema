<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

// Pegar mês selecionado ou atual
$mes_filtro = $_GET['mes'] ?? date('Y-m');

$stmtConf = $pdo->query("SELECT * FROM configuracoes LIMIT 1");
$config = $stmtConf->fetch();
$preco_unidade = $config['preco_perfil'] ?? 20.00;
$preco_bm = $config['preco_bm'] ?? 30.00;
$preco_pagina = $config['preco_pagina'] ?? 10.00;

$meses_nomes = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

$ano = substr($mes_filtro, 0, 4);
$mes_num = substr($mes_filtro, 5, 2);
$mes_por_extenso = $meses_nomes[$mes_num] . " " . $ano;

// Criar tabela de descontos se não existir
try {
    $pdo->query("SELECT id FROM descontos LIMIT 1");
} catch (Exception $e) {
    $pdo->query("CREATE TABLE IF NOT EXISTS `descontos` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pessoa_id` int(11) NOT NULL,
        `mes` varchar(7) NOT NULL COMMENT 'Formato YYYY-MM',
        `motivo` varchar(255) NOT NULL,
        `valor` decimal(10,2) NOT NULL,
        `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `pessoa_id` (`pessoa_id`),
        CONSTRAINT `descontos_ibfk_1` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoas` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Criar coluna excluir_nota se não existir
try {
    $pdo->query("SELECT excluir_nota FROM contas LIMIT 1");
} catch (Exception $e) {
    $pdo->query("ALTER TABLE contas ADD COLUMN excluir_nota TINYINT(1) NOT NULL DEFAULT 0");
}

// 1. Clientes COM movimentação no mês (faturamento considera apenas contas válidas/ativas)
$sql_ativos = "SELECT p.id, p.nome, COALESCE(SUM(CASE WHEN c.excluir_nota = 0 THEN 1 ELSE 0 END), 0) as total,
                COALESCE(SUM(CASE WHEN c.excluir_nota = 0 THEN {$preco_unidade} + IFNULL(c.bm_criada, 0) * {$preco_bm} + IFNULL(c.pagina_criada, 0) * {$preco_pagina} ELSE 0 END), 0) as valor_contas_total
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
$total_geral_mes = 0;
$valor_bruto_contas = 0;
foreach ($ativos as $at) {
    $total_geral_mes += $at['total'];
    $valor_bruto_contas += $at['valor_contas_total'];
}

// 2. Descontos do período
$stmtTotalDescontos = $pdo->prepare("
    SELECT SUM(d.valor) 
    FROM descontos d
    INNER JOIN pessoas p ON d.pessoa_id = p.id
    WHERE d.mes = ?
    AND LOWER(p.nome) != 'administrador'
");
$stmtTotalDescontos->execute([$mes_filtro]);
$total_descontos_mes = (float)$stmtTotalDescontos->fetchColumn();

// 3. Valor Total Líquido
$valor_total_mes = max(0, $valor_bruto_contas - $total_descontos_mes);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - Facebook Account Manager V4.3</title>
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
                <div class="bg-slate-50 dark:bg-slate-800/50 p-8 rounded-[2rem] border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
                    <div>
                        <div class="text-xs font-black uppercase text-slate-400 mb-2 tracking-widest">Valor Total do Mês</div>
                        <div class="text-5xl font-black text-emerald-600">R$ <?= number_format($valor_total_mes, 2, ',', '.') ?></div>
                    </div>
                    <?php if ($total_descontos_mes > 0): ?>
                        <div class="text-[10px] font-bold text-red-500 uppercase tracking-wider mt-2">
                            Total Descontado: R$ <?= number_format($total_descontos_mes, 2, ',', '.') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- LOOP DE IMPRESSÃO - UMA FOLHA POR PESSOA ATIVA -->
        <?php foreach ($ativos as $d): 
            $stmtContas = $pdo->prepare("SELECT id, nome, sobrenome, email, data_vinculo, excluir_nota, bm_criada, pagina_criada FROM contas WHERE destinada_a = ? AND DATE_FORMAT(data_vinculo, '%Y-%m') = ? ORDER BY data_vinculo ASC");
            $stmtContas->execute([$d['id'], $mes_filtro]);
            $contas_pessoa = $stmtContas->fetchAll();

            // Buscar descontos da pessoa
            $stmtDescontos = $pdo->prepare("SELECT id, motivo, valor FROM descontos WHERE pessoa_id = ? AND mes = ? ORDER BY criado_em ASC");
            $stmtDescontos->execute([$d['id'], $mes_filtro]);
            $descontos_pessoa = $stmtDescontos->fetchAll();

            $total_descontos = 0;
            foreach ($descontos_pessoa as $desc) {
                $total_descontos += $desc['valor'];
            }
            $valor_contas = $d['valor_contas_total'];
            $valor_final_pessoa = max(0, $valor_contas - $total_descontos);
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
                    <div class="text-3xl font-black text-blue-600">R$ <?= number_format($valor_final_pessoa, 2, ',', '.') ?></div>
                    <div class="text-[10px] font-bold text-slate-400 italic">
                        <?= $d['total'] ?> contas

                        <?php if ($total_descontos > 0): ?>
                            (- R$ <?= number_format($total_descontos, 2, ',', '.') ?> desc.)
                        <?php endif; ?>
                    </div>
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
                            <?php foreach ($contas_pessoa as $c): 
                                $deu_ruim = (int)$c['excluir_nota'] === 1;
                            ?>
                                <tr class="<?= $deu_ruim ? 'opacity-40 line-through text-slate-450 dark:text-slate-500 bg-slate-50/30 dark:bg-slate-950/20' : '' ?>">
                                    <td class="p-4 font-mono text-slate-400"><?= date('d/m/Y H:i', strtotime($c['data_vinculo'])) ?></td>
                                    <td class="p-4 font-bold"><?= htmlspecialchars($c['nome'] . ' ' . $c['sobrenome']) ?></td>
                                    <td class="p-4 font-bold text-blue-500 <?= $deu_ruim ? 'text-slate-450 dark:text-slate-500' : '' ?>"><?= htmlspecialchars($c['email']) ?></td>
                                    <td class="p-4 text-right font-black">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php
                                                $valor_item = $preco_unidade;
                                                if (isset($c['bm_criada']) && $c['bm_criada'] == 1) $valor_item += $preco_bm;
                                                if (isset($c['pagina_criada']) && $c['pagina_criada'] == 1) $valor_item += $preco_pagina;
                                            ?>
                                            <span>R$ <?= $deu_ruim ? '0,00' : number_format($valor_item, 2, ',', '.') ?></span>
                                            <!-- Botão de Dar Baixa / Tirar da Nota (oculto no print) -->
                                            <form action="processa.php?acao=toggle_nota" method="POST" class="inline no-print ml-2">
                                                <input type="hidden" name="acao" value="toggle_nota">
                                                <input type="hidden" name="conta_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="<?= $deu_ruim ? 'Reintroduzir na cobrança' : 'Dar Baixa (Tirar da cobrança)' ?>">
                                                    <?php if ($deu_ruim): ?>
                                                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-blue-500 hover:scale-110 transition-transform"></i>
                                                    <?php else: ?>
                                                        <i data-lucide="ban" class="w-3.5 h-3.5 text-red-500 hover:scale-110 transition-transform"></i>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Descontos na Tabela -->
                            <?php if (!empty($descontos_pessoa)): ?>
                                <?php foreach ($descontos_pessoa as $desc): ?>
                                    <tr class="bg-red-50/20 dark:bg-red-950/10 text-red-600 dark:text-red-400">
                                        <td class="p-4 font-bold uppercase tracking-wider text-[10px]">Desconto</td>
                                        <td class="p-4 font-bold" colspan="2">
                                            <div class="flex items-center justify-between">
                                                <span><?= htmlspecialchars($desc['motivo']) ?></span>
                                                <!-- Botão de Excluir Desconto (oculto no print) -->
                                                <form action="processa.php?acao=del_desconto" method="POST" class="inline no-print ml-2" onsubmit="return confirm('Tem certeza que deseja remover este desconto?')">
                                                    <input type="hidden" name="acao" value="del_desconto">
                                                    <input type="hidden" name="desconto_id" value="<?= $desc['id'] ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 p-1 rounded transition-colors" title="Remover Desconto">
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="p-4 text-right font-black">- R$ <?= number_format($desc['valor'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <?php if ($total_descontos > 0): ?>
                                <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-slate-400 font-bold text-[11px]">
                                    <td colspan="3" class="p-3 text-right uppercase tracking-widest">Subtotal Contas:</td>
                                    <td class="p-3 text-right">R$ <?= number_format($valor_contas, 2, ',', '.') ?></td>
                                </tr>
                                <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-red-500 font-bold text-[11px]">
                                    <td colspan="3" class="p-3 text-right uppercase tracking-widest">Total Descontos:</td>
                                    <td class="p-3 text-right">- R$ <?= number_format($total_descontos, 2, ',', '.') ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 font-black">
                                <td colspan="3" class="p-4 text-right uppercase tracking-widest">Total a Pagar:</td>
                                <td class="p-4 text-right text-blue-600 text-lg">R$ <?= number_format($valor_final_pessoa, 2, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Formulário para Lançar Desconto (oculto no print) -->
            <form action="processa.php?acao=add_desconto" method="POST" class="no-print mt-6 p-6 bg-slate-50 dark:bg-slate-800/30 rounded-3xl border border-slate-200 dark:border-slate-850/50 flex flex-wrap items-end gap-4">
                <input type="hidden" name="acao" value="add_desconto">
                <input type="hidden" name="pessoa_id" value="<?= $d['id'] ?>">
                <input type="hidden" name="mes" value="<?= $mes_filtro ?>">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1.5 tracking-wider">Motivo do Desconto</label>
                    <input type="text" name="motivo" required placeholder="Ex: Conta inativa, reposição de ID, etc." class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-semibold outline-none focus:border-blue-500 dark:focus:border-blue-500 transition-all">
                </div>
                <div class="w-32">
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1.5 tracking-wider">Valor (R$)</label>
                    <input type="number" name="valor" step="0.01" min="0.01" required placeholder="0,00" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-semibold outline-none focus:border-blue-500 dark:focus:border-blue-500 transition-all">
                </div>
                <div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-5 py-3 rounded-xl flex items-center gap-2 transition-all active:scale-95 shadow-md shadow-blue-600/10">
                        <i data-lucide="plus" class="w-4 h-4"></i> Lançar Desconto
                    </button>
                </div>
            </form>

            <div class="pt-8 flex justify-between items-center text-[10px] font-bold uppercase tracking-widest text-slate-400 border-t border-slate-100 dark:border-slate-800">
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