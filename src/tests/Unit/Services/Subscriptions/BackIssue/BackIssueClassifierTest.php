<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BackIssue;

use App\Enums\Subscriptions\FulfilmentTypeEnum;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Services\Subscriptions\BackIssue\BackIssueClassifier;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BackIssueClassifier.
 *
 * Verifies the classification decision only — no persistence, no queries
 * beyond the single injected LabelRunRepository collaborator call.
 */
class BackIssueClassifierTest extends TestCase
{
    private LabelRunRepository $labelRunRepository;
    private BackIssueClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->labelRunRepository = Mockery::mock(LabelRunRepository::class);
        $this->classifier = new BackIssueClassifier($this->labelRunRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_classifies_as_back_issue_when_label_run_already_complete(): void
    {
        $issue = $this->makeIssue(id: 10, onSaleDate: '+10 days');

        $this->labelRunRepository
            ->shouldReceive('hasCompletedRunForIssueDelivery')
            ->once()
            ->with(10)
            ->andReturn(true);

        $result = $this->classifier->classify($issue);

        $this->assertSame(FulfilmentTypeEnum::BACK_ISSUE, $result);
    }

    public function test_classifies_as_back_issue_when_issue_already_on_sale(): void
    {
        $issue = $this->makeIssue(id: 11, onSaleDate: '-5 days');

        $this->labelRunRepository
            ->shouldReceive('hasCompletedRunForIssueDelivery')
            ->once()
            ->with(11)
            ->andReturn(false);

        $result = $this->classifier->classify($issue);

        $this->assertSame(FulfilmentTypeEnum::BACK_ISSUE, $result);
    }

    public function test_classifies_as_standard_when_not_yet_printed_or_on_sale(): void
    {
        $issue = $this->makeIssue(id: 12, onSaleDate: '+10 days');

        $this->labelRunRepository
            ->shouldReceive('hasCompletedRunForIssueDelivery')
            ->once()
            ->with(12)
            ->andReturn(false);

        $result = $this->classifier->classify($issue);

        $this->assertSame(FulfilmentTypeEnum::STANDARD, $result);
    }

    public function test_classifies_as_standard_when_on_sale_date_is_null(): void
    {
        $issue = $this->makeIssue(id: 13, onSaleDate: null);

        $this->labelRunRepository
            ->shouldReceive('hasCompletedRunForIssueDelivery')
            ->once()
            ->with(13)
            ->andReturn(false);

        $result = $this->classifier->classify($issue);

        $this->assertSame(FulfilmentTypeEnum::STANDARD, $result);
    }

    private function makeIssue(int $id, ?string $onSaleDate): IssueDelivery
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = $id;
        $issue->on_sale_date = $onSaleDate ? new \DateTime($onSaleDate) : null;

        return $issue;
    }
}
