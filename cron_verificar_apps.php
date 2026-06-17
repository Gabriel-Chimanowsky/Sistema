<?php
// cron_verificar_apps.php
require_once 'conexao.php';

// Impedir timeout
set_time_limit(300);

// Verificar se o script está sendo forçado a rodar todos os registros (sempre forçado agora)
$force = true;
$stmt = $pdo->query("SELECT * FROM apps");

$apps = $stmt->fetchAll();
$contador = 0;

foreach ($apps as $app) {
    $old_status = $app['status'];
    $old_conexao = $app['status_conexao'];
    
    // Executa a validação avançada e verifica permissões + Live mode
    $resultado = verificarAppStatusMeta(
        $app['app_id'], 
        $app['app_secret'], 
        $app['user_access_token'], 
        $app['permissions']
    );
    
    $status_conexao = $resultado['status_conexao'];
    $status = $resultado['status'];
    $permissions_status = json_encode($resultado['permissions_status']);
    $obs = $app['observacao'];
    
    if ($resultado['observacao_adicional']) {
        $obs = trim(($app['observacao'] ? $app['observacao'] . "\n" : "") . $resultado['observacao_adicional']);
    }

    // Atualiza o registro no banco
    $stmtUp = $pdo->prepare("UPDATE apps SET status_conexao = ?, status = ?, permissions_status = ?, observacao = ?, data_verificacao = NOW() WHERE id = ?");
    $stmtUp->execute([$status_conexao, $status, $permissions_status, $obs, $app['id']]);
    $contador++;

    // Enviar notificações ao Slack se houver mudança de conexão (Caiu / Voltou)
    if ($old_conexao === 'online' && $status_conexao === 'caiu') {
        enviarNotificacaoSlack($pdo, "🚨 *Alerta de Queda:* O aplicativo *{$app['nome']}* (ID: `{$app['app_id']}`) caiu e está *OFFLINE*!");
    } elseif ($old_conexao === 'caiu' && $status_conexao === 'online') {
        enviarNotificacaoSlack($pdo, "🟢 *Alerta de Restabelecimento:* O aplicativo *{$app['nome']}* (ID: `{$app['app_id']}`) voltou a ficar *ONLINE*!");
    }

    // Enviar notificação ao Slack se o app passou para modo Aprovado (Live)
    if ($old_status === 'analise' && $status === 'aprovado') {
        enviarNotificacaoSlack($pdo, "🎉 *Alerta de Aprovação:* O aplicativo *{$app['nome']}* (ID: `{$app['app_id']}`) teve suas permissões aprovadas e foi ativado para o modo *LIVE / ONLINE*!");
    }

    // Delay de 200ms entre as requisições para evitar hitting API Rate Limits da Meta
    usleep(200000);
}

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}

echo json_encode([
    'sucesso' => true,
    'verificados' => $contador,
    'force' => $force,
    'mensagem' => "Cron de verificação automatizada executado com sucesso. {$contador} apps verificados."
]);
