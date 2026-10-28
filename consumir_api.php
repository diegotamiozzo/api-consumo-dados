<?php

/**
 * Script de teste da API (consumo local ou remoto).
 */

require_once __DIR__ . '/config.php';
loadEnv(__DIR__ . '/.env');

$apiKeys = configuredApiKeys();
if (count($apiKeys) === 0) {
    fwrite(STDERR, "Configure API_KEY no arquivo .env no formato chave:id_cliente[,id_cliente].\n");
    exit(1);
}

$apiKey = array_key_first($apiKeys);
$clientId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
if ($clientId === false || $clientId === null || $clientId < 1) {
    fwrite(STDERR, "Informe um cliente: php consumir_api.php <id_cliente> [last_data_index]\n");
    exit(1);
}
if (!in_array($clientId, $apiKeys[$apiKey], true)) {
    fwrite(STDERR, "A primeira API key configurada não tem permissão para esse cliente.\n");
    exit(1);
}

$lastDataIndex = $argv[2] ?? '0';
$lastDataIndex = filter_var($lastDataIndex, FILTER_VALIDATE_INT);
if ($lastDataIndex === false || $lastDataIndex < 0) {
    fwrite(STDERR, "Informe um cursor não negativo: php consumir_api.php <id_cliente> [last_data_index]\n");
    exit(1);
}

$baseUrl = getenv('API_BASE_URL') ?: 'http://localhost:8000';
$query = http_build_query([
    'id_cliente' => $clientId,
    'last_data_index' => $lastDataIndex,
]);
$url = rtrim($baseUrl, '/') . '/index.php?' . $query;

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'API-KEY: ' . $apiKey,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    fwrite(STDERR, 'Erro cURL: ' . curl_error($ch) . PHP_EOL);
    exit(1);
}

echo "URL: $url\n";
echo "Código HTTP: $httpCode\n\n";

$decoded = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

    if ($httpCode === 200 && isset($decoded['valor']['last_data_index'])) {
        echo "\n--- Cursor para a próxima consulta ---\n";
        echo 'php consumir_api.php ' . $clientId . ' ' . $decoded['valor']['last_data_index'] . "\n";
    }
} else {
    echo "Resposta bruta:\n$response\n";
}

curl_close($ch);
