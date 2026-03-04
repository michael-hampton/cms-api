#!/usr/bin/env php
<?php

/**
 * OpenAPI 3.0 Generator
 *
 * Introspects route files, controller methods, and FormRequest classes
 * to generate a complete openapi.json — zero controller changes required.
 *
 * Usage:
 *   php generate.php [--src=../] [--out=openapi.json] [--base-url=https://api.example.com]
 *                    [--title="My API"] [--version=1.0.0] [--description="..."]
 */

declare(strict_types=1);


require_once __DIR__ . '/src/OpenApiGenerator/Reflector.php';
require_once __DIR__ . '/src/OpenApiGenerator/RouteParser.php';
require_once __DIR__ . '/src/OpenApiGenerator/RequestAnalyzer.php';
require_once __DIR__ . '/src/OpenApiGenerator/ResponseInferer.php';
require_once __DIR__ . '/src/OpenApiGenerator/SchemaBuilder.php';
require_once __DIR__ . '/src/OpenApiGenerator/OpenApiBuilder.php';

use App\OpenApiGenerator\OpenApiBuilder;
use App\OpenApiGenerator\Reflector;
use App\OpenApiGenerator\RequestAnalyzer;
use App\OpenApiGenerator\ResponseInferer;
use App\OpenApiGenerator\RouteParser;
use App\OpenApiGenerator\SchemaBuilder;

// ── CLI args ──────────────────────────────────────────────────────────────────

$opts = getopt('', ['src:', 'out:', 'base-url:', 'title:', 'version:', 'description:']);


$srcPath = $opts['src'] ?? realpath(__DIR__ . '/src');
$outFile = $opts['out'] ?? __DIR__ . '/openapi.json';
$baseUrl = $opts['base-url'] ?? 'http://localhost:5001';
$title = $opts['title'] ?? 'API Documentation';
$version = $opts['version'] ?? '1.0.0';
$description = $opts['description'] ?? '';

if (!$srcPath || !is_dir($srcPath)) {
    fwrite(STDERR, "Error: Source path not found: {$srcPath}\n");
    fwrite(STDERR, "Usage: php generate.php --src=/path/to/project --out=openapi.json\n");
    exit(1);
}

echo "🔍 Scanning source: {$srcPath}\n";

// ── Bootstrap ─────────────────────────────────────────────────────────────────

$reflector = new Reflector($srcPath);
$routeParser = new RouteParser($srcPath, $reflector);
$requestAnalyzer = new RequestAnalyzer($reflector);
$responseInferer = new ResponseInferer($reflector);
$schemaBuilder = new SchemaBuilder();
$builder = new OpenApiBuilder($reflector, $requestAnalyzer, $responseInferer, $schemaBuilder);

// ── Parse routes ──────────────────────────────────────────────────────────────

echo "📡 Parsing routes...\n";

$routes = $routeParser->parse();

echo "   Found " . count($routes) . " routes\n";

if (empty($routes)) {
    fwrite(STDERR, "Warning: No routes found. Ensure --src points to your project root.\n");
}

// ── Build spec ────────────────────────────────────────────────────────────────

echo "🏗  Building OpenAPI spec...\n";

$config = [
        'title' => $title,
        'version' => $version,
        'description' => $description,
        'base_url' => $baseUrl,
];

$spec = $builder->build($routes, $config);

// ── Write output ──────────────────────────────────────────────────────────────

$json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    fwrite(STDERR, "Error: JSON encoding failed: " . json_last_error_msg() . "\n");
    exit(1);
}

if (file_put_contents($outFile, $json) === false) {
    fwrite(STDERR, "Error: Cannot write to {$outFile}\n");
    exit(1);
}

$pathCount = count($spec['paths'] ?? []);
$opCount = array_sum(array_map('count', array_values($spec['paths'] ?? [])));
$schemaCount = count($spec['components']['schemas'] ?? []);

echo "✅ Generated: {$outFile}\n";
echo "   Paths:      {$pathCount}\n";
echo "   Operations: {$opCount}\n";
echo "   Schemas:    {$schemaCount}\n";