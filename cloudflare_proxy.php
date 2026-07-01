<?php
/**
 * CF Email Bot — Secure Proxy PHP
 * Intermediário para chamadas à API da Cloudflare (evita CORS do navegador)
 * Protegido com validação de sessão do sistema.
 */

require_once 'conexao.php';
require_once 'auth.php';
checkAuth();

header('Content-Type: application/json');

// Read JSON body
$input  = json_decode(file_get_contents('php://input'), true);
$method = strtoupper($input['method'] ?? 'GET');
$url    = $input['url']   ?? '';
$token  = $input['token'] ?? '';
$body   = $input['body']  ?? null;

// Basic validation
if (empty($url) || empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'url e token são obrigatórios']);
    exit;
}

// Only allow Cloudflare API URLs
if (!str_starts_with($url, 'https://api.cloudflare.com/')) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas URLs da API Cloudflare são permitidas']);
    exit;
}

// Build cURL request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
]);

if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
}

$response   = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL error: ' . $curlError]);
    exit;
}

http_response_code($httpCode);
echo $response;
