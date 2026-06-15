<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\Image;
use App\Models\Page;
use App\Models\Site;
use App\Models\ImageSubmissionEvidence;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

abstract class OpenCollabTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;

    // -------------------------------------------------------------------------
    // Model stubs — partial mocks so we get a real object with injected attrs
    // -------------------------------------------------------------------------

    protected function makeImage(array $attrs = []): Image
    {
        $image = Mockery::mock(Image::class)->makePartial();

        $image->shouldReceive('update')
            ->byDefault()
            ->andReturnUsing(function (array $attributes) use ($image): bool {
                foreach ($attributes as $key => $value) {
                    $image->$key = $value;
                }

                return true;
            });

        $image->shouldReceive('fresh')
            ->byDefault()
            ->andReturnSelf();

        $defaults = [
            'id'           => 1,
            'site_id'      => 4,
            'name'         => 'Test image',
            'url'          => 'https://cdn.example.com/test.jpg',
            'image_rights' => 'contributor_owned',
            'alt_text'     => 'Test alt',
            'credit'       => 'Jane Smith',
            'is_active'    => true,
            'created_by'   => 42,
            'width'        => 800,
            'height'       => 600,
            'mime_type'    => 'image/jpeg',
        ];

        foreach (array_merge($defaults, $attrs) as $key => $value) {
            $image->$key = $value;
        }

        return $image;
    }

    protected function makeSite(array $attrs = []): Site
    {
        $site = Mockery::mock(Site::class)->makePartial();

        $defaults = ['id' => 4, 'name' => 'Test Site'];

        foreach (array_merge($defaults, $attrs) as $key => $value) {
            $site->$key = $value;
        }

        return $site;
    }

    protected function makePage(array $attrs = []): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();

        $defaults = [
            'id'             => 10,
            'site_id'        => 4,
            'contributor_id' => 42,
            'blocks'         => [],
            'resubmission_count' => 0,
        ];

        foreach (array_merge($defaults, $attrs) as $key => $value) {
            $page->$key = $value;
        }

        return $page;
    }

    protected function makeEvidence(array $attrs = []): ImageSubmissionEvidence
    {
        $evidence = Mockery::mock(ImageSubmissionEvidence::class)->makePartial();

        $defaults = [
            'id'                     => 1,
            'cms_image_id'           => 99,
            'contributor_user_id'    => 42,
            'request_correlation_id' => null,
        ];

        foreach (array_merge($defaults, $attrs) as $key => $value) {
            $evidence->$key = $value;
        }

        return $evidence;
    }
}