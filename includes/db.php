<?php
/**
 * Conexão com Banco de Dados e Inicialização de Variáveis de Ambiente
 */

// Carrega variáveis de ambiente se o arquivo .env existir
if (file_exists(dirname(__DIR__) . '/.env')) {
    $lines = file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

// Configurações do Banco de Dados
$host   = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'licencas';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';

// Configurações de API e E-mail
$apiHerosms = getenv('API_HEROSMS') ?: '1db85Af88f65e2840754836202520ee6';
$emailHost  = getenv('EMAIL_HOST') ?: 'mail.seudominio.com';
$emailUser  = getenv('EMAIL_USER') ?: 'admin@seudominio.com';
$emailPass  = getenv('EMAIL_PASS') ?: 'sua_senha_forte_do_email';

// Token para segurança das APIs externas
if (!defined('API_TOKEN')) {
    define('API_TOKEN', getenv('API_TOKEN') ?: 'dollfinn_secret_token_123');
}

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    
    $opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $opcoes);

} catch (PDOException $e) {
    $isWeb = isset($_SERVER['REQUEST_URI']);
    if ($isWeb && strpos($_SERVER['REQUEST_URI'], '.php') !== false && strpos($_SERVER['REQUEST_URI'], 'api') === false) {
        die("Erro crítico: Não foi possível conectar ao banco de dados.");
    } else {
        if (!headers_sent() && php_sapi_name() !== 'cli') {
            header('Content-Type: application/json');
        }
        die(json_encode([
            "sucesso" => false, 
            "mensagem" => "Erro crítico: Não foi possível conectar ao banco de dados. " . $e->getMessage()
        ]));
    }
}
