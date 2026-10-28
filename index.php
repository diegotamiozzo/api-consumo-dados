<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
loadEnv(__DIR__ . '/.env');

// Bloco de teste (CLI) - Simula a requisição para rodar no terminal
if (php_sapi_name() === 'cli') {
    $configuredKeys = configuredApiKeys();
    if (!isset($_SERVER['HTTP_API_KEY']) && count($configuredKeys) > 0) {
        $_SERVER['HTTP_API_KEY'] = array_key_first($configuredKeys);
    }
    $_GET['last_data_index'] = $_GET['last_data_index'] ?? 0;
    if (!isset($_GET['id_cliente']) && count($configuredKeys) > 0) {
        $_GET['id_cliente'] = $configuredKeys[array_key_first($configuredKeys)][0] ?? null;
    }
}

function resolveDataTable(PDO $pdo, int $clientId): string
{
    $suffix = '_' . str_pad((string)$clientId, 2, '0', STR_PAD_LEFT) . '_data';
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_TYPE = 'BASE TABLE'
           AND RIGHT(TABLE_NAME, CHAR_LENGTH(?)) = ?"
    );
    $stmt->execute([$suffix, $suffix]);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($tables) !== 1) {
        throw new RuntimeException(
            count($tables) === 0
                ? "Nenhuma tabela encontrada para o cliente {$clientId}."
                : "Mais de uma tabela encontrada para o cliente {$clientId}."
        );
    }

    return $tables[0];
}

function buscarDados(PDO $pdo, int $clientId, int $lastDataIndex, int $limit): array
{
    $table = resolveDataTable($pdo, $clientId);
    $quotedTable = '`' . str_replace('`', '``', $table) . '`';
    $sql = "SELECT `data_index`, `data_format_0`, `time@timestamp` AS `timestamp`
            FROM {$quotedTable}
            WHERE `data_index` > ?
            ORDER BY `data_index` ASC
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $lastDataIndex, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    $data = [];
    $newLastDataIndex = $lastDataIndex;
    while ($row = $stmt->fetch()) {
        $dataIndex = (int)$row['data_index'];
        $data[] = [
            'data_index' => $dataIndex,
            'id_cliente' => $clientId,
            'data_format_0' => $row['data_format_0'] === null
                ? null
                : (float)$row['data_format_0'], // Ajuste de escala para o valor real, divisão por 10 se necessário
            'time@timestamp' => $row['timestamp'] === null
                ? null
                : (float)$row['timestamp'],
        ];
        $newLastDataIndex = max($newLastDataIndex, $dataIndex);
    }

    return [
        'last_data_index' => $newLastDataIndex,
        'data' => $data,
    ];
}

// 1. Validação de autenticação pela API key
$headers = function_exists('getallheaders') ? getallheaders() : [];
$headerApiKey = '';
foreach ($headers as $name => $value) {
    if (strcasecmp($name, 'API-KEY') === 0) {
        $headerApiKey = $value;
        break;
    }
}
$apiKey = $_SERVER['HTTP_API_KEY'] ?? ($_SERVER['HTTP_APIKEY'] ?? $headerApiKey);
$apiKey = $apiKey ?? '';
$authorizedClients = configuredApiKeys();

if (count($authorizedClients) === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuração de API key inválida.']);
    exit;
}

if (!isset($authorizedClients[$apiKey])) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado.']);
    exit;
}

$clientId = filter_var($_GET['id_cliente'] ?? null, FILTER_VALIDATE_INT);
if ($clientId === false || $clientId === null || $clientId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'O parâmetro id_cliente deve ser um inteiro positivo.']);
    exit;
}

if (!in_array($clientId, $authorizedClients[$apiKey], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'A API key não tem permissão para acessar este cliente.']);
    exit;
}

$lastDataIndex = filter_var($_GET['last_data_index'] ?? 0, FILTER_VALIDATE_INT);
if ($lastDataIndex === false || $lastDataIndex < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'O parâmetro last_data_index deve ser um inteiro não negativo.']);
    exit;
}

$configuredLimit = getenv('QUERY_LIMIT');
$limit = $configuredLimit === false || $configuredLimit === ''
    ? 100
    : filter_var($configuredLimit, FILTER_VALIDATE_INT);
if ($limit === false || $limit < 1) {
    http_response_code(500);
    echo json_encode(['error' => 'QUERY_LIMIT deve ser um inteiro positivo.']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $result = buscarDados($pdo, $clientId, $lastDataIndex, $limit);
    echo json_encode(['valor' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    error_log('[api_dados] Falha na conexão/consulta: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor.']);
} catch (RuntimeException $e) {
    error_log('[api_dados] Falha ao localizar tabela: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível localizar a tabela de dados do cliente.']);
}