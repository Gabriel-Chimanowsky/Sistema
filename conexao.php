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
        

        // 3. Tabela slack_listas
        $pdo->query("CREATE TABLE IF NOT EXISTS `slack_listas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `mes` varchar(7) NOT NULL UNIQUE COMMENT 'Formato YYYY-MM',
            `list_id` varchar(50) NOT NULL,
            `primary_col_id` varchar(50) NOT NULL,
            `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    } catch (Exception $e) {
        // Silenciar erro em produção ou logar
    }

} catch (PDOException $e) {
    if (strpos($_SERVER['REQUEST_URI'], '.php') !== false && strpos($_SERVER['REQUEST_URI'], 'api') === false) {
        die("Erro crítico: Não foi possível conectar ao banco de dados.");
    } else {
        header('Content-Type: application/json');
        die(json_encode([
            "sucesso" => false, 
            "mensagem" => "Erro crítico: Não foi possível conectar ao banco de dados."
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
            $contasUnsynced = $pdo->query("SELECT id FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0 ORDER BY id ASC")->fetchAll();
            $totalUnsynced = count($contasUnsynced);

            if ($totalUnsynced >= 50) {
                $loteCount = 0;
                foreach ($items as $item) {
                    if (isset($item['parent_item_id']) && $item['parent_item_id'] === $week_row_id) {
                        $subName = '';
                        foreach ($item['fields'] as $f) {
                            if ($f['key'] === 'name' || $f['column_id'] === $primary_col_id) {
                                $subName = extrairTextoSlackField($f);
                                break;
                            }
                        }
                        if (strpos($subName, "perfis") !== false) {
                            $loteCount++;
                        }
                    }
                }

                $startRange = ($loteCount * 50) + 1;
                $endRange = ($loteCount + 1) * 50;
                $loteText = "{$startRange} - {$endRange} perfis {$nomeDominio}";
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
                    $idsToUpdate = array_slice(array_column($contasUnsynced, 'id'), 0, 50);
                    $in = str_repeat('?,', count($idsToUpdate) - 1) . '?';
                    $pdo->prepare("UPDATE contas SET slack_perfil_sync = 1 WHERE id IN ($in)")->execute($idsToUpdate);
                }
            }

            // 6. Contar e sincronizar Lotes de 50 BMs Criadas
            $bmsUnsynced = $pdo->query("SELECT id FROM contas WHERE bm_criada = 1 AND slack_bm_sync = 0 ORDER BY data_bm_criada ASC")->fetchAll();
            $totalBmsUnsynced = count($bmsUnsynced);

            if ($totalBmsUnsynced >= 50) {
                $loteCountBm = 0;
                foreach ($items as $item) {
                    if (isset($item['parent_item_id']) && $item['parent_item_id'] === $week_row_id) {
                        $subName = '';
                        foreach ($item['fields'] as $f) {
                            if ($f['key'] === 'name' || $f['column_id'] === $primary_col_id) {
                                $subName = extrairTextoSlackField($f);
                                break;
                            }
                        }
                        if (strpos($subName, "BMs") !== false) {
                            $loteCountBm++;
                        }
                    }
                }

                $startRangeBm = ($loteCountBm * 50) + 1;
                $endRangeBm = ($loteCountBm + 1) * 50;
                $loteTextBm = "{$startRangeBm} - {$endRangeBm} BMs {$nomeDominio}";
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
                    $idsToUpdate = array_slice(array_column($bmsUnsynced, 'id'), 0, 50);
                    $in = str_repeat('?,', count($idsToUpdate) - 1) . '?';
                    $pdo->prepare("UPDATE contas SET slack_bm_sync = 1 WHERE id IN ($in)")->execute($idsToUpdate);
                }
            }
        } catch (Exception $e) {
            // Silenciar/logar erro
        }
    }
}