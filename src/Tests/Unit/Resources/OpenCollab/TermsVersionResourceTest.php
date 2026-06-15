<?php

namespace App\Tests\Unit\Resources\OpenCollab;

use App\Models\TermsVersion;
use App\Resources\OpenCollab\TermsVersionResource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TermsVersionResourceTest extends TestCase
{
    public function test_resource_exposes_immutable_snapshot_metadata(): void
    {
        $reflection = new ReflectionClass(TermsVersion::class);
        $terms = $reflection->newInstanceWithoutConstructor();
        $terms->forceFill([
            'id' => 7,
            'site_id' => 3,
            'semantic_version' => '1.2.0',
            'title' => 'Contributor Terms',
            'source_format' => 'html',
            'source_content' => '<p>Source</p>',
            'rendered_format' => 'html',
            'rendered_content' => '<p>Rendered</p>',
            'rendered_hash' => str_repeat('a', 64),
            'status' => 'published',
            'is_material_change' => true,
            'change_summary' => 'Revenue-share wording updated.',
            'document_id' => 12,
            'source_document_id' => 12,
            'source_type' => 'document_upload',
            'extraction_status' => 'completed',
            'created_by_user_id' => 9,
        ]);

        $data = (new TermsVersionResource($terms))->toArray();

        $this->assertSame(7, $data['id']);
        $this->assertSame('1.2.0', $data['semantic_version']);
        $this->assertSame('<p>Rendered</p>', $data['rendered_content']);
        $this->assertSame(str_repeat('a', 64), $data['rendered_hash']);
        $this->assertTrue($data['is_material_change']);
        $this->assertSame(12, $data['source_document_id']);
    }
}
