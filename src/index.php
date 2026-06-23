<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\ApiApplication;
use App\Framework\Http\Response;

$app = new ApiApplication();

header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Site-Id');
header('Access-Control-Allow-Credentials: true');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$data = array_merge($_GET, $data);

if ($method === 'GET' && $path === '/robots.txt') {
    (new Response("User-agent: *\nAllow: /\n", 200, [
        'Content-Type' => 'text/plain; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ]))->send();
    exit;
}

try {
    $response = $app->handleRequest($method, $path, $data);

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $expectsJson = str_starts_with($path, '/api/')
        || str_contains($accept, 'application/json')
        || $requestedWith === 'xmlhttprequest';

    if ($response->getStatusCode() === 404 && !$expectsJson) {
        $response = Response::view('errors/404')->setStatusCode(404);
    }

    $response->send();
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
        'status' => 500,
        'timestamp' => date('c'),
    ], JSON_PRETTY_PRINT);
}
