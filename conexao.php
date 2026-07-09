<?php
// Carrega variáveis de ambiente se o arquivo .env existir
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Configurações do Banco de Dados
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'licencas';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// Configurações de API e E-mail
$apiHerosms = getenv('API_HEROSMS') ?: '1db85Af88f65e2840754836202520ee6';
$emailHost = getenv('EMAIL_HOST') ?: 'mail.seudominio.com';
$emailUser = getenv('EMAIL_USER') ?: 'admin@seudominio.com';
$emailPass = getenv('EMAIL_PASS') ?: 'sua_senha_forte_do_email';

// Token para segurança das APIs externas
define('API_TOKEN', getenv('API_TOKEN') ?: 'dollfinn_secret_token_123');

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    $opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $opcoes);

    // --- MIGRATIONS AUTOMÁTICAS ---
    try {
        // 1. Tabela contas
        $stmt = $pdo->query("SHOW COLUMNS FROM contas");
        $colunasContas = array_column($stmt->fetchAll(), 'Field');
        
        if (!in_array('data_exportado', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN data_exportado DATETIME NULL");
        }
        if (!in_array('bm_criada', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN bm_criada TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('data_bm_criada', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN data_bm_criada DATETIME NULL");
        }
        if (!in_array('pagina_criada', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN pagina_criada TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('data_pagina_criada', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN data_pagina_criada DATETIME NULL");
        }
        if (!in_array('dev_criada', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN dev_criada TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('data_dev_criada', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN data_dev_criada DATETIME NULL");
        }
        if (!in_array('slack_perfil_sync', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN slack_perfil_sync TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('slack_bm_sync', $colunasContas)) {
            $pdo->query("ALTER TABLE contas ADD COLUMN slack_bm_sync TINYINT(1) NOT NULL DEFAULT 0");
        }

        // 2. Tabela configuracoes
        $stmtConf = $pdo->query("SHOW COLUMNS FROM configuracoes");
        $colunasConf = array_column($stmtConf->fetchAll(), 'Field');
        
        if (!in_array('slack_token', $colunasConf)) {
            $pdo->query("ALTER TABLE configuracoes ADD COLUMN slack_token VARCHAR(255) NULL");
        }
        if (!in_array('slack_canal_notificacao', $colunasConf)) {
            $pdo->query("ALTER TABLE configuracoes ADD COLUMN slack_canal_notificacao VARCHAR(100) NULL");
        }
        if (!in_array('preco_perfil', $colunasConf)) {
            $pdo->query("ALTER TABLE configuracoes ADD COLUMN preco_perfil DECIMAL(10,2) NOT NULL DEFAULT 20.00");
        }
        if (!in_array('preco_bm', $colunasConf)) {
            $pdo->query("ALTER TABLE configuracoes ADD COLUMN preco_bm DECIMAL(10,2) NOT NULL DEFAULT 30.00");
        }
        if (!in_array('preco_pagina', $colunasConf)) {
            $pdo->query("ALTER TABLE configuracoes ADD COLUMN preco_pagina DECIMAL(10,2) NOT NULL DEFAULT 10.00");
        }
        if (!in_array('cloudflare_token', $colunasConf)) {
            $pdo->query("ALTER TABLE configuracoes ADD COLUMN cloudflare_token VARCHAR(255) NULL");
        }
        if (!in_array('cloudflare_zone_id', $colunasConf)) {
            $pdo->query("ALTER TABLE configuracoes ADD COLUMN cloudflare_zone_id VARCHAR(255) NULL");
        }
        if (!in_array('cloudflare_dest_email', $colunasConf)) {
            $pdo->query("ALTER TABLE configuracoes ADD COLUMN cloudflare_dest_email VARCHAR(255) NULL");
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

        // Garantir novas colunas da tabela slack_listas
        $stmtSlackListasCols = $pdo->query("SHOW COLUMNS FROM slack_listas");
        $colunasSlackListas = array_column($stmtSlackListasCols->fetchAll(), 'Field');
        if (!in_array('nome', $colunasSlackListas)) {
            $pdo->query("ALTER TABLE slack_listas ADD COLUMN nome VARCHAR(255) NULL");
        }
        $pdo->query("ALTER TABLE slack_listas MODIFY COLUMN mes VARCHAR(7) NULL");

        // 4. Tabela slack_lotes_count (Para evitar problemas de cache da API do Slack)
        $pdo->query("CREATE TABLE IF NOT EXISTS `slack_lotes_count` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `list_id` varchar(50) NOT NULL,
            `week` varchar(100) NOT NULL,
            `type` varchar(20) NOT NULL,
            `domain` varchar(50) DEFAULT NULL,
            `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Garantir coluna domain na tabela slack_lotes_count
        $stmtLotesCols = $pdo->query("SHOW COLUMNS FROM slack_lotes_count");
        $colunasLotes = array_column($stmtLotesCols->fetchAll(), 'Field');
        if (!in_array('domain', $colunasLotes)) {
            $pdo->query("ALTER TABLE slack_lotes_count ADD COLUMN domain VARCHAR(50) NULL DEFAULT NULL");
        }
        $pdo->query("UPDATE slack_lotes_count SET domain = 'dollfinn' WHERE domain IS NULL");

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

        // Garantir novas colunas da tabela apps
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

    } catch (Exception $e) {
        // Silenciar erro em produção ou logar
    }

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

/**
 * Reconstrói a estrutura de rich_text exigida pela API do Slack para campos de texto.
 */
if (!function_exists('buildRichText')) {
    function buildRichText($text) {
        return [
            [
                "type" => "rich_text",
                "elements" => [
                    [
                        "type" => "rich_text_section",
                        "elements" => [
                            [
                                "type" => "text",
                                "text" => $text
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}

/**
 * Extrai o texto puro de um campo do Slack (seja texto simples, rich_text ou outros formatos).
 */
if (!function_exists('extrairTextoSlackField')) {
    function extrairTextoSlackField($f) {
        if (!empty($f['text'])) {
            return $f['text'];
        } elseif (!empty($f['text_value'])) {
            return $f['text_value'];
        } elseif (!empty($f['value']) && is_string($f['value'])) {
            return $f['value'];
        } elseif (!empty($f['rich_text'])) {
            $extracted = '';
            array_walk_recursive($f['rich_text'], function($val, $key) use (&$extracted) {
                if ($key === 'text') $extracted .= $val;
            });
            return $extracted;
        }
        return '';
    }
}
/**
 * Retorna o título da semana contendo a data informada no formato do mês.
 * A semana começa sempre no Domingo e termina no Sábado.
 * A primeira semana do mês começa no dia 1 e vai até o primeiro Sábado.
 * A última semana começa no último Domingo e termina no último dia do mês.
 */
if (!function_exists('obterSemanaDoMes')) {
    function obterSemanaDoMes($timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        $ano = date('Y', $timestamp);
        $mes = date('m', $timestamp);
        $diaAlvo = (int)date('d', $timestamp);
        
        $diasNoMes = (int)date('t', $timestamp);
        
        $semanaEncontrada = "";
        $dia = 1;
        
        while ($dia <= $diasNoMes) {
            $dataInicio = sprintf('%02d/%02d/%04d', $dia, $mes, $ano);
            
            $timestampDia = strtotime("{$ano}-{$mes}-" . sprintf('%02d', $dia));
            $diaSemana = (int)date('w', $timestampDia); // 0 (Dom) a 6 (Sáb)
            
            $diasAteSabado = 6 - $diaSemana;
            $diaFim = $dia + $diasAteSabado;
            
            if ($diaFim > $diasNoMes) {
                $diaFim = $diasNoMes;
            }
            
            $dataFim = sprintf('%02d/%02d/%04d', $diaFim, $mes, $ano);
            
            if ($diaAlvo >= $dia && $diaAlvo <= $diaFim) {
                $semanaEncontrada = "Semana {$dataInicio} - {$dataFim}";
                break;
            }
            
            $dia = $diaFim + 1;
        }
        
        return $semanaEncontrada;
    }
}

/**
 * Realiza a sincronização automática das tarefas e lotes no Slack Lists.
 */
if (!function_exists('sincronizarSlackTracker')) {
    function sincronizarSlackTracker($pdo) {
        try {
            // 1. Obter configurações do Slack e domínio
            $stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao, email_dominio FROM configuracoes LIMIT 1");
            $config = $stmtConf->fetch();
            if (!$config) return;

            $token = $config['slack_token'] ?? '';
            $canal = $config['slack_canal_notificacao'] ?? '';
            if (empty($token)) return;

            $dominioEmail = $config['email_dominio'] ?? '';
            $dominioLimpo = ltrim($dominioEmail, '@');
            $nomeDominio = strtolower(explode('.', $dominioLimpo)[0]);
            if (empty($nomeDominio)) {
                $nomeDominio = "dollfinn";
            }

            // 2. Determinar o mês atual (YYYY-MM)
            $mesAtual = date('Y-m');

            // 3. Buscar ou criar a lista correspondente no banco
            $stmtLista = $pdo->prepare("SELECT * FROM slack_listas WHERE mes = ?");
            $stmtLista->execute([$mesAtual]);
            $listaObj = $stmtLista->fetch();

            if ($listaObj) {
                $list_id = $listaObj['list_id'];
                $primary_col_id = $listaObj['primary_col_id'];
            } else {
                // Criar nova lista via Slack API
                $meses = [
                    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
                    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
                    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
                ];
                $mesNum = date('m');
                $ano = date('Y');
                $nomeMes = $meses[$mesNum] ?? 'Mês';
                $list_name = "Gestão - {$nomeMes} {$ano}";

                $ch = curl_init("https://slack.com/api/slackLists.create");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . $token,
                    "Content-Type: application/json; charset=utf-8"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    "name" => $list_name,
                    "todo_mode" => true
                ]));
                $resRaw = curl_exec($ch);
                curl_close($ch);

                $resJson = json_decode($resRaw, true);
                if (!$resJson || !isset($resJson['ok']) || !$resJson['ok']) {
                    return;
                }

                $list_id = $resJson['list_id'];

                // Resolver coluna primária
                $primary_col_id = 'name';
                if (isset($resJson['list_metadata']['schema'])) {
                    foreach ($resJson['list_metadata']['schema'] as $col) {
                        if (!empty($col['is_primary_column'])) {
                            $primary_col_id = $col['id'];
                            break;
                        }
                    }
                }

                // Gravar no banco de dados
                $pdo->prepare("INSERT INTO slack_listas (mes, list_id, primary_col_id) VALUES (?, ?, ?)")
                    ->execute([$mesAtual, $list_id, $primary_col_id]);

                // Se tiver canal de notificação, enviar mensagem avisando com o link da lista
                if (!empty($canal)) {
                    // Obter link amigável dinamicamente usando auth.test
                    $chAuth = curl_init("https://slack.com/api/auth.test");
                    curl_setopt($chAuth, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chAuth, CURLOPT_POST, true);
                    curl_setopt($chAuth, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer " . $token,
                        "Content-Type: application/json; charset=utf-8"
                    ]);
                    $authRaw = curl_exec($chAuth);
                    curl_close($chAuth);

                    $authRes = json_decode($authRaw, true);
                    $team_id = $authRes['team_id'] ?? 'T09KA5AATL4';
                    $team_domain = isset($authRes['url']) ? parse_url($authRes['url'], PHP_URL_HOST) : 'winup-workspace.slack.com';
                    $list_link = "https://{$team_domain}/lists/{$team_id}/{$list_id}";

                    $msg = "📅 *Nova lista do mês criada:* <{$list_link}|{$list_name}>";

                    $chMsg = curl_init("https://slack.com/api/chat.postMessage");
                    curl_setopt($chMsg, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chMsg, CURLOPT_POST, true);
                    curl_setopt($chMsg, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer " . $token,
                        "Content-Type: application/json; charset=utf-8"
                    ]);
                    curl_setopt($chMsg, CURLOPT_POSTFIELDS, json_encode([
                        "channel" => $canal,
                        "text" => $msg
                    ]));
                    curl_exec($chMsg);
                    curl_close($chMsg);
                }
            }

            // 4. Determinar o título da semana atual (Começando no sábado anterior e terminando na próxima sexta)
            $week_title = obterSemanaDoMes(time());

            // Obter itens da lista do Slack
            $chItems = curl_init("https://slack.com/api/slackLists.items.list");
            curl_setopt($chItems, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chItems, CURLOPT_POST, true);
            curl_setopt($chItems, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $token,
                "Content-Type: application/json; charset=utf-8"
            ]);
            curl_setopt($chItems, CURLOPT_POSTFIELDS, json_encode([
                "list_id" => $list_id
            ]));
            $itemsRes = json_decode(curl_exec($chItems), true);
            curl_close($chItems);

            $week_row_id = null;
            $items = [];
            if ($itemsRes && isset($itemsRes['ok']) && $itemsRes['ok']) {
                $items = $itemsRes['items'] ?? [];
                foreach ($items as $item) {
                    if (empty($item['parent_item_id'])) {
                        $itemName = '';
                        if (isset($item['fields'])) {
                            foreach ($item['fields'] as $f) {
                                if ($f['key'] === 'name' || $f['column_id'] === $primary_col_id) {
                                    $itemName = extrairTextoSlackField($f);
                                    break;
                                }
                            }
                        }
                        if ($itemName === $week_title) {
                            $week_row_id = $item['id'];
                            break;
                        }
                    }
                }
            }

            // Se a semana não existe, criar
            if (!$week_row_id) {
                $chNewWeek = curl_init("https://slack.com/api/slackLists.items.create");
                curl_setopt($chNewWeek, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chNewWeek, CURLOPT_POST, true);
                curl_setopt($chNewWeek, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . $token,
                    "Content-Type: application/json; charset=utf-8"
                ]);
                curl_setopt($chNewWeek, CURLOPT_POSTFIELDS, json_encode([
                    "list_id" => $list_id,
                    "initial_fields" => [
                        [
                            "column_id" => $primary_col_id,
                            "rich_text" => buildRichText($week_title)
                        ]
                    ]
                ]));
                $newWeekRes = json_decode(curl_exec($chNewWeek), true);
                curl_close($chNewWeek);

                if ($newWeekRes && isset($newWeekRes['ok']) && $newWeekRes['ok']) {
                    $week_row_id = $newWeekRes['item']['id'] ?? $newWeekRes['id'] ?? null;
                    if (isset($newWeekRes['item'])) {
                        $items[] = $newWeekRes['item'];
                    }
                }
            }

            if (!$week_row_id) return;

            // 5. Contar e sincronizar Lotes de 50 Perfis Criados
            $contasUnsynced = $pdo->query("SELECT id, email FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0 ORDER BY id ASC")->fetchAll();
            
            $perfisPorDominio = [];
            foreach ($contasUnsynced as $c) {
                $domainEmail = strtolower(trim(explode('@', $c['email'])[1] ?? ''));
                $domName = strtolower(explode('.', $domainEmail)[0] ?? 'dollfinn');
                if (empty($domName)) $domName = 'dollfinn';
                $perfisPorDominio[$domName][] = $c['id'];
            }

            foreach ($perfisPorDominio as $domName => $idsDaZone) {
                $totalZone = count($idsDaZone);
                if ($totalZone >= 50) {
                    // Obter count acumulado de lotes para este domínio (independente de semana ou lista)
                    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM slack_lotes_count WHERE domain = ? AND type = 'perfil'");
                    $stmtCount->execute([$domName]);
                    $loteCount = (int) $stmtCount->fetchColumn();

                    $startRange = ($loteCount * 50) + 1;
                    $endRange = ($loteCount + 1) * 50;
                    $loteText = "{$startRange} - {$endRange} perfis {$domName}";
                    $hoje = date('Y-m-d');

                    $chSub = curl_init("https://slack.com/api/slackLists.items.create");
                    curl_setopt($chSub, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chSub, CURLOPT_POST, true);
                    curl_setopt($chSub, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer " . $token,
                        "Content-Type: application/json; charset=utf-8"
                    ]);
                    curl_setopt($chSub, CURLOPT_POSTFIELDS, json_encode([
                        "list_id" => $list_id,
                        "parent_item_id" => $week_row_id,
                        "initial_fields" => [
                            [
                                "column_id" => $primary_col_id,
                                "rich_text" => buildRichText($loteText)
                            ],
                            [
                                "column_id" => "Col00",
                                "checkbox" => true
                            ],
                            [
                                "column_id" => "Col02",
                                "date" => [$hoje]
                            ]
                        ]
                    ]));
                    $subRes = json_decode(curl_exec($chSub), true);
                    curl_close($chSub);

                    if ($subRes && isset($subRes['ok']) && $subRes['ok']) {
                        // Registra que um novo lote foi criado
                        $pdo->prepare("INSERT INTO slack_lotes_count (list_id, week, type, domain) VALUES (?, ?, ?, ?)")->execute([$list_id, $week_title, 'perfil', $domName]);

                        $idsToUpdate = array_slice($idsDaZone, 0, 50);
                        $in = str_repeat('?,', count($idsToUpdate) - 1) . '?';
                        $pdo->prepare("UPDATE contas SET slack_perfil_sync = 1 WHERE id IN ($in)")->execute($idsToUpdate);
                    }
                }
            }

            // 6. Contar e sincronizar Lotes de 50 BMs Criadas
            $bmsUnsynced = $pdo->query("SELECT id, email FROM contas WHERE bm_criada = 1 AND slack_bm_sync = 0 ORDER BY data_bm_criada ASC")->fetchAll();
            
            $bmsPorDominio = [];
            foreach ($bmsUnsynced as $b) {
                $domainEmail = strtolower(trim(explode('@', $b['email'])[1] ?? ''));
                $domName = strtolower(explode('.', $domainEmail)[0] ?? 'dollfinn');
                if (empty($domName)) $domName = 'dollfinn';
                $bmsPorDominio[$domName][] = $b['id'];
            }

            foreach ($bmsPorDominio as $domName => $idsDaZone) {
                $totalZone = count($idsDaZone);
                if ($totalZone >= 50) {
                    // Obter count acumulado de lotes para este domínio (independente de semana ou lista)
                    $stmtCountBm = $pdo->prepare("SELECT COUNT(*) FROM slack_lotes_count WHERE domain = ? AND type = 'bm'");
                    $stmtCountBm->execute([$domName]);
                    $loteCountBm = (int) $stmtCountBm->fetchColumn();

                    $startRangeBm = ($loteCountBm * 50) + 1;
                    $endRangeBm = ($loteCountBm + 1) * 50;
                    $loteTextBm = "{$startRangeBm} - {$endRangeBm} BMs {$domName}";
                    $hoje = date('Y-m-d');

                    $chSub = curl_init("https://slack.com/api/slackLists.items.create");
                    curl_setopt($chSub, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chSub, CURLOPT_POST, true);
                    curl_setopt($chSub, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer " . $token,
                        "Content-Type: application/json; charset=utf-8"
                    ]);
                    curl_setopt($chSub, CURLOPT_POSTFIELDS, json_encode([
                        "list_id" => $list_id,
                        "parent_item_id" => $week_row_id,
                        "initial_fields" => [
                            [
                                "column_id" => $primary_col_id,
                                "rich_text" => buildRichText($loteTextBm)
                            ],
                            [
                                "column_id" => "Col00",
                                "checkbox" => true
                            ],
                            [
                                "column_id" => "Col02",
                                "date" => [$hoje]
                            ]
                        ]
                    ]));
                    $subRes = json_decode(curl_exec($chSub), true);
                    curl_close($chSub);

                    if ($subRes && isset($subRes['ok']) && $subRes['ok']) {
                        // Registra que um novo lote foi criado
                        $pdo->prepare("INSERT INTO slack_lotes_count (list_id, week, type, domain) VALUES (?, ?, ?, ?)")->execute([$list_id, $week_title, 'bm', $domName]);

                        $idsToUpdate = array_slice($idsDaZone, 0, 50);
                        $in = str_repeat('?,', count($idsToUpdate) - 1) . '?';
                        $pdo->prepare("UPDATE contas SET slack_bm_sync = 1 WHERE id IN ($in)")->execute($idsToUpdate);
                    }
                }
            }
        } catch (Exception $e) {
            // Silenciar/logar erro
        }
    }
}

/**
 * Envia uma notificação de texto simples ou formatada ao canal do Slack configurado.
 */
if (!function_exists('enviarNotificacaoSlack')) {
    function enviarNotificacaoSlack($pdo, $mensagem) {
        try {
            $stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao FROM configuracoes LIMIT 1");
            $config = $stmtConf->fetch();
            if (!$config || empty($config['slack_token']) || empty($config['slack_canal_notificacao'])) {
                return false;
            }

            $token = $config['slack_token'];
            $canal = $config['slack_canal_notificacao'];

            $ch = curl_init("https://slack.com/api/chat.postMessage");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $token,
                "Content-Type: application/json; charset=utf-8"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "channel" => $canal,
                "text" => $mensagem
            ]));
            $resRaw = curl_exec($ch);
            curl_close($ch);

            $resJson = json_decode($resRaw, true);
            return ($resJson && isset($resJson['ok']) && $resJson['ok']);
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Verifica o status de um app da Meta (Facebook Graph API)
 * Retorna um array ['status_conexao' => 'online'|'caiu', 'status' => 'analise'|'aprovado'|'rejeitado', 'permissions_status' => array, 'observacao_adicional' => string|null]
 */
if (!function_exists('verificarAppStatusMeta')) {
    function verificarAppStatusMeta($app_id, $app_secret = null, $user_access_token = null, $tracked_permissions = '') {
        $app_id = trim($app_id);
        if (empty($app_id)) {
            return [
                'status_conexao' => 'caiu', 
                'status' => 'rejeitado', 
                'permissions_status' => [],
                'observacao_adicional' => null
            ];
        }

        $token = null;
        if (!empty($user_access_token)) {
            $token = trim($user_access_token);
        } elseif (!empty($app_secret)) {
            $token = $app_id . '|' . trim($app_secret);
        }

        $observacao_adicional = null;
        $permissions_status = [];

        // Parse tracked permissions
        $tracked_arr = [];
        if (!empty($tracked_permissions)) {
            $tracked_arr = array_filter(array_map('trim', explode(',', $tracked_permissions)));
        }

        if (!empty($token)) {
            // 1. Chamada autenticada com Token para buscar detalhes básicos
            $ch = curl_init();
            $url = "https://graph.facebook.com/v19.0/" . urlencode($app_id) . "?fields=id,name,development_mode&access_token=" . urlencode($token);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $res = curl_exec($ch);
            curl_close($ch);

            $dados = json_decode($res, true);
            if ($dados && isset($dados['id'])) {
                $devMode = true; // Default seguro
                if (isset($dados['development_mode'])) {
                    $devMode = !empty($dados['development_mode']);
                } else {
                    // Se o campo development_mode não veio na resposta do token (por falta de permissões ou escopo do token),
                    // fazemos uma verificação na API pública do Graph. Se o app estiver no modo Live, a chamada pública funcionará e retornará seu ID.
                    $chPub = curl_init();
                    $urlPub = "https://graph.facebook.com/v19.0/" . urlencode($app_id);
                    curl_setopt($chPub, CURLOPT_URL, $urlPub);
                    curl_setopt($chPub, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chPub, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($chPub, CURLOPT_TIMEOUT, 10);
                    curl_setopt($chPub, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                    $resPub = curl_exec($chPub);
                    curl_close($chPub);

                    $dadosPub = json_decode($resPub, true);
                    $isLive = false;
                    if ($dadosPub && isset($dadosPub['id'])) {
                        $isLive = true;
                    }
                    
                    $devMode = !$isLive;
                }
                $status = $devMode ? 'analise' : 'aprovado';
                $status_conexao = 'online';

                // 2. Buscar status detalhado das permissões do aplicativo
                $chPerm = curl_init();
                $urlPerm = "https://graph.facebook.com/v19.0/" . urlencode($app_id) . "/permissions?access_token=" . urlencode($token);
                curl_setopt($chPerm, CURLOPT_URL, $urlPerm);
                curl_setopt($chPerm, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chPerm, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chPerm, CURLOPT_TIMEOUT, 10);
                $resPerm = curl_exec($chPerm);
                curl_close($chPerm);

                $permDados = json_decode($resPerm, true);
                $approved_list = [];
                if ($permDados && isset($permDados['data'])) {
                    foreach ($permDados['data'] as $item) {
                        if (isset($item['permission']) && isset($item['status'])) {
                            $approved_list[$item['permission']] = strtolower($item['status']);
                        }
                    }
                }

                // Mapear cada permissão monitorada
                $all_live = true;
                foreach ($tracked_arr as $p) {
                    $p_status = $approved_list[$p] ?? 'unapproved';
                    $permissions_status[$p] = $p_status;
                    if ($p_status !== 'live' && $p_status !== 'granted' && $p_status !== 'aprovado') {
                        $all_live = false;
                    }
                }

                // 3. Se todas as permissões monitoradas forem aprovadas (live) e o app estiver em modo Dev,
                // tentar mudar automaticamente para Live Mode (development_mode = false)
                if ($devMode && count($tracked_arr) > 0 && $all_live) {
                    $chPost = curl_init();
                    curl_setopt($chPost, CURLOPT_URL, "https://graph.facebook.com/v19.0/" . urlencode($app_id));
                    curl_setopt($chPost, CURLOPT_POST, true);
                    curl_setopt($chPost, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chPost, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($chPost, CURLOPT_POSTFIELDS, http_build_query([
                        'development_mode' => 'false',
                        'access_token' => $token
                    ]));
                    $postRes = curl_exec($chPost);
                    curl_close($chPost);

                    $postDados = json_decode($postRes, true);
                    if ($postDados && !empty($postDados['success'])) {
                        $status = 'aprovado';
                        $status_conexao = 'online';
                        $observacao_adicional = "[Automático] Aplicativo ativado para Live Mode com sucesso, pois todas as permissões monitoradas foram aprovadas.";
                    } else {
                        $msgErro = $postDados['error']['message'] ?? 'Erro desconhecido ao mudar modo do aplicativo.';
                        $observacao_adicional = "[Automático] Tentativa de ativar Live Mode falhou: " . $msgErro;
                    }
                }

                return [
                    'status_conexao' => $status_conexao,
                    'status' => $status,
                    'permissions_status' => $permissions_status,
                    'observacao_adicional' => $observacao_adicional
                ];
            }

            // Se o token fornecido retornou erro, ou não conseguiu ID, tentamos sem token (público) como último recurso
        }

        // Chamada pública para a Graph API (Sem token ou como fallback de token expirado)
        $ch = curl_init();
        $url = "https://graph.facebook.com/v19.0/" . urlencode($app_id);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $res = curl_exec($ch);
        curl_close($ch);

        $dados = json_decode($res, true);
        
        // Mapear todas as monitoradas como unapproved por falta de token
        foreach ($tracked_arr as $p) {
            $permissions_status[$p] = 'unapproved';
        }

        // 1. Se a chamada pública sem token retornou o ID, o app está no modo Live (aprovado) e está Online
        if ($dados && isset($dados['id'])) {
            return [
                'status_conexao' => 'online',
                'status' => 'aprovado',
                'permissions_status' => $permissions_status,
                'observacao_adicional' => null
            ];
        }

        // 2. Se a chamada pública falhou, fazemos a checagem del redirecionamento do diálogo OAuth do Facebook
        $oauthUrl = "https://www.facebook.com/v19.0/dialog/oauth?client_id=" . urlencode($app_id) . "&redirect_uri=https://www.facebook.com/connect/login_success.html";
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $oauthUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false); // Não seguir redirects!
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch2, CURLOPT_HEADER, true);
        curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $html = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch2, CURLINFO_REDIRECT_URL);
        curl_close($ch2);

        if ($httpCode2 == 302 && !empty($redirectUrl)) {
            // Se redirecionar para um erro de ID inválido do SDK/Plataforma, o app caiu/não existe
            if (stripos($redirectUrl, 'PLATFORM__INVALID_APP_ID') !== false || stripos($redirectUrl, '/oauth/error/') !== false) {
                return [
                    'status_conexao' => 'caiu', 
                    'status' => 'rejeitado',
                    'permissions_status' => $permissions_status,
                    'observacao_adicional' => null
                ];
            }
            
            // Caso contrário (redirecionou para a tela de login ou fluxo válido), o app existe e está em modo de desenvolvimento.
            // Como não está público (Live), sua conexão para fins de negócio é considerada 'caiu' (offline).
            return [
                'status_conexao' => 'caiu', 
                'status' => 'analise',
                'permissions_status' => $permissions_status,
                'observacao_adicional' => null
            ];
        }

        // Se não redirecionou ou houve falha crítica de rede, consideramos o app como caído
        return [
            'status_conexao' => 'caiu', 
            'status' => 'rejeitado',
            'permissions_status' => $permissions_status,
            'observacao_adicional' => null
        ];
    }
}