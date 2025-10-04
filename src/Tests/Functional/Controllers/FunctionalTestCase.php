<?php

namespace App\Tests\Functional\Controllers;

use App\ApiApplication;
use App\Framework\Database\Database;
use App\Framework\Http\Response;
use App\Framework\Migration\MigrationRunner;
use PHPUnit\Framework\TestCase;

abstract class FunctionalTestCase extends TestCase
{
    protected Database $database;
    protected ApiApplication $app;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing'); // optional, for functions using getenv()

        // Use test database configuration
        $testConfig = [
            'driver' => 'mysql',
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'test_db',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASS') ?: 'rootsecret',
            'charset' => 'utf8mb4',
        ];

        $this->database = Database::getInstance($testConfig);


        // Create application with test database
        $this->app = new ApiApplication($testConfig, $this->database);
    }

    protected function tearDown(): void
    {
        $this->cleanupDatabase();
        parent::tearDown();
    }

    protected function runMigrations(): void
    {
        $migrationRunner = new MigrationRunner($this->database, 'migrations');
        $migrationRunner->run();
    }

    protected function cleanupDatabase(): void
    {
        try {
            // Get all tables
            $stmt = $this->database->query("SHOW TABLES");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($tables)) {
                return;
            }

            // Disable foreign key checks
            $this->database->query('SET FOREIGN_KEY_CHECKS = 0');

            // Truncate all tables
            foreach ($tables as $table) {

                if ($table === 'migrations') {
                    continue;
                }

                $this->database->query("TRUNCATE TABLE `$table`");
            }

            // Re-enable foreign key checks
            $this->database->query('SET FOREIGN_KEY_CHECKS = 1');

        } catch (\Exception $e) {
            // Silently fail on cleanup - tests may have already cleaned up
        }
    }

    protected function get(string $uri, array $headers = []): Response
    {
        return $this->makeRequest('GET', $uri, [], $headers);
    }

    protected function post(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }
        return $this->makeRequest('POST', $uri, $data, $headers, $files);
    }

    protected function put(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }
        return $this->makeRequest('PUT', $uri, $data, $headers, $files);
    }

    protected function patch(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->makeRequest('PATCH', $uri, $data, $headers);
    }

    protected function delete(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->makeRequest('DELETE', $uri, $data, $headers);
    }

    protected function makeRequest(
        string $method,
        string $uri,
        array $data = [],
        array $headers = [],
        array $files = []
    ): Response {
        $parsedUri = parse_url($uri);
        $path = $parsedUri['path'] ?? $uri;
        $queryData = [];

        if (isset($parsedUri['query'])) {
            parse_str($parsedUri['query'], $queryData);
        }

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $_GET = $queryData;
        $_POST = [];
        $_FILES = [];

        $contentType = $headers['Content-Type'] ?? '';

        if ($method === 'GET') {
            $_GET = array_merge($_GET, $data);
        } else {
            // Treat PUT like POST for testing purposes
            if (!empty($files)) {
                foreach ($files as $key => &$file) {
                    if (!isset($file['tmp_name'])) {
                        $tmp = tempnam(sys_get_temp_dir(), 'test_upload_');
                        file_put_contents($tmp, $file['content'] ?? '');
                        $file['tmp_name'] = $tmp;
                        $file['error'] = $file['error'] ?? UPLOAD_ERR_OK;
                        $file['size'] = $file['size'] ?? strlen($file['content'] ?? '');
                    }
                }

                $_POST = $data;
                $_FILES = $files;
                $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
            } elseif ($contentType === 'application/json') {
                $GLOBALS['__test_request_body'] = json_encode($data);
                $contentType = 'application/json';
            } else {
                $_POST = $data;
                $contentType = 'application/x-www-form-urlencoded';
            }
        }

        $_SERVER['CONTENT_TYPE'] = $contentType;

        // Set HTTP headers
        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        try {
            // Pass $data and $files so Request can wrap UploadedFile
            return $this->app->handleRequest($method, $path, $data, $files);
        } finally {
            unset($GLOBALS['__test_request_body']);
        }
    }




    protected function createUploadedFile(string $filename, string $mimeType): array
    {
        // Minimal valid PNG image
        $imageData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAuMB9QFzM0IAAAAASUVORK5CYII='
        );

        // Create temporary file
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tmpFile, $imageData);

        return [
            'name' => $filename,
            'type' => $mimeType,
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile)
        ];
    }


    protected function assertResponseOk(Response $response): void
    {
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            $statusCode >= 200 && $statusCode < 300,
            "Expected successful response, got {$statusCode}"
        );
    }

    protected function assertResponseStatus(int $expected, Response $response): void
    {
        $this->assertEquals(
            $expected,
            $response->getStatusCode(),
            "Expected status {$expected}, got {$response->getStatusCode()}"
        );
    }

    protected function assertJsonResponse(Response $response): void
    {
        $contentType = $response->getHeader('Content-Type');
        $this->assertStringContainsString('application/json', $contentType ?? '');
    }

    protected function assertJsonStructure(array $structure, array $data): void
    {
        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                $this->assertArrayHasKey($key, $data);
                $this->assertJsonStructure($value, $data[$key]);
            } else {
                $this->assertArrayHasKey($value, $data);
            }
        }
    }

    protected function seed(string $seederClass): void
    {
        $seeder = new $seederClass($this->database);
        $seeder->run();
    }

    protected function json(string $method, string $uri, array $data = []): Response
    {
        return $this->makeRequest($method, $uri, $data, ['Content-Type' => 'application/json']);
    }

    protected function getJson(string $uri): Response
    {
        return $this->json('GET', $uri);
    }

    protected function postJson(string $uri, array $data = []): Response
    {
        return $this->json('POST', $uri, $data);
    }

    protected function putJson(string $uri, array $data = []): Response
    {
        return $this->json('PUT', $uri, $data);
    }

    protected function patchJson(string $uri, array $data = []): Response
    {
        return $this->json('PATCH', $uri, $data);
    }

    protected function deleteJson(string $uri, array $data = []): Response
    {
        return $this->json('DELETE', $uri, $data);
    }
}