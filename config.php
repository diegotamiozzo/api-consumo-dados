<?php

function loadEnv(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . trim($value));
        }
    }
}

function configuredApiKeys(): array
{
    $config = getenv('API_KEY');
    if ($config === false || trim($config) === '') {
        $config = getenv('API_KEYS') ?: '';
    }

    $apiKeys = [];
    foreach (explode('|', $config) as $entry) {
        $parts = explode(':', trim($entry), 2);
        if (count($parts) !== 2 || trim($parts[0]) === '') {
            continue;
        }

        $apiKey = trim($parts[0]);
        foreach (explode(',', $parts[1]) as $configuredClientId) {
            $clientId = filter_var(trim($configuredClientId), FILTER_VALIDATE_INT);
            if ($clientId !== false && $clientId > 0) {
                $apiKeys[$apiKey][] = $clientId;
            }
        }
    }

    foreach ($apiKeys as &$clientIds) {
        $clientIds = array_values(array_unique($clientIds));
    }
    unset($clientIds);

    return $apiKeys;
}
