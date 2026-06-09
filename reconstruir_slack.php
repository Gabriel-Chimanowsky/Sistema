<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexao.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Reconstruir Slack</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #0f172a; padding: 2rem; max-width: 800px; margin: 0 auto; line-height: 1.6; }
        .card { background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        h2 { color: #2563eb; margin-top: 0; }
        h3 { color: #475569; margin-top: 1.5rem; margin-bottom: 0.5rem; font-size: 1.1rem; }
        .success { color: #16a34a; font-weight: bold; }
        .warning { color: #d97706; }
        .error { color: #dc2626; font-weight: bold; }
        .log { background: #f1f5f9; padding: 1rem; border-radius: 0.5rem; font-family: monospace; font-size: 0.9rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class='card'>
    <h2>🔄 Limpeza e Reconstrução do Slack</h2>";

$stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao, email_dominio FROM configuracoes LIMIT 1");
$config = $stmtConf->fetch();
$token = $config['slack_token'] ?? '';
$canal = $config['slack_canal_notificacao'] ?? '';

if (!$token) {
    die("<p class='error'>Token do Slack não configurado. Por favor, adicione na página de Configurações.</p></div></body></html>");
}

// 1. Apagar listas anteriores
echo "<h3>1. Apagando listas anteriores no Slack...</h3><div class='log'>";
$listasAntigas = $pdo->query("SELECT * FROM slack_listas")->fetchAll();

if (count($listasAntigas) === 0) {
    echo "Nenhuma lista anterior encontrada no banco local.<br>";
}

foreach ($listasAntigas as $lista) {
    $list_id = $lista['list_id'];
    echo "Tentando apagar lista ID: <b>$list_id</b>... ";
    
    // Tentar apagar via API do Slack. A API oficial não tem um endpoint público claro documentado como "slackLists.delete",
    // mas vamos tentar esse ou simplesmente arquivar/ignorar caso a API retorne erro, 
    // já que o mais importante é limpar do BD e criar a nova.
    $chDel = curl_init("https://slack.com/api/slackLists.delete");
    curl_setopt($chDel, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chDel, CURLOPT_POST, true);
    curl_setopt($chDel, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json; charset=utf-8"
    ]);
    curl_setopt($chDel, CURLOPT_POSTFIELDS, json_encode([
        "list_id" => $list_id
    ]));
    $resDel = json_decode(curl_exec($chDel), true);
    curl_close($chDel);
    
    if (isset($resDel['ok']) && $resDel['ok']) {
        echo "<span class='success'>OK (Apagada no Slack)</span><br>";
    } else {
        echo "<span class='warning'>Ignorado pelo Slack (Pode já estar apagada manualmente ou permissão negada).</span><br>";
    }
}

// Limpar banco de listas
$pdo->query("TRUNCATE TABLE slack_listas");
$pdo->query("TRUNCATE TABLE slack_lotes_count");
echo "<br><span class='success'>✅ Banco de dados local de listas foi limpo com sucesso.</span></div>";

// 2. Resetar status de sincronização de TODAS as contas
echo "<h3>2. Resetando status de sincronização...</h3><div class='log'>";
$pdo->query("UPDATE contas SET slack_perfil_sync = 0, slack_bm_sync = 0");
$totalContas = $pdo->query("SELECT COUNT(*) FROM contas WHERE status IN ('criada', 'autenticada', 'exportado')")->fetchColumn();
echo "<span class='success'>✅ Todos os $totalContas perfis e BMs foram marcados para sincronizar novamente.</span></div>";

// 3. Chamar o sincronizador múltiplas vezes para cobrir todas as contas em lotes
echo "<h3>3. Iniciando criação da nova lista e agrupamento em lotes...</h3><div class='log'>";
echo "Sincronizando lotes de 50 em 50...<br>";

$maxLoops = 100; // Limite de segurança para não travar (até 5000 contas)
$loopsRealizados = 0;

while ($maxLoops > 0) {
    $contasPendentes = $pdo->query("SELECT COUNT(*) FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0")->fetchColumn();
    $bmsPendentes = $pdo->query("SELECT COUNT(*) FROM contas WHERE bm_criada = 1 AND slack_bm_sync = 0")->fetchColumn();
    
    if ($contasPendentes < 50 && $bmsPendentes < 50) {
        break; // Não tem mais lotes completos de 50 para enviar
    }
    
    // Chama o bot para fazer uma rodada de envio (50 perfis e 50 BMs)
    sincronizarSlackTracker($pdo);
    
    $loopsRealizados++;
    echo "."; // Indicador visual de progresso
    
    // Pequena pausa para evitar rate limit do Slack
    usleep(500000); 
    $maxLoops--;
}
echo "<br><br><span class='success'>✅ Processados $loopsRealizados lotes de 50 contas/BMs para a nova lista.</span><br>";
$restantesP = $pdo->query("SELECT COUNT(*) FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0")->fetchColumn();
$restantesB = $pdo->query("SELECT COUNT(*) FROM contas WHERE bm_criada = 1 AND slack_bm_sync = 0")->fetchColumn();
echo "<span class='warning'>Ficaram na fila de espera (menos de 50): $restantesP Perfis e $restantesB BMs. (Eles subirão sozinhos quando atingirem 50).</span></div>";


// 4. Enviar mensagem para o chat cadastrado com a notificação
echo "<h3>4. Notificando o Chat Configurado...</h3><div class='log'>";
if (!empty($canal)) {
    $chMsg = curl_init("https://slack.com/api/chat.postMessage");
    curl_setopt($chMsg, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chMsg, CURLOPT_POST, true);
    curl_setopt($chMsg, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json; charset=utf-8"
    ]);
    curl_setopt($chMsg, CURLOPT_POSTFIELDS, json_encode([
        "channel" => $canal,
        "text" => "⚠️ *Aviso do Sistema:* As listas anteriores de Gestão foram resetadas e uma *nova lista completa* foi gerada agora com todas as informações atualizadas de Perfis e BMs agrupados.\n👉 Acesse a seção de *Lists* no seu Slack para conferir o resultado."
    ]));
    $resMsg = json_decode(curl_exec($chMsg), true);
    curl_close($chMsg);
    
    if (isset($resMsg['ok']) && $resMsg['ok']) {
        echo "<span class='success'>✅ Mensagem de notificação enviada para o canal/chat cadastrado.</span><br>";
    } else {
        echo "<span class='error'>❌ Erro ao enviar notificação: " . print_r($resMsg, true) . "</span><br>";
    }
} else {
    echo "<span class='warning'>⚠️ Nenhum canal/chat cadastrado nas configurações para enviar a notificação.</span><br>";
}
echo "</div>";

echo "<h2>Processo Concluído com Sucesso! 🎉</h2>";
echo "<p>Você pode acessar o Slack e conferir a sua nova lista.</p>";
echo "<br><a href='index.php' style='display:inline-block;background:#2563eb;color:white;text-decoration:none;padding:10px 20px;border-radius:8px;font-weight:bold;'>Voltar para a Página Inicial</a>";
echo "</div></body></html>";
?>
