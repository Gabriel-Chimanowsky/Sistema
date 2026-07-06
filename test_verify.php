<?php
require_once 'conexao.php';

$stmt = $pdo->query("SELECT cloudflare_token, cloudflare_zone_id FROM configuracoes LIMIT 1");
$config = $stmt->fetch();

$token = $config['cloudflare_token'] ?? '';
$zoneId = $config['cloudflare_zone_id'] ?? '';

echo "<h3>Diagnóstico da API da Cloudflare</h3>";
echo "<p><b>Zone ID:</b> " . htmlspecialchars($zoneId) . "</p>";

$tokenMascarado = !empty($token) ? substr($token, 0, 5) . "..." . substr($token, -5) : "VAZIO";
echo "<p><b>Token:</b> {$tokenMascarado}</p>";

// 1. Verificar Token
$ch = curl_init("https://api.cloudflare.com/client/v4/user/tokens/verify");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $token,
    "Content-Type: application/json"
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "<h4>1. /user/tokens/verify</h4>";
echo "<pre>" . print_r($res, true) . "</pre>";

// 2. Verificar Acesso à Zone
$ch = curl_init("https://api.cloudflare.com/client/v4/zones/" . $zoneId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $token,
    "Content-Type: application/json"
]);
$resZone = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "<h4>2. /zones/" . htmlspecialchars($zoneId) . "</h4>";
echo "<pre>" . print_r($resZone, true) . "</pre>";

// 3. Verificar Acesso às Regras de Email
$ch = curl_init("https://api.cloudflare.com/client/v4/zones/" . $zoneId . "/email/routing/rules");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $token,
    "Content-Type: application/json"
]);
$resRules = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "<h4>3. /zones/" . htmlspecialchars($zoneId) . "/email/routing/rules</h4>";
echo "<pre>" . print_r($resRules, true) . "</pre>";
?>
