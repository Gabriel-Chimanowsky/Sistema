<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexao.php';

echo "<h2>Recriação da Lista no Slack</h2>";

// 1. Obter token e domínio
$stmtConf = $pdo->query("SELECT slack_token, email_dominio FROM configuracoes LIMIT 1");
$config = $stmtConf->fetch();
$token = $config['slack_token'] ?? '';

if (!$token) {
    die("Token do Slack não configurado.");
}

$dominioEmail = $config['email_dominio'] ?? '';
$dominioLimpo = ltrim($dominioEmail, '@');
$nomeDominio = strtolower(explode('.', $dominioLimpo)[0]);
if (empty($nomeDominio)) $nomeDominio = "dollfinn";

// 2. Criar nova lista
$meses = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
$mesNum = date('m');
$ano = date('Y');
$nomeMes = $meses[$mesNum] ?? 'Mês';
$list_name = "Gestão - {$nomeMes} {$ano} (Corrigida)";

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
$resJson = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$resJson || !isset($resJson['ok']) || !$resJson['ok']) {
    die("Erro ao criar nova lista no Slack: " . print_r($resJson, true));
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

// Atualiza o BD para usar a nova lista daqui pra frente
$mesAtual = date('Y-m');
$stmtUpdateLista = $pdo->prepare("UPDATE slack_listas SET list_id = ?, primary_col_id = ? WHERE mes = ?");
$stmtUpdateLista->execute([$list_id, $primary_col_id, $mesAtual]);

echo "✅ Nova lista criada com sucesso! ID: $list_id<br>";

// Funções de ajuda para interagir com o Slack
$week_rows = [];
function getWeekRowId($week_title, $list_id, $primary_col_id, $token, &$week_rows) {
    if (isset($week_rows[$week_title])) return $week_rows[$week_title];
    
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
    $res = json_decode(curl_exec($chNewWeek), true);
    curl_close($chNewWeek);
    $id = $res['item']['id'] ?? $res['id'];
    $week_rows[$week_title] = $id;
    return $id;
}

function createLote($list_id, $week_row_id, $text, $date, $primary_col_id, $token) {
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
                "rich_text" => buildRichText($text)
            ],
            [
                "column_id" => "Col00",
                "checkbox" => true
            ],
            [
                "column_id" => "Col02",
                "date" => [$date]
            ]
        ]
    ]));
    curl_exec($chSub);
    curl_close($chSub);
}

// 3. Resetar status de sync deste mês no banco
$pdo->query("UPDATE contas SET slack_perfil_sync = 0 WHERE data_criacao LIKE '{$mesAtual}-%'");
$pdo->query("UPDATE contas SET slack_bm_sync = 0 WHERE data_bm_criada LIKE '{$mesAtual}-%'");
echo "✅ Status de sincronização resetado no banco.<br>";

// 4. Processar Perfis
$contas = $pdo->query("SELECT id, data_criacao FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND data_criacao LIKE '{$mesAtual}-%' ORDER BY id ASC")->fetchAll();
$total = count($contas);
echo "<br><b>Recriando Perfis ($total contas do mês de $nomeMes)...</b><br>";

$loteCounts = [];
for ($i = 0; $i < floor($total / 50); $i++) {
    $batch = array_slice($contas, $i * 50, 50);
    $lastItem = end($batch);
    $time = strtotime($lastItem['data_criacao']);
    $week_title = obterSemanaDoMes($time);
    
    if (!isset($loteCounts[$week_title])) $loteCounts[$week_title] = 0;
    
    $start = $loteCounts[$week_title] * 50;
    $end = ($loteCounts[$week_title] + 1) * 50;
    $loteText = "{$start} - {$end} perfis {$nomeDominio}";
    
    $week_id = getWeekRowId($week_title, $list_id, $primary_col_id, $token, $week_rows);
    createLote($list_id, $week_id, $loteText, date('Y-m-d', $time), $primary_col_id, $token);
    echo "  - Lote $loteText inserido em '$week_title'<br>";
    
    $loteCounts[$week_title]++;
    
    // Atualiza apenas as contas deste lote como syncadas
    $ids = array_column($batch, 'id');
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $pdo->prepare("UPDATE contas SET slack_perfil_sync = 1 WHERE id IN ($in)")->execute($ids);
}

// 5. Processar BMs
$bms = $pdo->query("SELECT id, data_bm_criada FROM contas WHERE bm_criada = 1 AND data_bm_criada LIKE '{$mesAtual}-%' ORDER BY data_bm_criada ASC")->fetchAll();
$totalBms = count($bms);
echo "<br><b>Recriando BMs ($totalBms BMs do mês de $nomeMes)...</b><br>";

$loteCountsBm = [];
for ($i = 0; $i < floor($totalBms / 50); $i++) {
    $batch = array_slice($bms, $i * 50, 50);
    $lastItem = end($batch);
    $time = strtotime($lastItem['data_bm_criada']);
    $week_title = obterSemanaDoMes($time);
    
    if (!isset($loteCountsBm[$week_title])) $loteCountsBm[$week_title] = 0;
    
    $start = $loteCountsBm[$week_title] * 50;
    $end = ($loteCountsBm[$week_title] + 1) * 50;
    $loteText = "{$start} - {$end} BMs {$nomeDominio}";
    
    $week_id = getWeekRowId($week_title, $list_id, $primary_col_id, $token, $week_rows);
    createLote($list_id, $week_id, $loteText, date('Y-m-d', $time), $primary_col_id, $token);
    echo "  - Lote $loteText inserido em '$week_title'<br>";
    
    $loteCountsBm[$week_title]++;
    
    $ids = array_column($batch, 'id');
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $pdo->prepare("UPDATE contas SET slack_bm_sync = 1 WHERE id IN ($in)")->execute($ids);
}

echo "<h2>Reconstrução Completa!</h2>";
echo "<p>Você já pode acessar o Slack e conferir a nova lista chamada <b>$list_name</b>.</p>";
echo "<p>Nota: A lista antiga não foi deletada pelo sistema por segurança para que você não perca nada se houver problemas. Agora que a nova está pronta e correta, você pode deletar a lista com problemas direto no Slack.</p>";
?>
