<?php

namespace App\Tests\Functional\Controllers;

use App\ApiApplication;
use App\Framework\Authorization\Auth;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Http\Response;
use App\Framework\Http\TestResponse;
use App\Framework\HttpClient\HttpClient;
use App\Framework\HttpClient\HttpClientResponse;
use App\Framework\Mail\ArrayMailer;
use App\Framework\Migration\MigrationRunner;
use App\Framework\Session\Session;
use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Config;
use App\Models\Member;
use App\Models\Model;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\OpenCollabSiteUserRole;
use App\Models\ReplacementPolicy;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSite;
use App\Repositories\OpenCollab\RbacRepository;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Tests\Support\TestDatabase;
use App\Tests\Support\TestPassword;
use Exception;
use Mockery;
use PDO;
use PHPUnit\Framework\TestCase;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;

abstract class FunctionalTestCase extends TestCase
{
    protected static array $httpMocks = [];
    protected Database $database;
    protected ApiApplication $app;
    protected $siteSlug = '';
    protected int $siteId;
    protected ?User $authenticatedUser = null;
    protected ?string $authToken = null;
    protected mixed $authenticatedMemberUser;
    private int $currentUserId;
    protected string $memberAuthToken;

    /**
     * When true, tearDown truncates all tables instead of rolling back.
     * Default is transactional rollback (fast). Seed helpers force site/user
     * id=1 so hard-coded FK fixtures keep working without AUTO_INCREMENT reset.
     * Use truncation only for tests that issue DDL / TRUNCATE mid-test.
     */
    protected bool $usesDatabaseTruncation = false;

    /**
     * When false, setUp skips actingAs() (repository/service suites that never
     * hit HTTP auth). Saves per-test token inserts.
     */
    protected bool $authenticateDefaultUser = true;

    private bool $testTransactionOpen = false;

    /** True after this test instance has prepared site RBAC (site roles / config). */
    private bool $siteRbacSeededForTest = false;

    public static function setUpBeforeClass(): void
    {
        // IDE runners often default to 128M and ignore phpunit.xml <ini>;
        // functional suites need more headroom once prior classes have run.
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        // Flush leftover container bindings (e.g. Mockery EventDispatcher)
        // from prior test classes before anything else touches the container.
        Container::getInstance()->flush();
        Cache::flush();

        $database = TestDatabase::connect(reuseExisting: false);

        // Migrations only need Database — do NOT boot ApiApplication here.
        // Booting the app loads the full route table; setUp() already boots
        // a fresh app per test, and a second load in setUpBeforeClass OOMs
        // under suite memory pressure.
        $migrationRunner = new MigrationRunner($database, 'migrations');
        $migrationRunner->run();

        // Seed catalogues once outside per-test transactions. Re-inserting them
        // inside every setUp() under concurrent suite pressure caused InnoDB
        // deadlocks (1213) on cancellation_reasons / refund_reasons.
        // Reasons alone are not enough — CRM refund/cancel flows also need the
        // matching default Business Decision (seeders create both).
        if (\App\Models\CancellationReason::query()->first() === null
            || !self::hasDefaultBusinessDecision(\App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum::CANCELLATIONS)
        ) {
            (new \App\Database\Seeders\CancellationReasonSeeder())->run();
        }
        if (\App\Models\RefundReason::query()->first() === null
            || !self::hasDefaultBusinessDecision(\App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum::REFUNDS)
        ) {
            (new \App\Database\Seeders\RefundReasonSeeder())->run();
        }

        // Site/user id=1 must survive transactional rollback. Reseeding them
        // inside every setUp() previously paid ~180ms for password_hash().
        self::seedBaselineSiteAndUser($database);

        // RBAC permission/role catalogue must also be committed outside the
        // per-test transaction. ensureSeeded() inside every RBAC test was
        // re-inserting oc_permissions under concurrent suite load → 1213.
        self::seedBaselineRbacCatalogue();
    }

    /**
     * Commit baseline site + admin user outside the per-test transaction.
     */
    protected static function seedBaselineSiteAndUser(Database $database): void
    {
        TestDatabase::assertConnectedToTestDatabase($database);

        $site = Site::find(1);
        if ($site === null) {
            $database->query(
                "INSERT INTO sites (id, name, slug, is_default, is_active, created_at, updated_at)
                 VALUES (1, 'Test Site', 'test-site', 1, 1, NOW(), NOW())"
            );
        }

        $user = User::find(1);
        if ($user === null) {
            $database->query(
                "INSERT INTO users (id, name, email, password, site_id, role, is_active, created_at, updated_at)
                 VALUES (1, 'Test User', 'test@example.com', ?, 1, 'admin', 1, NOW(), NOW())",
                [TestPassword::HASH]
            );
        }
    }

    /**
     * Commit the shared RBAC permission/role catalogue (and site-1 role rows)
     * outside per-test transactions so concurrent ensureSeeded() calls only read.
     */
    protected static function seedBaselineRbacCatalogue(): void
    {
        Config::set('rbac', require dirname(__DIR__, 3) . '/config/rbac.php');
        $bootstrapper = new RbacBootstrapper(new RbacRepository());
        $bootstrapper->ensureCatalogueSeeded();
        // Site 1 is the default FunctionalTestCase site; pre-create its role rows
        // so enableSiteRbac() does not re-walk the catalogue on every grant.
        $bootstrapper->ensureSiteRolesForSite(1);
    }

    protected function setUp(): void
    {
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        // Ensure each test starts with a clean container so mocks/bindings from
        // previous tests cannot leak across the suite.
        Container::getInstance()->flush();
        Cache::flush();

        $this->cleanupServerGlobals();

        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing'); // optional, for functions using getenv()

        ini_set('log_errors', 0);

        $this->bootFunctionalApplication();

        Config::set('rbac.site_enabled', false);
        $this->siteRbacSeededForTest = false;

        // Isolate each test inside a transaction (rolled back in tearDown).
        // Seed + auth use fixed ids 1 so suites that hard-code FKs stay green.
        $this->beginTestDatabaseTransaction();

        $this->ensureSiteExists();
        $this->ensureCancellationReasonsExist();
        $this->ensureRefundReasonsExist();
        $this->ensureDefaultPolicyExists();

        if ($this->authenticateDefaultUser) {
            $this->actingAs();
        }

    }

    /**
     * Connect to the guarded test DB and boot ApiApplication without re-running
     * migrations (schema is applied once in setUpBeforeClass).
     */
    protected function bootFunctionalApplication(): void
    {
        $testConfig = TestDatabase::config();
        $this->database = TestDatabase::connect(reuseExisting: true);

        // Pass the existing Database so bootstrapApplication skips MigrationRunner.
        $this->app = new ApiApplication($testConfig, $this->database);
        Container::getInstance()->instance(StripeClient::class, $this->mockStripeClient());
    }

    protected function beginTestDatabaseTransaction(): void
    {
        if ($this->usesDatabaseTruncation) {
            $this->testTransactionOpen = false;
            return;
        }

        $this->database->beginTransaction();
        $this->testTransactionOpen = true;
    }

    private function ensureDefaultPolicyExists(): Model
    {
        return ReplacementPolicy::create(['site_id' => $this->siteId, 'is_default' => true, 'active' => true, 'name' => 'Goodwill Override', 'policy_class' => 'App\Services\Subscriptions\Policies\GoodwillPolicy']);
    }

    /**
     * Clean up $_SERVER superglobal to prevent test pollution
     */
    protected function cleanupServerGlobals(): void
    {
        // Store the keys we want to preserve (PHPUnit and system-level vars)
        $preserveKeys = [
            'SERVER_NAME', 'SERVER_PORT', 'SCRIPT_NAME', 'SCRIPT_FILENAME',
            'PHP_SELF', 'argv', 'argc', 'PWD', 'SHLVL', '_'
        ];

        // Remove all HTTP_* headers that were set during tests
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0 && !in_array($key, $preserveKeys)) {
                unset($_SERVER[$key]);
            }
        }

        // Reset request-related server variables but preserve system ones
        $requestVars = [
            'REQUEST_METHOD',
            'REQUEST_URI',
            'CONTENT_TYPE',
            'QUERY_STRING',
            'REQUEST_TIME',
            'REQUEST_TIME_FLOAT'
        ];

        foreach ($requestVars as $var) {
            if (isset($_SERVER[$var])) {
                unset($_SERVER[$var]);
            }
        }

        // Clean up superglobals
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_REQUEST = [];

        // Clean up session if exists
        if (isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    protected function mockStripeClient(): StripeClient
    {
        $paymentMethods = Mockery::mock(PaymentMethodService::class);
        $paymentMethods->shouldReceive('retrieve')
            ->byDefault()
            ->andReturn((object) ['customer' => 'cus_other']);

        $customerGateway = Mockery::mock(CustomerService::class);
        $customerGateway->shouldReceive('create')->andReturn((object) ['id' => 'test']);

        $customerGateway->shouldReceive('retrieve')
            ->byDefault()
            ->andReturn((object) ['id' => 'test']);

        $customerGateway->shouldReceive('update')
            ->byDefault();

        $subscriptions = Mockery::mock();
        $subscriptions->shouldReceive('retrieve')
            ->byDefault()
            ->andReturn((object) [
                'id' => 'sub_test123',
                'status' => 'active',
                'cancel_at_period_end' => true,
            ]);
        $subscriptions->shouldReceive('update')
            ->byDefault()
            ->andReturn((object) [
                'id' => 'sub_test123',
                'status' => 'active',
                'cancel_at_period_end' => false,
            ]);
        $subscriptions->shouldReceive('cancel')
            ->byDefault()
            ->andReturn((object) [
                'id' => 'sub_test123',
                'status' => 'canceled',
                'cancel_at_period_end' => false,
            ]);

        $products = Mockery::mock();
        $products->shouldReceive('create')
            ->byDefault()
            ->andReturnUsing(function (array $params = []) {
                return (object) [
                    'id' => 'prod_test_' . substr(md5(($params['name'] ?? 'plan') . microtime(true)), 0, 8),
                    'name' => $params['name'] ?? 'Test Product',
                ];
            });
        $products->shouldReceive('delete')
            ->byDefault()
            ->andReturn((object) ['id' => 'prod_test_deleted', 'deleted' => true]);

        $prices = Mockery::mock();
        $prices->shouldReceive('create')
            ->byDefault()
            ->andReturnUsing(function (array $params = []) {
                return (object) [
                    'id' => 'price_test_' . substr(md5(json_encode($params) . microtime(true)), 0, 8),
                    'product' => $params['product'] ?? 'prod_test',
                    'unit_amount' => $params['unit_amount'] ?? 0,
                    'currency' => $params['currency'] ?? 'usd',
                ];
            });
        $prices->shouldReceive('update')
            ->byDefault()
            ->andReturnUsing(function (string $id, array $params = []) {
                return (object) array_merge(['id' => $id], $params);
            });

        $stripe = Mockery::mock(StripeClient::class)->shouldIgnoreMissing();
        $stripe->paymentMethods = $paymentMethods;
        $stripe->customers = $customerGateway;
        $stripe->subscriptions = $subscriptions;
        $stripe->products = $products;
        $stripe->prices = $prices;

        return $stripe;
    }

    protected function ensureSiteExists()
    {
        if (!empty($this->siteId) && Site::find($this->siteId)) {
            return;
        }

        $site = Site::find(1);
        if ($site === null) {
            // Explicit id=1: transactional rollback does not reset AUTO_INCREMENT,
            // but many tests/factories hard-code site_id / created_by = 1.
            $this->database->query(
                "INSERT INTO sites (id, name, slug, is_default, is_active, created_at, updated_at)
                 VALUES (1, 'Test Site', 'test-site', 1, 1, NOW(), NOW())"
            );
            $site = Site::find(1);
        }

        if ($site === null) {
            throw new \RuntimeException('Failed to seed test site with id=1.');
        }

        $this->siteSlug = $site->slug;
        $this->siteId = (int) $site->id;
    }

    /**
     * Every functional test starts with a clean data snapshot (transaction
     * rollback, or truncate when $usesDatabaseTruncation is set), so — same
     * as ensureSiteExists() above — anything that now validates against the
     * DB-driven cancellation_reasons table needs its baseline rows recreated
     * per test rather than assuming a seeded environment. Reuses
     * CancellationReasonSeeder so this stays the single source of truth
     * for the legacy reason codes.
     */
    protected function ensureCancellationReasonsExist(): void
    {
        if (\App\Models\CancellationReason::query()->first() !== null
            && self::hasDefaultBusinessDecision(\App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum::CANCELLATIONS)
        ) {
            return;
        }

        (new \App\Database\Seeders\CancellationReasonSeeder())->run();
    }

    /**
     * Same rationale as ensureCancellationReasonsExist — CRM refund flows
     * require an active refund_reasons catalogue plus a default REFUNDS
     * Business Decision for policy resolution.
     */
    protected function ensureRefundReasonsExist(): void
    {
        if (\App\Models\RefundReason::query()->first() !== null
            && self::hasDefaultBusinessDecision(\App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum::REFUNDS)
        ) {
            return;
        }

        (new \App\Database\Seeders\RefundReasonSeeder())->run();
    }

    /**
     * Catalogue seeders create both reason rows and a global default decision.
     * Persisted reasons from an older DB snapshot can exist without that
     * decision; policy resolution then fails at runtime.
     */
    protected static function hasDefaultBusinessDecision(
        \App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum $category
    ): bool {
        return \App\Models\BusinessDecision::query()
            ->where('category', $category->value)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first() !== null;
    }

    /**
     * Authenticate as a test user and get token
     */
    protected function actingAs(?User $user = null): self
    {
        Auth::$user = null;

        if ($user === null) {
            $user = User::find(1);
            if ($user === null) {
                $this->database->query(
                    "INSERT INTO users (id, name, email, password, site_id, role, is_active, created_at, updated_at)
                     VALUES (1, 'Test User', 'test@example.com', ?, ?, 'admin', 1, NOW(), NOW())",
                    [TestPassword::HASH, $this->siteId]
                );
                $user = User::find(1);
            }

            if ($user === null) {
                throw new \RuntimeException('Failed to seed test user with id=1.');
            }
        }

        $this->authenticatedUser = $user;
        if (!empty($this->siteId) && Site::find($this->siteId)) {
            UserSite::firstOrCreate([
                'user_id' => $user->id,
                'site_id' => $this->siteId,
            ]);
        }
        Auth::login($user->toArray());

        // Generate a test token (you may need to adjust based on your token generation logic)
        $this->authToken = $this->generateTestToken($user);

        return $this;
    }

    /**
     * Generate a test authentication token
     */
    protected function generateTestToken(User $user, ?int $siteId = null): string
    {
        // Option 1: Use your actual token generation logic
        // return $user->createToken('test-token');

        // Option 2: Create a simple test token
        // You'll need a tokens table or similar mechanism
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        // Store token in database (adjust based on your auth implementation)
        $this->database->query(
            "INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, expires_at, created_at, site_id) 
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)",
            [
                'App\\Models\\User',
                $user->id,
                'test-token',
                $hashedToken,
                json_encode(['*']),
                date('Y-m-d H:i:s', strtotime('+8 hours')),
                $siteId ?? $this->siteId,
            ]
        );

        return $rawToken;
    }

    protected function actingAsMember(Member $member): void
    {
        $this->authenticatedMemberUser = $member;
        MemberAuth::login($member);

        // Generate a test token (you may need to adjust based on your token generation logic)
        $this->memberAuthToken = $this->generateTestTokenForMember($member);

        Session::put('member_id', $member->id);
        Session::put('member_authenticated', true);
    }

    protected function generateTestTokenForMember(Member $user): string
    {
        // Option 1: Use your actual token generation logic
        // return $user->createToken('test-token');

        // Option 2: Create a simple test token
        // You'll need a tokens table or similar mechanism
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        // Store token in database (adjust based on your auth implementation)
        $this->database->query(
            "INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, expires_at, created_at, site_id) 
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)",
            [
                'App\\Models\\Member',
                $user->id,
                'test-token',
                $hashedToken,
                json_encode(['*']),
                date('Y-m-d H:i:s', strtotime('+8 hours')),
                $this->siteId,
            ]
        );

        return $rawToken;
    }

    protected function put(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }
        return $this->makeRequest('PUT', $uri, $data, $this->getDefaultHeaders($headers), $files);
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

    /**
     * Get default headers including auth token if set
     */
    protected function getDefaultHeaders(array $additionalHeaders = [], bool $forMember = false): array
    {
        $headers = $additionalHeaders;

        if (($forMember === true || MemberAuth::check()) && !empty($this->memberAuthToken)) {
            $headers['Authorization'] = 'Bearer ' . $this->memberAuthToken;
        } elseif (!empty($this->authToken)) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }

        $headers['X-Site-Id'] = $this->siteId;

        return $headers;
    }

    protected function unauthenticateMember(): void
    {
        MemberAuth::$member = null;
        Session::forget('member_id');
        Session::forget('member_authenticated');
    }

    protected function tearDown(): void
    {
        $this->unauthenticateMember();

        if (isset($this->database)) {
            $this->cleanupDatabase();
        }
        $this->cleanupServerGlobals();
        ArrayMailer::clear();
        Mockery::close();
        parent::tearDown();
    }

    protected function cleanupDatabase(): void
    {
        TestDatabase::assertConnectedToTestDatabase($this->database);

        if ($this->testTransactionOpen || $this->databaseTransactionLevel() > 0) {
            $this->rollBackTestDatabaseTransaction();
            return;
        }

        $this->truncateAllTables();
    }

    protected function rollBackTestDatabaseTransaction(): void
    {
        try {
            while ($this->databaseTransactionLevel() > 0) {
                $this->database->rollBack();
            }
        } finally {
            $this->testTransactionOpen = false;
        }
    }

    protected function databaseTransactionLevel(): int
    {
        return (int) ($this->database->getConnectionInfo()['transaction_level'] ?? 0);
    }

    protected function truncateAllTables(): void
    {
        TestDatabase::assertConnectedToTestDatabase($this->database);

        // Get all tables
        $stmt = $this->database->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            return;
        }

        // Disable foreign key checks
        $this->database->query('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                if ($table === 'migrations') {
                    continue;
                }

                $this->database->query("TRUNCATE TABLE `$table`");
            }
        } finally {
            $this->database->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    protected function runMigrations(): void
    {
        $migrationRunner = new MigrationRunner($this->database, 'migrations');
        $migrationRunner->run();
    }

    protected function get(string $uri, array $headers = []): Response
    {
        return $this->makeRequest('GET', $uri, [], $this->getDefaultHeaders($headers));
    }

    protected function getForSite(string $uri, array $headers = [], bool $forMember = false): Response
    {
        return $this->makeRequest('GET', $this->generateUrl($uri), [], $this->getDefaultHeaders($headers, $forMember));
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

    protected function getForSiteUnauthenticated(string $uri, array $headers = []): Response
    {
        $response = $this->makeRequest('GET', $this->generateUrl($uri), [], $headers);

        return new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    protected function postForSite(string $uri, array $data = [], array $files = [], array $headers = [], $productionMode = false, bool $forMember = false): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }

        if ($productionMode) {
            $_ENV['APP_ENV'] = 'production';
        }

        $response = $this->makeRequest('POST', $this->generateUrl($uri), $data, $this->getDefaultHeaders($headers, $forMember), $files);

        $response = new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );

        if ($productionMode) {
            $_ENV['APP_ENV'] = 'testing';
        }

        return $response;
    }

    protected function postForSiteUnauthenticated(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        $this->unauthenticate();

        if (!empty($files)) {
            $_FILES = $files;
        }

        $response = $this->makeRequest('POST', $this->generateUrl($uri), $data, $headers, $files);

        return new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    /**
     * Clear authentication
     */
    protected function unauthenticate(): self
    {
        $this->authenticatedUser = null;
        Auth::$user = null;
        $this->authToken = null;
        Auth::logout();
        return $this;
    }

    protected function putForSite(string $uri, array $data = [], array $files = [], array $headers = [], bool $forMember = false): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }
        $response = $this->makeRequest('PUT', $this->generateUrl($uri), $data, $this->getDefaultHeaders($headers, $forMember), $files);

        return new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    protected function putForSiteUnauthenticated(string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        if (!empty($files)) {
            $_FILES = $files;
        }

        $this->cleanupServerGlobals();

        $response = $this->makeRequest('PUT', $this->generateUrl($uri), $data, $headers, $files);

        return new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    protected function enableSiteRbac(): void
    {
        $this->ensureSiteExists();
        Config::set('rbac', require __DIR__ . '/../../../config/rbac.php');
        Config::set('rbac.site_enabled', true);

        if ($this->siteRbacSeededForTest) {
            return;
        }

        // Catalogue + site-1 roles are seeded once in setUpBeforeClass.
        // Other sites still need site-role rows for this transaction.
        if ((int) $this->siteId !== 1) {
            (new RbacBootstrapper(new RbacRepository()))->ensureSiteRolesForSite($this->siteId);
        }

        $this->siteRbacSeededForTest = true;
    }

    protected function assignSiteRole(User $user, string $roleSlug): void
    {
        $this->enableSiteRbac();

        $role = OpenCollabRole::where('slug', $roleSlug)->first();

        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    protected function grantSitePermission(User $user, string $permissionSlug, bool $granted = true): void
    {
        $this->enableSiteRbac();

        $permission = OpenCollabPermission::where('slug', $permissionSlug)->first();

        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => $granted,
        ]);
    }

    protected function ensurePermission(
        string $name,
        string $slug,
        string $group,
    ): OpenCollabPermission {
        $permission = OpenCollabPermission::query()
            ->where('slug', $slug)
            ->first();

        if ($permission !== null) {
            return $permission;
        }

        return OpenCollabPermission::create([
            'name' => $name,
            'slug' => $slug,
            'group' => $group,
        ]);
    }

    protected function deleteForSite(string $uri, array $headers = [], bool $forMember = false): Response
    {
        $response = $this->makeRequest('DELETE', $this->generateUrl($uri), [], $this->getDefaultHeaders($headers, $forMember));

        return new TestResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    protected function deleteForSiteUnauthenticated(string $uri, array $headers = []): Response
    {
        $this->cleanupServerGlobals();

        $response = $this->makeRequest('DELETE', $this->generateUrl($uri), [], $headers);

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

    protected function patch(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->makeRequest('PATCH', $uri, $data, $this->getDefaultHeaders($headers));
    }

    protected function delete(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->makeRequest('DELETE', $uri, $data, $this->getDefaultHeaders($headers));
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

    protected function getJson(string $uri): Response
    {
        return $this->json('GET', $uri);
    }

    protected function json(string $method, string $uri, array $data = []): Response
    {
        return $this->makeRequest($method, $uri, $data, ['Content-Type' => 'application/json']);
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

    /**
     * Assert record exists in database
     */
    protected function assertDatabaseHas(string $table, array $attributes): void
    {
        $count = $this->countRecords($table, $attributes);
        $this->assertGreaterThan(
            0,
            $count,
            "Failed asserting that table [{$table}] contains record with attributes: " . json_encode($attributes)
        );
    }

    /**
     * Count records in a table
     */
    protected function countRecords(string $table, array $where = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        $bindings = [];

        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                if (is_null($value)) {
                    $conditions[] = "`{$key}` IS NULL";
                } else {
                    $conditions[] = "`{$key}` = :{$key}";
                    $bindings[$key] = $value;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->database->query($sql, $bindings);
        return (int)$stmt->fetch()['count'];
    }

    /**
     * Assert record does not exist in database
     */
    protected function assertDatabaseMissing(string $table, array $attributes): void
    {
        $count = $this->countRecords($table, $attributes);
        $this->assertEquals(
            0,
            $count,
            "Failed asserting that table [{$table}] does not contain record with attributes: " . json_encode($attributes)
        );
    }

    protected function assertSoftDeleted(string $table, array $attributes): void
    {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        $bindings = [];
        $conditions = [];

        foreach ($attributes as $key => $value) {
            if (is_null($value)) {
                $conditions[] = "`{$key}` IS NULL";
            } else {
                $conditions[] = "`{$key}` = :{$key}";
                $bindings[$key] = $value;
            }
        }

        $conditions[] = "`deleted_at` IS NOT NULL";

        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->database->query($sql, $bindings);

        $count = (int)$stmt->fetch()['count'];

        $this->assertGreaterThan(
            0,
            $count,
            "Failed asserting that table [{$table}] contains soft deleted record with attributes: "
            . json_encode($attributes)
        );
    }

    protected function assertDatabaseCount(string $string, int $int)
    {
        $count = $this->countRecords($string);
        $this->assertEquals($int, $count);
    }

    protected function createFile(string $filePath)
    {
        $filePath = getcwd() . '/' . $filePath;

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        if (!file_exists($filePath)) {
            file_put_contents($filePath, 'dummy image content');
        }
    }

    protected function setAuthenticatedUser(int $userId): void
    {
        $this->currentUserId = $userId;
        $_SESSION['user_id'] = $userId;
    }

    protected function createMockHttpClient(): HttpClient
    {
        return new class extends HttpClient {
            protected function request(string $method, string $url, array $options = []): HttpClientResponse
            {
                $mock = FunctionalTestCase::getHttpMock($url);

                if ($mock === null) {
                    // No mock found, call parent (real request) or throw exception
                    throw new Exception("No mock configured for URL: {$url}");
                }

                return new HttpClientResponse(
                    $mock['status'],
                    $mock['headers'] ?? [],
                    $mock['content']
                );
            }
        };
    }

    public static function getHttpMock(string $url): ?array
    {
        return self::$httpMocks[$url] ?? null;
    }

    protected function mockHttpResponse(string $url, string $content, int $statusCode = 200, array $headers = []): void
    {
        self::$httpMocks[$url] = [
            'content' => $content,
            'status' => $statusCode,
            'headers' => $headers
        ];
    }

    protected function assertObjectHasAttribute(string $attribute, object $object): void
    {
        $this->assertTrue(
            property_exists($object, $attribute),
            sprintf(
                'Failed asserting that object of class "%s" has attribute "%s".',
                $object::class,
                $attribute
            )
        );
    }

    protected function assertOrderData(array $data, array $expectations): bool
    {
        foreach ($expectations as $key => $value) {

            if (!array_key_exists($key, $data)) {
                return false;
            }

            if ($data[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    protected function decodeJson(Response $response)
    {
        return json_decode($response->getContent(), true);
    }

    protected function createSite()
    {
        $name = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10);

        return Site::create([
            'name' => $name,
            'slug' => strtolower($name),
        ]);
    }

    protected function createUser(array $attributes = []): User
    {
        $defaults = [
            'name' => $attributes['name'] ?? 'Test User',
            'email' => $attributes['email'] ?? ('test-user-' . uniqid() . '@example.com'),
            'password' => $attributes['password'] ?? TestPassword::HASH,
            'site_id' => $attributes['site_id'] ?? $this->siteId,
            'role' => $attributes['role'] ?? 'user',
        ];

        $user = User::create(array_merge($defaults, $attributes));

        if (!empty($user->site_id)) {
            UserSite::firstOrCreate([
                'user_id' => $user->id,
                'site_id' => $user->site_id,
            ]);
        }

        return $user;
    }

}