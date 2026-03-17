<?php

/**
 * Compare API responses across two base URLs and report shape differences.
 *
 * Usage:
 *  php scripts/compare_api.php --base-a=http://localhost:8000 --base-b=http://localhost:8001 \
 *    --endpoints=scripts/endpoints.json --token="Bearer <token>"
 */

declare(strict_types=1);

$options = getopt('', [
    'base-a:',
    'base-b:',
    'endpoints::',
    'token::',
    'header::',
    'timeout::',
]);

$baseA = rtrim((string)($options['base-a'] ?? ''), '/');
$baseB = rtrim((string)($options['base-b'] ?? ''), '/');
$endpointsPath = $options['endpoints'] ?? __DIR__ . '/endpoints.json';
$timeout = isset($options['timeout']) ? (int)$options['timeout'] : 20;
$token = $options['token'] ?? null;
$extraHeaders = $options['header'] ?? [];

if (!is_array($extraHeaders)) {
    $extraHeaders = [$extraHeaders];
}

if ($baseA === '' || $baseB === '') {
    fwrite(STDERR, "Missing required --base-a or --base-b options.\n");
    exit(1);
}

if (!file_exists($endpointsPath)) {
    fwrite(STDERR, "Endpoints file not found: {$endpointsPath}\n");
    exit(1);
}

$rawConfig = file_get_contents($endpointsPath);
$config = json_decode($rawConfig ?: '', true);
if (!is_array($config) || !isset($config['endpoints']) || !is_array($config['endpoints'])) {
    fwrite(STDERR, "Invalid endpoints config. Expecting { \"endpoints\": [...] }.\n");
    exit(1);
}

$globalHeaders = [];
if ($token) {
    $globalHeaders[] = 'Authorization: ' . $token;
}
foreach ($extraHeaders as $header) {
    if (is_string($header) && trim($header) !== '') {
        $globalHeaders[] = $header;
    }
}

$results = [];
foreach ($config['endpoints'] as $endpoint) {
    $name = $endpoint['name'] ?? ($endpoint['path'] ?? 'unknown');
    $method = strtoupper((string)($endpoint['method'] ?? 'GET'));
    $path = (string)($endpoint['path'] ?? '/');
    $query = $endpoint['query'] ?? null;
    $body = $endpoint['body'] ?? null;
    $headers = $endpoint['headers'] ?? [];
    $expectStatus = $endpoint['expectStatus'] ?? null;

    $headersList = $globalHeaders;
    foreach ($headers as $key => $value) {
        $headersList[] = "{$key}: {$value}";
    }

    $urlA = buildUrl($baseA, $path, $query);
    $urlB = buildUrl($baseB, $path, $query);

    $responseA = request($method, $urlA, $headersList, $body, $timeout);
    $responseB = request($method, $urlB, $headersList, $body, $timeout);

    $results[] = [
        'name' => $name,
        'method' => $method,
        'path' => $path,
        'responseA' => $responseA,
        'responseB' => $responseB,
        'expectStatus' => $expectStatus,
    ];
}

foreach ($results as $result) {
    $name = $result['name'];
    $responseA = $result['responseA'];
    $responseB = $result['responseB'];

    echo "\n=== {$name} ({$result['method']} {$result['path']}) ===\n";
    echo "A: {$responseA['status']} | B: {$responseB['status']}\n";

    if ($result['expectStatus'] !== null) {
        $expected = (int)$result['expectStatus'];
        $statusNoteA = $responseA['status'] === $expected ? 'OK' : "Expected {$expected}";
        $statusNoteB = $responseB['status'] === $expected ? 'OK' : "Expected {$expected}";
        echo "Expected {$expected} -> A: {$statusNoteA}, B: {$statusNoteB}\n";
    }

    $jsonA = decodeJson($responseA['body']);
    $jsonB = decodeJson($responseB['body']);

    if ($jsonA['error'] || $jsonB['error']) {
        echo "JSON parse error. A: {$jsonA['error']}; B: {$jsonB['error']}\n";
        continue;
    }

    $shapeA = buildShapeMap($jsonA['data']);
    $shapeB = buildShapeMap($jsonB['data']);

    $diff = diffShapes($shapeA, $shapeB);

    if ($diff['missingInA'] || $diff['missingInB'] || $diff['typeMismatches']) {
        if ($diff['missingInA']) {
            echo "Missing in A:\n";
            foreach ($diff['missingInA'] as $path) {
                echo "  - {$path}\n";
            }
        }
        if ($diff['missingInB']) {
            echo "Missing in B:\n";
            foreach ($diff['missingInB'] as $path) {
                echo "  - {$path}\n";
            }
        }
        if ($diff['typeMismatches']) {
            echo "Type mismatches:\n";
            foreach ($diff['typeMismatches'] as $mismatch) {
                echo "  - {$mismatch}\n";
            }
        }
    } else {
        echo "Shapes match.\n";
    }
}

function buildUrl(string $base, string $path, mixed $query): string
{
    $url = rtrim($base, '/') . '/' . ltrim($path, '/');
    if (is_array($query) && !empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function request(string $method, string $url, array $headers, mixed $body, int $timeout): array
{
    $ch = curl_init();

    $payload = null;
    if ($body !== null) {
        if (is_array($body)) {
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        } else {
            $payload = (string) $body;
        }
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $bodyResponse = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($bodyResponse === false) {
        $bodyResponse = json_encode(['error' => curl_error($ch)]);
    }

    // curl_close() is a no-op since PHP 8.0; suppress deprecation on 8.5+
    @curl_close($ch);

    return [
        'status' => $status,
        'body' => $bodyResponse,
    ];
}

function decodeJson(string $payload): array
{
    $data = json_decode($payload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => json_last_error_msg(),
            'data' => null,
        ];
    }

    return [
        'error' => null,
        'data' => $data,
    ];
}

function buildShapeMap(mixed $data, string $prefix = ''): array
{
    $shape = [];
    $type = getTypeLabel($data);
    $shape[$prefix === '' ? '$' : $prefix] = $type;

    if (is_array($data)) {
        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssoc) {
            foreach ($data as $key => $value) {
                $path = $prefix === '' ? "$.{$key}" : "{$prefix}.{$key}";
                $shape = array_merge($shape, buildShapeMap($value, $path));
            }
        } else {
            foreach ($data as $index => $value) {
                $path = $prefix === '' ? '$[*]' : "{$prefix}[*]";
                $shape = array_merge($shape, buildShapeMap($value, $path));
            }
        }
    }

    return $shape;
}

function getTypeLabel(mixed $value): string
{
    return match (true) {
        is_array($value) => 'array',
        is_string($value) => 'string',
        is_int($value) => 'integer',
        is_float($value) => 'float',
        is_bool($value) => 'boolean',
        is_null($value) => 'null',
        default => 'object',
    };
}

function diffShapes(array $shapeA, array $shapeB): array
{
    $missingInA = array_values(array_diff(array_keys($shapeB), array_keys($shapeA)));
    $missingInB = array_values(array_diff(array_keys($shapeA), array_keys($shapeB)));

    $typeMismatches = [];
    foreach ($shapeA as $path => $type) {
        if (isset($shapeB[$path]) && $shapeB[$path] !== $type) {
            $typeMismatches[] = "{$path}: {$type} vs {$shapeB[$path]}";
        }
    }

    sort($missingInA);
    sort($missingInB);
    sort($typeMismatches);

    return [
        'missingInA' => $missingInA,
        'missingInB' => $missingInB,
        'typeMismatches' => $typeMismatches,
    ];
}
