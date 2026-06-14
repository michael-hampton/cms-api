<?php

namespace App\Tests\Unit\Requests\OpenCollab;

use App\Requests\OpenCollab\StoreTermsFromDocumentRequest;
use PHPUnit\Framework\TestCase;

class StoreTermsFromDocumentRequestTest extends TestCase
{
    public function test_rules_require_version_title_and_document(): void
    {
        $rules = (new StoreTermsFromDocumentRequest())->rules();

        $this->assertContains('required', $rules['semantic_version']);
        $this->assertContains('required', $rules['title']);
        $this->assertContains('required', $rules['document']);
        $this->assertArrayHasKey('is_material_change', $rules);
        $this->assertArrayHasKey('change_summary', $rules);
    }
}
