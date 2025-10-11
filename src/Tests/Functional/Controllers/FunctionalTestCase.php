<?php

namespace App\Tests\Functional\Controllers;

use App\ApiApplication;
use App\Framework\Database\Database;
use App\Framework\Http\Response;
use App\Framework\Http\TestResponse;
use App\Framework\Migration\MigrationRunner;
use App\Framework\Session\Session;
use App\Models\Site;
use App\Models\User;
use PHPUnit\Framework\TestCase;

abstract class FunctionalTestCase extends TestCase
{
    protected Database $database;
    protected ApiApplication $app;
    protected $siteSlug = '';
    protected int $siteId;
    protected ?User $authenticatedUser = null;
    protected ?string $authToken = null;

    protected function setUp(): void
    {
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

        $this->ensureSiteExists();

        $this->actingAs();

    }

    public static function setUpBeforeClass(): void
    {
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

        $database = Database::getInstance($testConfig);

        // Create application with test database
        new ApiApplication($testConfig, $database);

        $migrationRunner = new MigrationRunner($database, 'migrations');
        $migrationRunner->run();

    }

    protected function ensureSiteExists() {
        $sites = Site::all();
        $site = !$sites->isEmpty() ? $sites->first() : Site::create(['name' => 'Test Site', 'slug' => 'test-site']);
        $this->siteSlug = $site->slug;
        $this->siteId = $site->id;
    }

    /**
     * Authenticate as a test user and get token
     */
    protected function actingAs(?User $user = null): self
    {
        if ($user === null) {
            // Create or get a test user
            $user = User::where('email', 'michaelhamptondesign@yahoo.com')->first();
            if (!$user) {
                $user = User::create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'password' => password_hash('password', PASSWORD_DEFAULT),
                    'site_id' => $this->siteId,
                ]);
            } else {
                $user = new User($user);
            }
        }

        $this->authenticatedUser = $user;

        // Generate a test token (you may need to adjust based on your token generation logic)
        $this->authToken = $this->generateTestToken($user);

        return $this;
    }

    /**
     * Generate a test authentication token
     */
    protected function generateTestToken(User $user): string
    {
        // Option 1: Use your actual token generation logic
        // return $user->createToken('test-token');

        // Option 2: Create a simple test token
        // You'll need a tokens table or similar mechanism
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        // Store token in database (adjust based on your auth implementation)
        $this->database->query(
            "INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, created_at, site_id) 
             VALUES (?, ?, ?, ?, NOW(), ?)",
            ['App\\Models\\User', $user->id, 'test-token', $hashedToken, $this->siteId]
        );

        return $rawToken;
    }

    /**
     * Clear authentication
     */
    protected function unauthenticate(): self
    {
        $this->authenticatedUser = null;
        $this->authToken = null;
        return $this;
    }

    /**
     * Get default headers including auth token if set
     */
    protected function getDefaultHeaders(array $additionalHeaders = []): array
    {
        $headers = $additionalHeaders;

        if ($this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }

        $headers['X-Site-Id'] = $this->siteId;

        return $headers;
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

                if ($table === 'migrations' || $table === 'users') {
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
        return $this->makeRequest('GET', $uri, [], $this->getDefaultHeaders($headers));
    }

    private function generateUrl(string $uri): string
    {
        $siteSlug = $this->siteSlug;

        // Parse URL (works for both absolute and relative URIs)
        $parsed = parse_url($uri);

        // path fallback: if parse_url returned nothing for path (rare), treat whole uri as path
        $path = $parsed['path'] ?? $uri;
        $query = $parsed['query'] ?? '';
        $fragment = $parsed['fragment'] ?? '';

        // Split into segments but only drop empty strings (preserve "0")
        $rawSegments = explode('/', trim($path, '/'));
        $segments = array_values(array_filter($rawSegments, function ($seg) {
            return $seg !== '';
        }));

        // Insert site slug after 'api' or prepend
        if (isset($segments[0]) && $segments[0] === 'api') {
            array_splice($segments, 1, 0, [$siteSlug]);
        } else {
            array_unshift($segments, $siteSlug);
        }

        $newPath = '/' . implode('/', $segments);

        // Rebuild full URL if original had a scheme/host, otherwise return path-based result
        if (isset($parsed['scheme']) || isset($parsed['host'])) {
            $result = '';

            if (isset($parsed['scheme'])) {
                $result .= $parsed['scheme'] . '://';
            }

            if (isset($parsed['user'])) {
                $result .= $parsed['user'];
                if (isset($parsed['pass'])) {
                    $result .= ':' . $parsed['pass'];
                }
                $result .= '@';
            }

            $result .= $parsed['host'] ?? '';

            if (isset($parsed['port'])) {
                $result .= ':' . $parsed['port'];
            }

            $result .= $newPath;
        } else {
            // relative path
            $result = $newPath;
        }

        if ($query !== '') {
            $result .= '?' . $query;
        }
        if ($fragment !== '') {
            $result .= '#' . $fragment;
        }

        return $result;
    }



    protected function getForSite(string $uri, array $headers = []): Response
    {
        return $this->makeRequest('GET', $this->generateUrl($uri), [], $this->getDefaultHeaders($headers));
    }

    protected function postForSite(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }
        $response = $this->makeRequest('POST', $this->generateUrl($uri), $data, $this->getDefaultHeaders($headers), $files);

        return new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    protected function putForSite(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }
        $response = $this->makeRequest('PUT', $this->generateUrl($uri), $data, $this->getDefaultHeaders($headers), $files);

        return new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    protected function deleteForSite(string $uri, array $headers = []): Response
    {
        $response = $this->makeRequest('DELETE', $this->generateUrl($uri), [], $this->getDefaultHeaders($headers));

        return new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    protected function post(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }
        return $this->makeRequest('POST', $uri, $data, $this->getDefaultHeaders($headers), $files);
    }

    protected function put(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }
        return $this->makeRequest('PUT', $uri, $data, $this->getDefaultHeaders($headers), $files);
    }

    protected function patch(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->makeRequest('PATCH', $uri, $data, $this->getDefaultHeaders($headers));
    }

    protected function delete(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->makeRequest('DELETE', $uri, $data, $this->getDefaultHeaders($headers));
    }

    protected function makeRequest(
        string $method,
        string $uri,
        array  $data = [],
        array  $headers = [],
        array  $files = []
    ): Response
    {
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
        // JsonResponse doesn't have getHeader method, so we check the content type differently
        $content = $response->getContent();

        // Try to decode JSON to verify it's valid JSON
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'Response is not valid JSON');
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

    /**
     * Create a temporary file for testing.
     *
     * @param string $relativePath Path relative to temp upload dir, e.g. 'test.jpg'
     * @param string $content File contents
     * @return string Returns the full filesystem path to the temp file
     */
    protected function createTempUploadFile(string $relativePath, string $content = 'dummy content'): string
    {
        $uploadDir = realpath(__DIR__ . '/../../../uploads/uploads_test');

        // Remove any leading slash
        $relativePath = ltrim($relativePath, '/');

        $fullPath = $uploadDir . '/' . $relativePath;

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);

        return $fullPath;
    }

}