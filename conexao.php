<?php
/**
 * Ponto de Conexão Principal e Inicialização do Sistema
 * Mantido para 100% de retrocompatibilidade com todas as páginas e crons existentes.
 */

// 1. Conexão com o banco de dados e variáveis de ambiente
require_once __DIR__ . '/includes/db.php';

// 2. Migrações automáticas de schema do banco de dados
require_once __DIR__ . '/includes/migrations.php';
executarMigracoes($pdo);

// 3. Helpers e Serviços
require_once __DIR__ . '/includes/helpers/slack_helper.php';
require_once __DIR__ . '/includes/helpers/meta_helper.php';
require_once __DIR__ . '/includes/helpers/sms_helper.php';

if (file_exists(__DIR__ . '/cloudflare_helper.php')) {
    require_once __DIR__ . '/cloudflare_helper.php';
}