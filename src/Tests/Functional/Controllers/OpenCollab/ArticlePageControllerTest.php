<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ArticlePageControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_create_returns_403_without_content_create_permission(): void
    {
        $this->enableSiteRbac();

        $contributor = $this->createUser([
            'email' => 'article-page-restricted@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->actingAs($contributor);

        $response = $this->getForSite('/open-collab/articles/create');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_create_returns_200_when_content_create_permission_is_granted(): void
    {
        $this->enableSiteRbac();

        $contributor = $this->createUser([
            'email' => 'article-page-allowed@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->grantSitePermission($contributor, 'content.create');
        $this->actingAs($contributor);

        $response = $this->getForSite('/open-collab/articles/create');

        $this->assertEquals(200, $response->getStatusCode());
    }
}
