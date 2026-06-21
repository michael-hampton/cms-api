<?php

declare(strict_types=1);

namespace App\Tests\Unit\Routes;

use PHPUnit\Framework\TestCase;

class CrmIssueResolutionRoutesTest extends TestCase
{
    public function test_resolution_route_is_registered_as_api_route(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../../routes/crm-issue-resolutions.php');

        $this->assertStringContainsString(
            '/api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/resolution',
            $contents
        );
    }

    public function test_legacy_replace_route_is_overridden_to_resolution_controller(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../../routes/crm-issue-resolutions.php');

        $this->assertStringContainsString(
            '/api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/replace',
            $contents
        );
        $this->assertStringContainsString('CrmIssueResolutionController::class', $contents);
        $this->assertStringContainsString('AuthenticateWithToken::class', $contents);
    }

    public function test_route_file_is_loaded_after_main_api_routes(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../../config/routing.php');

        $apiPosition = strpos($contents, "'routes/api.php'");
        $resolutionPosition = strpos($contents, "'routes/crm-issue-resolutions.php'");

        $this->assertIsInt($apiPosition);
        $this->assertIsInt($resolutionPosition);
        $this->assertGreaterThan($apiPosition, $resolutionPosition);
    }
}
