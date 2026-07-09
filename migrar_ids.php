<?php
require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

$resultado = null;
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirmar'] ?? '') === 'sim') {
    try {
        // 1. Obter todos os domínios ordenados por ID mínimo atual
        $stmt = $pdo->query("
            SELECT 
                LOWER(SUBSTRING_INDEX(email, '@', -1)) as dominio,
                MIN(id) as min_id
            FROM contas 
            GROUP BY dominio
            ORDER BY min_id ASC
        ");
        $dominios = array_column($stmt->fetchAll(), 'dominio');
        
        $domainIndices = [];
        foreach ($dominios as $k => $d) {
            $domainIndices[$d] = $k;
        }

        // Buscar todas as contas
        $stmtContas = $pdo->query("SELECT id, email FROM contas");
        $contas = $stmtContas->fetchAll();

        // Verificar tabela de logs para atualizações correlacionadas
        $hasLogTable = false;
        try {
            $pdo->query("SELECT conta_id FROM log_criacao_contas LIMIT 1");
            $hasLogTable = true;
        } catch (Exception $e) {}

        // Desabilitar chaves estrangeiras
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $updates = [];
        foreach ($contas as $c) {
            $oldId = $c['id'];
            $email = $c['email'];
            
            $parts = explode('@', $email);
            $localPart = $parts[0] ?? '';
            $domain = isset($parts[1]) ? strtolower(trim($parts[1])) : '';
            
            // Extrair número do prefixo contatoX
            $number = (int)preg_replace('/\D/', '', $localPart);
            
            if ($number === 0) {
                $logs[] = "⚠️ Ignorada conta '{$email}': não foi possível determinar o número no prefixo.";
                continue;
            }
            
            $k = $domainIndices[$domain] ?? 0;
            $newId = (200 * $k) + $number;
            
            if ($oldId !== $newId) {
                $updates[] = [
                    'old_id' => $oldId,
                    'new_id' => $newId,
                    'email' => $email
                ];
            }
        }

        // Fase 1: Mover todas as contas para IDs temporários altos para evitar colisões
        foreach ($updates as $u) {
            $tempId = 100000 + $u['old_id'];
            $pdo->prepare("UPDATE contas SET id = ? WHERE id = ?")->execute([$tempId, $u['old_id']]);
            if ($hasLogTable) {
                $pdo->prepare("UPDATE log_criacao_contas SET conta_id = ? WHERE conta_id = ?")->execute([$tempId, $u['old_id']]);
            }
        }

        // Fase 2: Mover para os novos IDs definitivos
        $sucessos = 0;
        foreach ($updates as $u) {
            $tempId = 100000 + $u['old_id'];
            try {
                $pdo->prepare("UPDATE contas SET id = ? WHERE id = ?")->execute([$u['new_id'], $tempId]);
                if ($hasLogTable) {
                    $pdo->prepare("UPDATE log_criacao_contas SET conta_id = ? WHERE conta_id = ?")->execute([$u['new_id'], $tempId]);
                }
                $logs[] = "✅ '{$u['email']}': ID alterado de <strong>{$u['old_id']}</strong> para <strong>{$u['new_id']}</strong>";
                $sucessos++;
            } catch (Exception $e) {
                // Reverter para o original se der erro inesperado
                $pdo->prepare("UPDATE contas SET id = ? WHERE id = ?")->execute([$u['old_id'], $tempId]);
                if ($hasLogTable) {
                    $pdo->prepare("UPDATE log_criacao_contas SET conta_id = ? WHERE conta_id = ?")->execute([$u['old_id'], $tempId]);
                }
                $logs[] = "❌ ERRO em '{$u['email']}' para ID {$u['new_id']}: " . $e->getMessage();
            }
        }

        // Resetar AUTO_INCREMENT para o maior ID atual
        $stmtMax = $pdo->query("SELECT MAX(id) FROM contas");
        $maxId = (int)$stmtMax->fetchColumn();
        if ($maxId > 0) {
            $pdo->exec("ALTER TABLE contas AUTO_INCREMENT = " . ($maxId + 1));
        }

        // Reabilitar chaves estrangeiras
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        $resultado = [
            'sucesso' => true,
            'mensagem' => "Reorganização concluída! {$sucessos} de " . count($updates) . " contas atualizadas com sucesso."
        ];

    } catch (Exception $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $resultado = [
            'sucesso' => false,
            'mensagem' => "Falha crítica na migração: " . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reorganizar IDs das Contas</title>
    <script src="tailwind.js?v=1"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Fira+Code&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        pre { font-family: 'Fira Code', monospace; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-8 font-sans">
    <div class="max-w-2xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-xl font-extrabold text-white">Reorganizar IDs por Domínio</h1>
                <p class="text-xs text-slate-400 mt-1">Garante que o ID no banco e o número do contato correspondam.</p>
            </div>
            <a href="index.php" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded-xl text-xs font-semibold transition">
                ← Voltar ao Início
            </a>
        </div>

        <?php if ($resultado === null): ?>
            <!-- Confirmação Inicial -->
            <div class="bg-slate-800/40 border border-slate-800 p-6 rounded-2xl space-y-4">
                <div class="bg-amber-500/10 border border-amber-500/20 text-amber-200 p-4 rounded-xl text-xs leading-relaxed space-y-2">
                    <p class="font-bold text-sm">⚠️ Atenção: Leia com cuidado antes de prosseguir!</p>
                    <p>Este utilitário fará a reorganização dos IDs da tabela <code>contas</code> com as seguintes regras:</p>
                    <ul class="list-disc pl-4 space-y-1">
                        <li>Domínio 1 (<strong>dollfinn.com</strong>): IDs de 1 a 200 (corresponde a contatoX)</li>
                        <li>Domínio 2 (<strong>whitebeavers.com</strong>): IDs de 201 a 400 (corresponde a contatoX + 200)</li>
                        <li>Domínio 3 (<strong>novos domínios</strong>): IDs de 401 a 600 (corresponde a contatoX + 400)</li>
                    </ul>
                    <p class="mt-2">Todos os registros associados nas tabelas de logs serão mantidos com os IDs corretos. A operação é segura e possui tratamento contra colisões de chaves.</p>
                </div>

                <form method="POST" class="flex justify-end pt-2">
                    <input type="hidden" name="confirmar" value="sim">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-3 rounded-xl text-xs tracking-wide transition shadow-lg shadow-emerald-900/20">
                        🚀 Iniciar Reorganização de IDs
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Resultados -->
            <div class="space-y-4">
                <div class="p-4 rounded-xl text-xs font-bold <?php echo $resultado['sucesso'] ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-300' : 'bg-red-500/10 border border-red-500/20 text-red-300'; ?>">
                    <?php echo htmlspecialchars($resultado['mensagem']); ?>
                </div>

                <div class="space-y-2">
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">📋 Histórico da Execução</h2>
                    <div class="bg-slate-950 border border-slate-850 p-4 rounded-2xl max-h-[400px] overflow-y-auto text-[11px] space-y-1.5 font-mono text-slate-300">
                        <?php foreach ($logs as $log): ?>
                            <div><?php echo $log; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex justify-between pt-2">
                    <a href="verificar_log.php" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-xl text-xs font-semibold transition">
                        📊 Ver Status dos Domínios
                    </a>
                    <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition">
                        Concluído
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
