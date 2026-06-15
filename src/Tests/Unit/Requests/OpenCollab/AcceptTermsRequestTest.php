<?php

namespace App\Tests\Unit\Requests\OpenCollab;

use App\Requests\OpenCollab\AcceptTermsRequest;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AcceptTermsRequestTest extends TestCase
{
    public function test_rules_require_terms_version_and_explicit_agreement(): void
    {
        $rules = (new ReflectionClass(AcceptTermsRequest::class))
            ->newInstanceWithoutConstructor()
            ->rules();

        $this->assertContains('required', $rules['terms_version_id']);
        $this->assertContains('integer', $rules['terms_version_id']);
        $this->assertContains('required', $rules['agreed']);
        $this->assertContains('accepted', $rules['agreed']);
    }
}
