<?php

declare(strict_types=1);

$dir = getenv('PAYRIFF_MOCK_DIR');
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

file_put_contents($dir . '/requests.jsonl', json_encode([
    'method' => $method,
    'url' => $uri,
    'headers' => array_change_key_case(getallheaders(), CASE_LOWER),
    'body' => base64_encode((string) file_get_contents('php://input')),
]) . "\n", FILE_APPEND | LOCK_EX);

$stubs = json_decode((string) @file_get_contents($dir . '/stubs.json'), true) ?: [];
$stub = $stubs["$method $uri"] ?? $stubs[$method . ' ' . strtok($uri, '?')] ?? null;
if ($stub === null) {
    http_response_code(404);
    return true;
}
http_response_code($stub['status']);
header('Content-Type: application/json');
foreach ($stub['headers'] as $name => $value) {
    header("$name: $value");
}
echo base64_decode($stub['body']);
return true;