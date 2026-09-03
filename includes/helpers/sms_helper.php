<?php
/**
 * Helper para Integração com o serviço HeroSMS
 */

if (!function_exists('gerarNumeroSMS')) {
    function gerarNumeroSMS($apiHerosms, $servico = 'fb', $pais = 73) {
        $urlApi = 'https://hero-sms.com/stubs/handler_api.php';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$urlApi}?action=getNumber&service={$servico}&country={$pais}&operator=any&api_key={$apiHerosms}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $resposta = trim(curl_exec($ch));
        curl_close($ch);
        
        if (strpos($resposta, 'ACCESS_NUMBER') !== false) {
            $partes = explode(':', $resposta);
            return ['sucesso' => true, 'numero' => $partes[2] ?? '', 'id_pedido' => $partes[1] ?? ''];
        }
        return ['sucesso' => false, 'erro' => 'Erro HeroSMS: ' . $resposta];
    }
}

if (!function_exists('receberCodigoSMS')) {
    function receberCodigoSMS($apiHerosms, $id_pedido) {
        $urlApi = 'https://hero-sms.com/stubs/handler_api.php';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$urlApi}?action=getStatus&id={$id_pedido}&api_key={$apiHerosms}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $resposta = trim(curl_exec($ch));
        curl_close($ch);

        if (strpos($resposta, 'STATUS_OK') !== false) {
            $partes = explode(':', $resposta);
            return ['sucesso' => true, 'codigo' => $partes[1] ?? ''];
        }
        return ['sucesso' => false, 'erro' => $resposta];
    }
}
