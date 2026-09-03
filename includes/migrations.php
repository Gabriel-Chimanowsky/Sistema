<?php
/**
 * Gerenciador de Migrações Automáticas do Banco de Dados
 * Executa as alterações de schema de forma controlada sem onerar cada requisição HTTP.
 */

if (!function_exists('executarMigracoes')) {
    function executarMigracoes($pdo, $forcar = false) {
        static $jaExecutou = false;
        if ($jaExecutou && !$forcar) {
            return;
        }

        try {
            // 1. Tabela contas
            $stmt = $pdo->query("SHOW COLUMNS FROM contas");
            $colunasContas = array_column($stmt->fetchAll(), 'Field');
            
            $colunasParaAdicionarContas = [
                'data_autenticacao'  => 'ALTER TABLE contas ADD COLUMN data_autenticacao DATETIME NULL',
                'cookies'            => 'ALTER TABLE contas ADD COLUMN cookies LONGTEXT NULL',
                'nota_conta'         => 'ALTER TABLE contas ADD COLUMN nota_conta TEXT DEFAULT NULL',
                'data_exportado'     => 'ALTER TABLE contas ADD COLUMN data_exportado DATETIME NULL',
                'bm_criada'          => 'ALTER TABLE contas ADD COLUMN bm_criada TINYINT(1) NOT NULL DEFAULT 0',
                'data_bm_criada'     => 'ALTER TABLE contas ADD COLUMN data_bm_criada DATETIME NULL',
                'pagina_criada'      => 'ALTER TABLE contas ADD COLUMN pagina_criada TINYINT(1) NOT NULL DEFAULT 0',
                'data_pagina_criada' => 'ALTER TABLE contas ADD COLUMN data_pagina_criada DATETIME NULL',
                'dev_criada'         => 'ALTER TABLE contas ADD COLUMN dev_criada TINYINT(1) NOT NULL DEFAULT 0',
                'data_dev_criada'    => 'ALTER TABLE contas ADD COLUMN data_dev_criada DATETIME NULL',
                'slack_perfil_sync'  => 'ALTER TABLE contas ADD COLUMN slack_perfil_sync TINYINT(1) NOT NULL DEFAULT 0',
                'slack_bm_sync'      => 'ALTER TABLE contas ADD COLUMN slack_bm_sync TINYINT(1) NOT NULL DEFAULT 0',
                'valor_perfil'       => 'ALTER TABLE contas ADD COLUMN valor_perfil DECIMAL(10,2) NULL DEFAULT NULL',
                'valor_bm'           => 'ALTER TABLE contas ADD COLUMN valor_bm DECIMAL(10,2) NULL DEFAULT NULL',
                'valor_pagina'       => 'ALTER TABLE contas ADD COLUMN valor_pagina DECIMAL(10,2) NULL DEFAULT NULL',
            ];

            foreach ($colunasParaAdicionarContas as $col => $sql) {
                if (!in_array($col, $colunasContas)) {
                    $pdo->query($sql);
                }
            }

            // 2. Tabela configuracoes
            $stmtConf = $pdo->query("SHOW COLUMNS FROM configuracoes");
            $colunasConf = array_column($stmtConf->fetchAll(), 'Field');
            
            $colunasParaAdicionarConf = [
                'slack_token'             => 'ALTER TABLE configuracoes ADD COLUMN slack_token VARCHAR(255) NULL',
                'slack_canal_notificacao' => 'ALTER TABLE configuracoes ADD COLUMN slack_canal_notificacao VARCHAR(100) NULL',
                'preco_perfil'            => 'ALTER TABLE configuracoes ADD COLUMN preco_perfil DECIMAL(10,2) NOT NULL DEFAULT 20.00',
                'preco_bm'                => 'ALTER TABLE configuracoes ADD COLUMN preco_bm DECIMAL(10,2) NOT NULL DEFAULT 30.00',
                'preco_pagina'            => 'ALTER TABLE configuracoes ADD COLUMN preco_pagina DECIMAL(10,2) NOT NULL DEFAULT 10.00',
                'cloudflare_token'        => 'ALTER TABLE configuracoes ADD COLUMN cloudflare_token VARCHAR(255) NULL',
                'cloudflare_zone_id'      => 'ALTER TABLE configuracoes ADD COLUMN cloudflare_zone_id VARCHAR(255) NULL',
                'cloudflare_dest_email'   => 'ALTER TABLE configuracoes ADD COLUMN cloudflare_dest_email VARCHAR(255) NULL',
            ];

            foreach ($colunasParaAdicionarConf as $col => $sql) {
                if (!in_array($col, $colunasConf)) {
                    $pdo->query($sql);
                }
            }

            // 3. Tabela slack_listas
            $pdo->query("CREATE TABLE IF NOT EXISTS `slack_listas` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `mes` varchar(7) NULL UNIQUE COMMENT 'Formato YYYY-MM',
                `nome` varchar(255) NULL,
                `list_id` varchar(50) NOT NULL,
                `primary_col_id` varchar(50) NOT NULL,
                `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmtSlackListasCols = $pdo->query("SHOW COLUMNS FROM slack_listas");
            $colunasSlackListas = array_column($stmtSlackListasCols->fetchAll(), 'Field');
            if (!in_array('nome', $colunasSlackListas)) {
                $pdo->query("ALTER TABLE slack_listas ADD COLUMN nome VARCHAR(255) NULL");
            }

            // 4. Tabela slack_lotes_count
            $pdo->query("CREATE TABLE IF NOT EXISTS `slack_lotes_count` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `list_id` varchar(50) NOT NULL,
                `week` varchar(100) NOT NULL,
                `type` varchar(20) NOT NULL,
                `domain` varchar(50) DEFAULT NULL,
                `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmtLotesCols = $pdo->query("SHOW COLUMNS FROM slack_lotes_count");
            $colunasLotes = array_column($stmtLotesCols->fetchAll(), 'Field');
            if (!in_array('domain', $colunasLotes)) {
                $pdo->query("ALTER TABLE slack_lotes_count ADD COLUMN domain VARCHAR(50) NULL DEFAULT NULL");
            }

            // 5. Tabela apps
            $pdo->query("CREATE TABLE IF NOT EXISTS `apps` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `nome` VARCHAR(255) NOT NULL,
                `app_id` VARCHAR(100) NOT NULL UNIQUE,
                `app_secret` VARCHAR(255) NULL,
                `status` ENUM('analise', 'aprovado', 'rejeitado') NOT NULL DEFAULT 'analise',
                `status_conexao` ENUM('online', 'caiu') NOT NULL DEFAULT 'online',
                `observacao` TEXT NULL,
                `data_verificacao` DATETIME NULL,
                `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $stmtAppsCols = $pdo->query("SHOW COLUMNS FROM apps");
            $colunasApps = array_column($stmtAppsCols->fetchAll(), 'Field');
            if (!in_array('permissions', $colunasApps)) {
                $pdo->query("ALTER TABLE apps ADD COLUMN permissions TEXT NULL");
            }
            if (!in_array('permissions_status', $colunasApps)) {
                $pdo->query("ALTER TABLE apps ADD COLUMN permissions_status TEXT NULL");
            }
            if (!in_array('user_access_token', $colunasApps)) {
                $pdo->query("ALTER TABLE apps ADD COLUMN user_access_token TEXT NULL");
            }

            // 6. Tabela pessoas
            $stmtPessoasCols = $pdo->query("SHOW COLUMNS FROM pessoas");
            $colunasPessoas = array_column($stmtPessoasCols->fetchAll(), 'Field');
            if (!in_array('email', $colunasPessoas)) {
                $pdo->query("ALTER TABLE pessoas ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL");
            }
            if (!in_array('comentario', $colunasPessoas)) {
                $pdo->query("ALTER TABLE pessoas ADD COLUMN comentario TEXT NULL DEFAULT NULL");
            }

            // 7. Tabela cloudflare_api_logs
            $pdo->query("CREATE TABLE IF NOT EXISTS `cloudflare_api_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `texto` TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 8. Tabela log_criacao_contas (log imutável de criação de contas)
            $pdo->query("CREATE TABLE IF NOT EXISTS `log_criacao_contas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conta_id` INT NOT NULL,
                `slack_sync` TINYINT(1) NOT NULL DEFAULT 0,
                `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_criado_em (`criado_em`),
                INDEX idx_slack_sync (`slack_sync`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // 9. Tabela cloudflare_dominios (rotação automática de domínios)
            $pdo->query("CREATE TABLE IF NOT EXISTS `cloudflare_dominios` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `prefixo` VARCHAR(100) NOT NULL DEFAULT 'conta',
                `dominio` VARCHAR(100) NOT NULL,
                `contador` INT NOT NULL DEFAULT 1,
                `ativo` TINYINT(1) NOT NULL DEFAULT 0,
                `esgotado` TINYINT(1) NOT NULL DEFAULT 0,
                `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $jaExecutou = true;
        } catch (Exception $e) {
            // Silencia erro em runtime para evitar travar a tela
            error_log("Erro em executarMigracoes: " . $e->getMessage());
        }
    }
}
