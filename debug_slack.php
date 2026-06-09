<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "conexao.php";

echo "<h2>Debug Sincronizacao Slack</h2>";

try {
    $stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao FROM configuracoes LIMIT 1");
    $config = $stmtConf->fetch();
    echo "<p><b>Token:</b> " . htmlspecialchars($config['slack_token']) . "</p>";
    echo "<p><b>Canal/ID Notificacao:</b> " . htmlspecialchars($config['slack_canal_notificacao']) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Erro ao ler configuracoes: " . $e->getMessage() . "</p>";
    exit;
}

try {
    echo "<p>Iniciando sincronizarSlackTracker...</p>";
    
    $token = $config['slack_token'];
    $canal = $config['slack_canal_notificacao'];
    
    if (empty($token)) {
        echo "<p style='color:red;'>Erro: slack_token esta vazio.</p>";
    } else {
        $ch = curl_init("https://slack.com/api/auth.test");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token,
            "Content-Type: application/json; charset=utf-8"
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        echo "<pre><b>auth.test response:</b>\n" . print_r($res, true) . "</pre>";
        
        if (isset($res['ok']) && $res['ok']) {
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
                    "text" => "Teste de conexao do sistema em " . date('d/m/Y H:i:s')
                ]));
                $resMsg = json_decode(curl_exec($chMsg), true);
                curl_close($chMsg);
                echo "<pre><b>chat.postMessage response:</b>\n" . print_r($resMsg, true) . "</pre>";
            } else {
                echo "<p style='color:orange;'>Aviso: slack_canal_notificacao esta vazio.</p>";
            }
        }
    }
    
    sincronizarSlackTracker($pdo);
    echo "<p style='color:green;'>Sincronizacao principal finalizada!</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Erro na sincronizacao: " . $e->getMessage() . "</p>";
}
?>
