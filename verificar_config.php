<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "conexao.php";

echo "<h2>Diagnostico de Producao do Slack</h2>";

// 1. Mostrar configurações cadastradas
try {
    $stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao, email_dominio FROM configuracoes LIMIT 1");
    $config = $stmtConf->fetch();
    
    if ($config) {
        $token = $config['slack_token'] ?? '';
        $canal = $config['slack_canal_notificacao'] ?? '';
        $dominio = $config['email_dominio'] ?? '';
        
        $tokenMascarado = !empty($token) ? substr($token, 0, 8) . "..." . substr($token, -6) : "VAZIO";
        
        echo "<p><b>Token Cadastrado:</b> {$tokenMascarado}</p>";
        echo "<p><b>Canal/ID Cadastrado:</b> " . (!empty($canal) ? htmlspecialchars($canal) : "VAZIO") . "</p>";
        echo "<p><b>Dominio Cadastrado:</b> " . htmlspecialchars($dominio) . "</p>";
    } else {
        echo "<p style='color:red;'>Nenhuma configuracao encontrada na tabela.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Erro ao ler configuracoes: " . $e->getMessage() . "</p>";
}

// 2. Mostrar dados de contas pendentes de sincronização
try {
    // Contas criadas e não sincronizadas no Slack
    $unsyncedPerfis = $pdo->query("SELECT COUNT(*) FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0")->fetchColumn();
    // BMs criadas e não sincronizadas no Slack
    $unsyncedBms = $pdo->query("SELECT COUNT(*) FROM contas WHERE bm_criada = 1 AND slack_bm_sync = 0")->fetchColumn();
    
    echo "<p><b>Perfis criados aguardando envio (Lote de 50):</b> {$unsyncedPerfis} / 50</p>";
    echo "<p><b>BMs criadas aguardando envio (Lote de 50):</b> {$unsyncedBms} / 50</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Erro ao contar perfis/BMs: " . $e->getMessage() . "</p>";
}

// 3. Listas do mês criadas
try {
    $listas = $pdo->query("SELECT * FROM slack_listas ORDER BY mes DESC")->fetchAll();
    echo "<h3>Histórico de Listas Criadas (tabela slack_listas)</h3>";
    if (count($listas) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Mês</th><th>List ID</th><th>Primary Col ID</th><th>Criado Em</th></tr>";
        foreach ($listas as $l) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($l['mes']) . "</td>";
            echo "<td>" . htmlspecialchars($l['list_id']) . "</td>";
            echo "<td>" . htmlspecialchars($l['primary_col_id']) . "</td>";
            echo "<td>" . htmlspecialchars($l['criado_em']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhuma lista registrada no banco.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Erro ao listar tabelas: " . $e->getMessage() . "</p>";
}
?>
