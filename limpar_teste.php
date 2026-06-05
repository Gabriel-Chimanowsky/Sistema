<?php
require_once "conexao.php";

$mesAtual = date('Y-m');
$pdo->prepare("DELETE FROM slack_listas WHERE mes = ?")->execute([$mesAtual]);

echo "<h3>Registro de teste deletado para o mes {$mesAtual}!</h3>";
echo "<p>Agora, acesse a <a href='index.php'>Pagina Inicial</a> para forcar o bot a criar a lista novamente e enviar o link no seu Slack.</p>";
?>
