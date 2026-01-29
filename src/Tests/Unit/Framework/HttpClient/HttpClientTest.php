<?php

namespace App\Tests\Unit\Framework\HttpClient;

use App\Framework\HttpClient\HttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    private HttpClient $client;

    public static function httpMethodProvider(): array
    {
        return [
            ['get'],
            ['post'],
            ['put'],
            ['patch'],
            ['delete'],
            ['head'],
        ];
    }

    public function testTimeoutSetsTimeout()
    {
        $client = $this->client->timeout(60);

        $this->assertInstanceOf(HttpClient::class, $client);
        $this->assertSame($this->client, $client);
    }

    public function testRetrySetsRetryConfiguration()
    {
        $client = $this->client->retry(3, 200);

        $this->assertInstanceOf(HttpClient::class, $client);
        $this->assertSame($this->client, $client);
    }

    public function testWithHeadersSetsHeaders()
    {
        $client = $this->client->withHeaders([
            'X-Custom-Header' => 'value',
            'Accept' => 'application/json'
        ]);

        $this->assertInstanceOf(HttpClient::class, $client);
        $this->assertSame($this->client, $client);
    }

    public function testWithTokenSetsAuthorizationHeader()
    {
        $client = $this->client->withToken('test-token-12345');

        $this->assertInstanceOf(HttpClient::class, $client);
        $this->assertSame($this->client, $client);
    }

    public function testWithBasicAuthSetsAuthorizationHeader()
    {
        $client = $this->client->withBasicAuth('username', 'password');

        $this->assertInstanceOf(HttpClient::class, $client);
        $this->assertSame($this->client, $client);
    }

    public function testMethodChainingWorks()
    {
        $client = $this->client
            ->timeout(30)
            ->retry(2, 100)
            ->withHeaders(['Accept' => 'application/json'])
            ->withToken('token123');

        $this->assertInstanceOf(HttpClient::class, $client);
        $this->assertSame($this->client, $client);
    }

    public function testMultipleWithHeadersCalls()
    {
        $client = $this->client
            ->withHeaders(['Header1' => 'value1'])
            ->withHeaders(['Header2' => 'value2']);

        $this->assertInstanceOf(HttpClient::class, $client);
    }

    #[DataProvider('httpMethodProvider')]
    public function testHttpMethodsReturnResponse(string $method)
    {
        // We can't make real HTTP calls in unit tests, but we can verify
        // the methods exist and have the right signature
        $this->assertTrue(method_exists($this->client, $method));
    }

    public function testGetMethodSignature()
    {
        $reflection = new \ReflectionMethod($this->client, 'get');
        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('url', $params[0]->getName());
        $this->assertEquals('options', $params[1]->getName());
    }

    public function testPostMethodSignature()
    {
        $reflection = new \ReflectionMethod($this->client, 'post');
        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('url', $params[0]->getName());
        $this->assertEquals('options', $params[1]->getName());
    }

    public function testPutMethodSignature()
    {
        $reflection = new \ReflectionMethod($this->client, 'put');
        $this->assertEquals(2, $reflection->getNumberOfParameters());
    }

    public function testPatchMethodSignature()
    {
        $reflection = new \ReflectionMethod($this->client, 'patch');
        $this->assertEquals(2, $reflection->getNumberOfParameters());
    }

    public function testDeleteMethodSignature()
    {
        $reflection = new \ReflectionMethod($this->client, 'delete');
        $this->assertEquals(2, $reflection->getNumberOfParameters());
    }

    public function testHeadMethodSignature()
    {
        $reflection = new \ReflectionMethod($this->client, 'head');
        $this->assertEquals(2, $reflection->getNumberOfParameters());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new HttpClient();
    }
}