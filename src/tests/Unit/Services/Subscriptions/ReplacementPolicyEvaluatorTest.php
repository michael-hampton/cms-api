<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\ReplacementUsageStatistics;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\ReplacementPolicy;
use App\Services\Subscriptions\ReplacementPolicyEvaluator;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReplacementPolicyEvaluatorTest extends TestCase
{
    private ReplacementPolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ReplacementPolicyEvaluator();
    }

    private function policy(array $attributes): ReplacementPolicy
    {
        $policy = Mockery::mock(ReplacementPolicy::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $policy->{$key} = $value;
        }

        return $policy;
    }

    public function test_it_allows_replacement_when_policy_permits_and_no_limit_reached(): void
    {
        $policy = $this->policy([
            'allows_replacements' => true,
            'max_replacements' => 2,
            'requires_manager_approval' => false,
        ]);

        $usage = new ReplacementUsageStatistics(replacementsUsed: 1, extensionsUsed: 0);

        $result = $this->evaluator->evaluate($policy, ReplacementResolution::REPLACE, $usage);

        $this->assertTrue($result->canRequestReplacement);
    }

    public function test_it_rejects_replacement_when_replacements_are_disabled(): void
    {
        $policy = $this->policy([
            'allows_replacements' => false,
        ]);

        $usage = new ReplacementUsageStatistics(replacementsUsed: 0, extensionsUsed: 0);

        $result = $this->evaluator->evaluate($policy, ReplacementResolution::REPLACE, $usage);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertSame('This plan does not allow issue replacements.', $result->blockedReason);
    }

    public function test_it_rejects_replacement_when_limit_is_reached(): void
    {
        $policy = $this->policy([
            'allows_replacements' => true,
            'max_replacements' => 2,
            'requires_manager_approval' => false,
        ]);

        $usage = new ReplacementUsageStatistics(replacementsUsed: 2, extensionsUsed: 0);

        $result = $this->evaluator->evaluate($policy, ReplacementResolution::REPLACE, $usage);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertSame('The replacement limit for this plan has been reached.', $result->blockedReason);
    }

    public function test_it_allows_unlimited_replacements_when_max_is_null(): void
    {
        $policy = $this->policy([
            'allows_replacements' => true,
            'max_replacements' => null,
            'requires_manager_approval' => false,
        ]);

        $usage = new ReplacementUsageStatistics(replacementsUsed: 500, extensionsUsed: 0);

        $result = $this->evaluator->evaluate($policy, ReplacementResolution::REPLACE, $usage);

        $this->assertTrue($result->canRequestReplacement);
    }

    public function test_it_rejects_replacement_requiring_manager_approval(): void
    {
        $policy = $this->policy([
            'allows_replacements' => true,
            'max_replacements' => null,
            'requires_manager_approval' => true,
        ]);

        $usage = new ReplacementUsageStatistics(replacementsUsed: 0, extensionsUsed: 0);

        $result = $this->evaluator->evaluate($policy, ReplacementResolution::REPLACE, $usage);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertSame(
            'This plan requires manager approval before a replacement can be issued.',
            $result->blockedReason
        );
    }

    public function test_it_rejects_extension_when_extensions_are_disabled(): void
    {
        $policy = $this->policy([
            'allows_extensions' => false,
        ]);

        $usage = new ReplacementUsageStatistics(replacementsUsed: 0, extensionsUsed: 0);

        $result = $this->evaluator->evaluate($policy, ReplacementResolution::EXTEND, $usage);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertSame('This plan does not allow subscription extensions.', $result->blockedReason);
    }

    public function test_it_rejects_extension_when_limit_is_reached(): void
    {
        $policy = $this->policy([
            'allows_extensions' => true,
            'max_extensions' => 1,
            'requires_manager_approval' => false,
        ]);

        $usage = new ReplacementUsageStatistics(replacementsUsed: 0, extensionsUsed: 1);

        $result = $this->evaluator->evaluate($policy, ReplacementResolution::EXTEND, $usage);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertSame('The extension limit for this plan has been reached.', $result->blockedReason);
    }

    public function test_it_allows_extension_when_policy_permits_and_no_limit_reached(): void
    {
        $policy = $this->policy([
            'allows_extensions' => true,
            'max_extensions' => 3,
            'requires_manager_approval' => false,
        ]);

        $usage = new ReplacementUsageStatistics(replacementsUsed: 0, extensionsUsed: 1);

        $result = $this->evaluator->evaluate($policy, ReplacementResolution::EXTEND, $usage);

        $this->assertTrue($result->canRequestReplacement);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}