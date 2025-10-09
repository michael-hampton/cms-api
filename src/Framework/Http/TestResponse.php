<?php

namespace App\Framework\Http;

use PHPUnit\Framework\Assert;

class TestResponse extends Response
{
    public function assertStatus(int $expected): self
    {
        Assert::assertSame(
            $expected,
            $this->getStatusCode(),
            "Expected status {$expected}, got {$this->getStatusCode()}"
        );

        return $this;
    }

    public function assertHeader(string $name, string $value): self
    {
        $headers = $this->getHeaders();

        Assert::assertArrayHasKey($name, $headers);
        Assert::assertSame($value, $headers[$name]);

        return $this;
    }

    public function assertJson(array $expectedSubset): self
    {
        $decoded = json_decode($this->getContent(), true);
        Assert::assertIsArray($decoded, 'Response content is not valid JSON');

        if (array_key_exists('data', $decoded)) {
            $decoded = $decoded['data'];
        }

        foreach ($expectedSubset as $key => $value) {
            Assert::assertArrayHasKey($key, $decoded);
            Assert::assertSame($value, $decoded[$key]);
        }

        return $this;
    }

    public function assertSee(string $text): self
    {
        Assert::assertStringContainsString($text, $this->getContent());
        return $this;
    }

    public function assertDontSee(string $text): self
    {
        Assert::assertStringNotContainsString($text, $this->getContent());
        return $this;
    }

    public function assertRedirect(string $url): self
    {
        Assert::assertTrue(
            in_array($this->getStatusCode(), [301, 302, 303, 307, 308]),
            "Expected redirect response, got status {$this->getStatusCode()}"
        );

        $headers = $this->getHeaders();
        Assert::assertArrayHasKey('Location', $headers);
        Assert::assertSame($url, $headers['Location']);

        return $this;
    }
}