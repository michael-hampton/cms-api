<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\MemberInsights\Newsletters\Recommendations;

use App\Enums\MemberInsights\Newsletters\NewsletterRelationType;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\NewsletterRelation;
use App\Repositories\MemberInsights\Newsletters\NewsletterRelationRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\MemberInsights\Newsletters\Recommendations\NewsletterRecommendationService;
use App\Services\MemberInsights\Newsletters\Recommendations\RecommendationResult;
use App\Services\MemberInsights\Newsletters\Suppression\SuppressionSet;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;


class NewsletterRecommendationServiceTest extends UnitTestCase
{
    private NewsletterRelationRepository&MockInterface $relationRepository;
    private NewsletterRepository&MockInterface $newsletterRepository;
    private NewsletterRecommendationService $service;

    public function test_suppressed_newsletters_never_appear_in_results(): void
    {
        $suppression = SuppressionSet::from([1, 2]);
        $member = $this->makeMember();

        $nl3 = $this->makeNewsletter(3, 'Newsletter 3');
        $source1 = $this->makeNewsletter(1, 'Source 1');

        // Relation points to newsletter 3 (not suppressed) — should appear.
        $relation = $this->makeRelation(
            relatedId: 3,
            relatedNewsletter: $nl3,
            sourceNewsletter: $source1,
            type: NewsletterRelationType::SameCategory,
        );

        $this->relationRepository
            ->shouldReceive('findRelatedTo')
            ->once()
            ->with([1, 2], 1)
            ->andReturn(collect([$relation]));

        $results = $this->service->recommend($member, $suppression, siteId: 1);

        $this->assertCount(1, $results);
        $this->assertSame(3, $results[0]->newsletter->id);
    }

    private function makeMember(): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 99;
        $member->email = 'member@example.com';
        return $member;
    }

    // ── Suppression applied first ─────────────────────────────────────────

    private function makeNewsletter(int $id, string $title): Newsletter
    {
        $nl = Mockery::mock(Newsletter::class)->makePartial();
        $nl->id = $id;
        $nl->title = $title;
        $nl->last_sent = null;
        return $nl;
    }

    private function makeRelation(
        int                    $relatedId,
        Newsletter             $relatedNewsletter,
        Newsletter             $sourceNewsletter,
        NewsletterRelationType $type,
    ): NewsletterRelation
    {
        $relation = Mockery::mock(NewsletterRelation::class)->makePartial();
        $relation->related_newsletter_id = $relatedId;
        $relation->relation_type = $type;
        $relation->relatedNewsletter = $relatedNewsletter;
        $relation->sourceNewsletter = $sourceNewsletter;
        return $relation;
    }

    // ── Scoring and ranking ───────────────────────────────────────────────

    public function test_relation_pointing_to_suppressed_newsletter_is_excluded(): void
    {
        $suppression = SuppressionSet::from([1, 2]);
        $member = $this->makeMember();

        // The related newsletter IS in the suppression set.
        $nl2 = $this->makeNewsletter(2, 'Newsletter 2');
        $source1 = $this->makeNewsletter(1, 'Source 1');

        $relation = $this->makeRelation(
            relatedId: 2,
            relatedNewsletter: $nl2,
            sourceNewsletter: $source1,
            type: NewsletterRelationType::SameBrand,
        );

        $this->relationRepository
            ->shouldReceive('findRelatedTo')
            ->once()
            ->andReturn(collect([$relation]));

        // No candidates → falls back to active newsletters.
        $this->newsletterRepository
            ->shouldReceive('getActive')
            ->once()
            ->with(1)
            ->andReturn(collect([]));

        $results = $this->service->recommend($member, $suppression, siteId: 1);

        $this->assertCount(0, $results);
    }

    public function test_results_are_sorted_by_score_descending(): void
    {
        $suppression = SuppressionSet::from([1]);
        $member = $this->makeMember();
        $source = $this->makeNewsletter(1, 'Source');

        $nl2 = $this->makeNewsletter(2, 'Category NL');
        $nl3 = $this->makeNewsletter(3, 'Premium NL');

        $relations = collect([
            $this->makeRelation(2, $nl2, $source, NewsletterRelationType::SameCategory),    // score 20
            $this->makeRelation(3, $nl3, $source, NewsletterRelationType::UpsellPremium),   // score 40
        ]);

        $this->relationRepository
            ->shouldReceive('findRelatedTo')
            ->once()
            ->andReturn($relations);

        $results = $this->service->recommend($member, $suppression, siteId: 1);

        $this->assertCount(2, $results);
        $this->assertSame(3, $results[0]->newsletter->id); // Upsell wins
        $this->assertSame(2, $results[1]->newsletter->id);
        $this->assertGreaterThan($results[1]->score, $results[0]->score);
    }

    public function test_deduplicates_by_keeping_highest_scoring_relation_per_target(): void
    {
        $suppression = SuppressionSet::from([1, 2]);
        $member = $this->makeMember();

        $nl3 = $this->makeNewsletter(3, 'Target NL');
        $source1 = $this->makeNewsletter(1, 'Source 1');
        $source2 = $this->makeNewsletter(2, 'Source 2');

        // Newsletter 3 appears via two different source relations.
        $relations = collect([
            $this->makeRelation(3, $nl3, $source1, NewsletterRelationType::ComplementaryTopic), // score 10
            $this->makeRelation(3, $nl3, $source2, NewsletterRelationType::SameBrand),          // score 30
        ]);

        $this->relationRepository
            ->shouldReceive('findRelatedTo')
            ->once()
            ->andReturn($relations);

        $results = $this->service->recommend($member, $suppression, siteId: 1);

        $this->assertCount(1, $results);
        $this->assertSame(3, $results[0]->newsletter->id);
        $this->assertSame(30, $results[0]->score); // Kept the higher score
    }

    // ── Reason strings ────────────────────────────────────────────────────

    public function test_limits_results_to_max_results(): void
    {
        $suppression = SuppressionSet::from([1]);
        $member = $this->makeMember();
        $source = $this->makeNewsletter(1, 'Source');

        $relations = collect(range(2, 10))->map(fn($i) => $this->makeRelation(
            $i,
            $this->makeNewsletter($i, "NL {$i}"),
            $source,
            NewsletterRelationType::SameCategory,
        ));

        $this->relationRepository
            ->shouldReceive('findRelatedTo')
            ->once()
            ->andReturn($relations);

        $results = $this->service->recommend($member, $suppression, siteId: 1, maxResults: 3);

        $this->assertCount(3, $results);
    }

    // ── Fallback behaviour ────────────────────────────────────────────────

    public function test_reason_includes_source_newsletter_title(): void
    {
        $suppression = SuppressionSet::from([1]);
        $member = $this->makeMember();
        $source = $this->makeNewsletter(1, 'The Morning Brief');
        $nl2 = $this->makeNewsletter(2, 'Evening Round-Up');

        $this->relationRepository
            ->shouldReceive('findRelatedTo')
            ->once()
            ->andReturn(collect([
                $this->makeRelation(2, $nl2, $source, NewsletterRelationType::SameCategory),
            ]));

        $results = $this->service->recommend($member, $suppression, siteId: 1);

        $this->assertStringContainsString('Because you subscribe to The Morning Brief — same topic', $results[0]->reason);
    }

    public function test_falls_back_to_active_newsletters_when_no_relations_exist(): void
    {
        $suppression = SuppressionSet::from([1]);
        $member = $this->makeMember();

        $this->relationRepository
            ->shouldReceive('findRelatedTo')
            ->once()
            ->andReturn(collect([]));

        $nl5 = $this->makeNewsletter(5, 'Popular NL');

        $this->newsletterRepository
            ->shouldReceive('getActive')
            ->once()
            ->with(1)
            ->andReturn(collect([$nl5]));

        $results = $this->service->recommend($member, $suppression, siteId: 1);

        $this->assertCount(1, $results);
        $this->assertSame(5, $results[0]->newsletter->id);
        $this->assertSame('Popular newsletter', $results[0]->reason);
    }

    public function test_falls_back_immediately_when_member_has_no_subscriptions(): void
    {
        // Empty suppression set means no subscribed newsletters to base
        // relations on — fallback is called without hitting relationRepository.
        $suppression = SuppressionSet::empty();
        $member = $this->makeMember();

        $this->relationRepository->shouldNotReceive('findRelatedTo');

        $nl1 = $this->makeNewsletter(1, 'Fallback NL');

        $this->newsletterRepository
            ->shouldReceive('getActive')
            ->once()
            ->with(1)
            ->andReturn(collect([$nl1]));

        $results = $this->service->recommend($member, $suppression, siteId: 1);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(RecommendationResult::class, $results[0]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function test_fallback_excludes_suppressed_newsletters(): void
    {
        $suppression = SuppressionSet::from([1]);
        $member = $this->makeMember();

        $this->relationRepository
            ->shouldReceive('findRelatedTo')
            ->once()
            ->andReturn(collect([]));

        $nl1 = $this->makeNewsletter(1, 'Already subscribed'); // suppressed
        $nl2 = $this->makeNewsletter(2, 'Available NL');

        $this->newsletterRepository
            ->shouldReceive('getActive')
            ->once()
            ->andReturn(collect([$nl1, $nl2]));

        $results = $this->service->recommend($member, $suppression, siteId: 1);

        $this->assertCount(1, $results);
        $this->assertSame(2, $results[0]->newsletter->id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->relationRepository = Mockery::mock(NewsletterRelationRepository::class);
        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);

        $this->service = new NewsletterRecommendationService(
            $this->relationRepository,
            $this->newsletterRepository,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}