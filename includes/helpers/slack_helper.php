<?php
/**
 * Helper para Integração com a API do Slack & Slack Lists
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
            $diaSemana = (int)date('w', $timestampDia);
            
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

if (!function_exists('sincronizarSlackTracker')) {
    function sincronizarSlackTracker($pdo) {
        try {
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

            $mesAtual = date('Y-m');

            $stmtLista = $pdo->prepare("SELECT * FROM slack_listas WHERE mes = ?");
            $stmtLista->execute([$mesAtual]);
            $listaObj = $stmtLista->fetch();

            if ($listaObj) {
                $list_id = $listaObj['list_id'];
                $primary_col_id = $listaObj['primary_col_id'];
            } else {
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

                $primary_col_id = 'name';
                if (isset($resJson['list_metadata']['schema'])) {
                    foreach ($resJson['list_metadata']['schema'] as $col) {
                        if (!empty($col['is_primary_column'])) {
                            $primary_col_id = $col['id'];
                            break;
                        }
                    }
                }

                $pdo->prepare("INSERT INTO slack_listas (mes, list_id, primary_col_id) VALUES (?, ?, ?)")
                    ->execute([$mesAtual, $list_id, $primary_col_id]);

                if (!empty($canal)) {
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

                    $destinatariosTracker = obterDestinatariosSlack($canal);
                    foreach ($destinatariosTracker as $destC) {
                        $chMsg = curl_init("https://slack.com/api/chat.postMessage");
                        curl_setopt($chMsg, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chMsg, CURLOPT_POST, true);
                        curl_setopt($chMsg, CURLOPT_HTTPHEADER, [
                            "Authorization: Bearer " . $token,
                            "Content-Type: application/json; charset=utf-8"
                        ]);
                        curl_setopt($chMsg, CURLOPT_POSTFIELDS, json_encode([
                            "channel" => $destC,
                            "text" => $msg
                        ]));
                        curl_exec($chMsg);
                        curl_close($chMsg);
                    }
                }
            }

            $week_title = obterSemanaDoMes(time());

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
                }
            }

            if (!$week_row_id) return;

            // 5. Contar e sincronizar Lotes de 50 Perfis via log imutável de criação
            $pendingLogCount = (int) $pdo->query("SELECT COUNT(*) FROM log_criacao_contas WHERE slack_sync = 0")->fetchColumn();

            if ($pendingLogCount >= 50) {
                $loteText = "50 perfis criados {$nomeDominio}";
                $hoje = date('Y-m-d');

                $pendingLogIds = array_column(
                    $pdo->query("SELECT id FROM log_criacao_contas WHERE slack_sync = 0 ORDER BY id ASC LIMIT 50")->fetchAll(),
                    'id'
                );

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
                    $pdo->prepare("INSERT INTO slack_lotes_count (list_id, week, type, domain) VALUES (?, ?, ?, ?)")
                        ->execute([$list_id, $week_title, 'perfil', $nomeDominio]);

                    $inLog = implode(',', array_map('intval', $pendingLogIds));
                    $pdo->query("UPDATE log_criacao_contas SET slack_sync = 1 WHERE id IN ({$inLog})");
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
                    $stmtCountBm = $pdo->prepare("SELECT COUNT(*) FROM slack_lotes_count WHERE domain = ? AND type = 'bm'");
                    $stmtCountBm->execute([$domName]);
                    $loteCountBm = (int) $stmtCountBm->fetchColumn();

                    $loteTextBm = "50 BMs criadas {$domName}";
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
                        $pdo->prepare("INSERT INTO slack_lotes_count (list_id, week, type, domain) VALUES (?, ?, ?, ?)")
                            ->execute([$list_id, $week_title, 'bm', $domName]);

                        $idsToUpdate = array_slice($idsDaZone, 0, 50);
                        $in = str_repeat('?,', count($idsToUpdate) - 1) . '?';
                        $pdo->prepare("UPDATE contas SET slack_bm_sync = 1 WHERE id IN ($in)")->execute($idsToUpdate);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erro em sincronizarSlackTracker: " . $e->getMessage());
        }
    }
}

if (!function_exists('obterDestinatariosSlack')) {
    /**
     * Extrai e limpa a lista de destinatários (IDs de membros ou canais) a partir de uma string bruta
     * Suporta separadores: vírgula, ponto-e-vírgula, quebra de linha ou espaço
     */
    function obterDestinatariosSlack($canaisRaw) {
        if (empty($canaisRaw) || !is_string($canaisRaw)) {
            return [];
        }
        $partes = preg_split('/[\r\n,;\s]+/', trim($canaisRaw));
        $destinatarios = [];
        foreach ($partes as $p) {
            $p = trim($p);
            $p = trim($p, '"\' ');
            if (!empty($p)) {
                $destinatarios[] = $p;
            }
        }
        return array_values(array_unique($destinatarios));
    }
}

if (!function_exists('enviarNotificacaoSlack')) {
    /**
     * Envia notificação para todos os destinatários configurados (canais e/ou IDs de usuários)
     */
    function enviarNotificacaoSlack($pdo, $mensagem, $destinatariosManual = null) {
        try {
            $stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao FROM configuracoes LIMIT 1");
            $config = $stmtConf->fetch();
            if (!$config || empty($config['slack_token'])) {
                return false;
            }

            $token = $config['slack_token'];
            
            if ($destinatariosManual !== null) {
                $destinatarios = is_array($destinatariosManual) ? $destinatariosManual : obterDestinatariosSlack($destinatariosManual);
            } else {
                $destinatarios = obterDestinatariosSlack($config['slack_canal_notificacao'] ?? '');
            }

            if (empty($destinatarios)) {
                return false;
            }

            $sucessos = 0;
            foreach ($destinatarios as $dest) {
                $ch = curl_init("https://slack.com/api/chat.postMessage");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . $token,
                    "Content-Type: application/json; charset=utf-8"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    "channel" => $dest,
                    "text" => $mensagem
                ]));
                $resRaw = curl_exec($ch);
                curl_close($ch);

                $resJson = json_decode($resRaw, true);
                if ($resJson && isset($resJson['ok']) && $resJson['ok']) {
                    $sucessos++;
                } else {
                    $erroMsg = $resJson['error'] ?? 'desconhecido';
                    error_log("Erro Slack postMessage para '{$dest}': {$erroMsg}");
                }
            }

            return ($sucessos > 0);
        } catch (Exception $e) {
            error_log("Exceção em enviarNotificacaoSlack: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('testarNotificacaoSlack')) {
    /**
     * Realiza um teste de envio de alerta para todos os destinatários cadastrados e detalha os resultados
     */
    function testarNotificacaoSlack($pdo, $mensagemTeste = null) {
        try {
            $stmtConf = $pdo->query("SELECT slack_token, slack_canal_notificacao FROM configuracoes LIMIT 1");
            $config = $stmtConf->fetch();
            if (!$config || empty($config['slack_token'])) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Token do Slack não está configurado.',
                    'total' => 0,
                    'enviados' => 0,
                    'falhas' => 0,
                    'detalhes' => []
                ];
            }

            $token = $config['slack_token'];
            $destinatarios = obterDestinatariosSlack($config['slack_canal_notificacao'] ?? '');

            if (empty($destinatarios)) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Nenhum destinatário (canal ou ID de membro) configurado.',
                    'total' => 0,
                    'enviados' => 0,
                    'falhas' => 0,
                    'detalhes' => []
                ];
            }

            if (empty($mensagemTeste)) {
                $agora = date('d/m/Y H:i:s');
                $mensagemTeste = "🧪 *Teste de Alerta do Bot Slack*\n"
                    . "Esta é uma mensagem de teste enviada pelo sistema de Gestão de Contas & Apps em *{$agora}*.\n"
                    . "Se você recebeu este alerta, suas notificações dos aplicativos estão ativas e funcionando perfeitamente! 🚀";
            }

            $detalhes = [];
            $enviados = 0;
            $falhas = 0;

            foreach ($destinatarios as $dest) {
                $ch = curl_init("https://slack.com/api/chat.postMessage");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . $token,
                    "Content-Type: application/json; charset=utf-8"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    "channel" => $dest,
                    "text" => $mensagemTeste
                ]));
                $resRaw = curl_exec($ch);
                curl_close($ch);

                $resJson = json_decode($resRaw, true);
                if ($resJson && isset($resJson['ok']) && $resJson['ok']) {
                    $enviados++;
                    $detalhes[] = [
                        'destinatario' => $dest,
                        'ok' => true,
                        'ts' => $resJson['ts'] ?? null
                    ];
                } else {
                    $falhas++;
                    $erroMsg = $resJson['error'] ?? 'Erro desconhecido na API do Slack';
                    $detalhes[] = [
                        'destinatario' => $dest,
                        'ok' => false,
                        'erro' => $erroMsg
                    ];
                }
            }

            return [
                'sucesso' => ($enviados > 0),
                'mensagem' => "Envio concluído: {$enviados} enviado(s), {$falhas} falha(s).",
                'total' => count($destinatarios),
                'enviados' => $enviados,
                'falhas' => $falhas,
                'detalhes' => $detalhes
            ];
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro interno ao disparar teste: ' . $e->getMessage(),
                'total' => 0,
                'enviados' => 0,
                'falhas' => 0,
                'detalhes' => []
            ];
        }
    }
}

if (!function_exists('obterMembrosSlackWorkspace')) {
    /**
     * Tenta buscar membros do workspace via users.list (se o token tiver permissão users:read)
     */
    function obterMembrosSlackWorkspace($token) {
        if (empty($token)) return [];
        try {
            $ch = curl_init("https://slack.com/api/users.list");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $token,
                "Content-Type: application/json; charset=utf-8"
            ]);
            $resRaw = curl_exec($ch);
            curl_close($ch);
            $resJson = json_decode($resRaw, true);

            if ($resJson && isset($resJson['ok']) && $resJson['ok'] && isset($resJson['members'])) {
                $membros = [];
                foreach ($resJson['members'] as $m) {
                    if (!empty($m['deleted']) || !empty($m['is_bot']) || ($m['id'] ?? '') === 'USLACKBOT') {
                        continue;
                    }
                    $membros[] = [
                        'id' => $m['id'],
                        'name' => $m['name'] ?? '',
                        'real_name' => $m['real_name'] ?? ($m['profile']['real_name'] ?? $m['name']),
                        'display_name' => $m['profile']['display_name'] ?? '',
                        'avatar' => $m['profile']['image_48'] ?? ''
                    ];
                }
                return $membros;
            }
            return [];
        } catch (Exception $e) {
            return [];
        }
    }
}
