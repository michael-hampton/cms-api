<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\ApiApplication;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Response;

// -----------------------------
// Initialize application
// -----------------------------
$app = new ApiApplication();

// Allow Angular dev server
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Site-Id"); // Add X-Site-Id
header("Access-Control-Allow-Credentials: true"); // Add this for cookies/auth

// If it's a preflight (OPTIONS) request, return immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// -----------------------------
// Automatically detect request
// -----------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$queryParams = $_GET;
$data = array_merge($queryParams, $data); // include query parameters

// -----------------------------
// Dispatch request and handle errors
// -----------------------------
try {
    $response = $app->handleRequest($method, $path, $data);
    $response->send();
    exit;
} catch (Throwable $e) {

    file_put_contents(__DIR__.'/hit.log', 'mike', FILE_APPEND);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
        'status' => 500,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}
