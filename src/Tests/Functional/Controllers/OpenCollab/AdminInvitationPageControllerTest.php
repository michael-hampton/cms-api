<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\Invitation;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class AdminInvitationPageControllerTest extends FunctionalTestCase
{
    public function test_index_uses_admin_layout_and_renders_invitations(): void
    {
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'writer@example.com',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $response = $this->getForSite('/open-collab/admin/invitations');
        $content = $response->getContent();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('OC Admin', $content);
        $this->assertStringContainsString('Invitation History', $content);
        $this->assertStringContainsString('writer@example.com', $content);
    }
}
