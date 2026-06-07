<?php

namespace App\Tests\Functional\Controllers;

use App\Models\OpenCollabPermission;
use App\Models\OpenCollabSiteUserPermission;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Repositories\OpenCollab\RbacRepository;

class AuthControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSiteRbac();
    }

    public function test_login_response_includes_effective_permissions(): void
    {
        $user = $this->createUser([
            'email' => 'auth-login-permissions@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'user',
        ]);
        $this->grantPermissionForUser($user->id, 'pages.review');

        $response = $this->postForSiteUnauthenticated('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);

        $this->assertContains('pages.review', $payload['data']['permissions']);
        $this->assertContains('pages.review', $payload['data']['user']['permissions']);
    }

    public function test_me_response_includes_effective_permissions(): void
    {
        $user = $this->createUser([
            'email' => 'auth-me-permissions@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($user);
        $this->grantPermissionForUser($user->id, 'pages.review');

        $response = $this->getForSite('/api/auth/me');

        $this->assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);

        $this->assertSame($user->id, $payload['data']['user']['id']);
        $this->assertContains('pages.review', $payload['data']['permissions']);
    }

    public function test_user_without_permissions_returns_empty_permissions_array(): void
    {
        $user = $this->createUser([
            'email' => 'auth-me-no-permissions@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->getForSite('/api/auth/me');

        $this->assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);

        $this->assertSame([], $payload['data']['permissions']);
    }

    private function grantPermissionForUser(int $userId, string $permissionSlug): void
    {
        (new RbacBootstrapper(new RbacRepository()))->ensureSeeded($this->siteId);

        $permission = OpenCollabPermission::where('slug', $permissionSlug)->first();

        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $userId,
            'permission_id' => $permission->id,
            'granted' => true,
        ]);
    }
}
