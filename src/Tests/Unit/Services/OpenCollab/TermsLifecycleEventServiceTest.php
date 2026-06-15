<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Events\OpenCollab\TermsVersionAcceptedEvent;
use App\Events\OpenCollab\TermsVersionPublishedEvent;
use App\Framework\Events\EventDispatcher;
use App\Models\TermsVersion;
use App\Models\UserTermsAcceptance;
use App\Services\OpenCollab\TermsLifecycleEventService;
use Mockery;
use PHPUnit\Framework\TestCase;

class TermsLifecycleEventServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_published_dispatches_terms_published_event(): void
    {
        $dispatcher = Mockery::mock(EventDispatcher::class);
        $service = new TermsLifecycleEventService($dispatcher);
        $terms = Mockery::mock(TermsVersion::class)->makePartial();
        $terms->id = 7;
        $terms->site_id = 3;
        $terms->is_material_change = true;

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn($event) => $event instanceof TermsVersionPublishedEvent
                && $event->termsVersionId === 7
                && $event->siteId === 3
                && $event->publishedByUserId === 99
                && $event->requiresReacceptance === true));

        $service->published($terms, 99);
        $this->assertTrue(true);
    }

    public function test_accepted_dispatches_terms_accepted_event(): void
    {
        $dispatcher = Mockery::mock(EventDispatcher::class);
        $service = new TermsLifecycleEventService($dispatcher);
        $acceptance = Mockery::mock(UserTermsAcceptance::class)->makePartial();
        $acceptance->terms_version_id = 7;
        $acceptance->site_id = 3;
        $acceptance->user_id = 20;
        $acceptance->accepted_via = 'onboarding';

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn($event) => $event instanceof TermsVersionAcceptedEvent
                && $event->termsVersionId === 7
                && $event->siteId === 3
                && $event->userId === 20
                && $event->acceptedVia === 'onboarding'));

        $service->accepted($acceptance);
        $this->assertTrue(true);
    }
}
