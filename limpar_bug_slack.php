<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexao.php';

echo "<h2>Script de Correção do Slack Lists</h2>";

$stmtConf = $pdo->query("SELECT slack_token FROM configuracoes LIMIT 1");
$config = $stmtConf->fetch();
$token = $config['slack_token'] ?? '';

if (!$token) {
    die("Token do Slack não configurado.");
}

$stmtLista = $pdo->query("SELECT list_id, primary_col_id FROM slack_listas WHERE mes = '" . date('Y-m') . "' LIMIT 1");
$lista = $stmtLista->fetch();
if (!$lista) {
    die("Lista do mês atual não encontrada no banco de dados.");
}

$list_id = $lista['list_id'];
$primary_col_id = $lista['primary_col_id'];

$week_title = obterSemanaDoMes(time());
echo "Procurando por: <b>$week_title</b><br><br>";

// 1. Listar todos os itens do Slack List
$ch = curl_init("https://slack.com/api/slackLists.items.list");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $token,
    "Content-Type: application/json; charset=utf-8"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "list_id" => $list_id
]));
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

$items = $res['items'] ?? [];

$weekIdsToDelete = [];
$subItemsToDelete = [];

foreach ($items as $item) {
    if (empty($item['parent_item_id'])) {
        $itemName = '';
        foreach ($item['fields'] as $f) {
            if ($f['key'] === 'name' || $f['column_id'] === $primary_col_id) {
                $itemName = extrairTextoSlackField($f);
                break;
            }
        }
        if ($itemName === $week_title) {
            $weekIdsToDelete[] = $item['id'];
        }
    }
}

foreach ($items as $item) {
    if (!empty($item['parent_item_id']) && in_array($item['parent_item_id'], $weekIdsToDelete)) {
        $subItemsToDelete[] = $item['id'];
    }
}

echo "- Subtarefas identificadas para exclusão: " . count($subItemsToDelete) . "<br>";
echo "- Tarefas semanais identificadas para exclusão: " . count($weekIdsToDelete) . "<br><br>";

$allToDelete = array_merge($subItemsToDelete, $weekIdsToDelete);

// Apagar itens no Slack
foreach ($allToDelete as $delId) {
    $chDel = curl_init("https://slack.com/api/slackLists.items.delete");
    curl_setopt($chDel, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chDel, CURLOPT_POST, true);
    curl_setopt($chDel, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json; charset=utf-8"
    ]);
    curl_setopt($chDel, CURLOPT_POSTFIELDS, json_encode([
        "list_id" => $list_id,
        "id" => $delId
    ]));
    curl_exec($chDel);
    curl_close($chDel);
}

if (count($allToDelete) > 0) {
    echo "Itens duplicados deletados do Slack com sucesso!<br><br>";
} else {
    echo "Nenhum item encontrado no Slack para deletar. Avançando...<br><br>";
}

// 2. Extrair a data para o reset
preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $week_title, $matches);
if (count($matches) == 4) {
    $dataInicio = "{$matches[3]}-{$matches[2]}-{$matches[1]} 00:00:00";
    
    $stmtMin = $pdo->query("SELECT MIN(id) FROM contas WHERE data_criacao >= '$dataInicio' OR data_bm_criada >= '$dataInicio'");
    $minId = $stmtMin->fetchColumn();
    
    if ($minId) {
        $pdo->query("UPDATE contas SET slack_perfil_sync = 0, slack_bm_sync = 0 WHERE id >= $minId");
        echo "Reset do sincronismo no Banco de Dados concluído (para contas a partir de ID $minId).<br><br>";
    } else {
        echo "Nenhuma conta criada nesta semana para ser resetada.<br><br>";
    }
}

// 3. Rodar a sincronização até não sobrar mais lotes pendentes
echo "Recriando a Semana e reagrupando subtarefas no Slack...<br>";
$syncCount = 0;
for ($i = 0; $i < 50; $i++) {
    sincronizarSlackTracker($pdo);
    
    $c = $pdo->query("SELECT count(*) FROM contas WHERE status IN ('criada', 'autenticada', 'exportado') AND slack_perfil_sync = 0")->fetchColumn();
    $b = $pdo->query("SELECT count(*) FROM contas WHERE bm_criada = 1 AND slack_bm_sync = 0")->fetchColumn();
    
    if ($c < 50 && $b < 50) {
        break;
    }
    $syncCount++;
}

echo "<b>Finalizado!</b> A semana foi reconstruída no Slack List e todos os $syncCount lotes foram adicionados corretamente, somando os lotes como deve ser.";
?>
