<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Models\NewsletterCreationSchedule;
use App\Models\NewsletterSendSchedule;
use App\Repositories\Newsletters\NewsletterCreationScheduleRepository;
use App\Repositories\Newsletters\NewsletterSendScheduleRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterScheduleRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private NewsletterCreationScheduleRepository $creationRepo;
    private NewsletterSendScheduleRepository $sendRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creationRepo = new NewsletterCreationScheduleRepository();
        $this->sendRepo = new NewsletterSendScheduleRepository();
    }

    public function test_find_by_newsletter_id_returns_active_schedule(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $result = $this->creationRepo->findByNewsletterId($newsletter->id);

        $this->assertNotNull($result);
        $this->assertEquals($schedule->id, $result->id);
    }

    // =========================================================================
    // NewsletterCreationScheduleRepository
    // =========================================================================

    public function test_find_by_newsletter_id_returns_paused_schedule(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'paused',
        ]);

        $result = $this->creationRepo->findByNewsletterId($newsletter->id);

        $this->assertNotNull($result);
        $this->assertEquals('paused', $result->status);
    }

    public function test_find_by_newsletter_id_does_not_return_cancelled_schedule(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'cancelled',
        ]);

        $result = $this->creationRepo->findByNewsletterId($newsletter->id);

        $this->assertNull($result);
    }

    public function test_find_by_newsletter_id_returns_null_when_none_exist(): void
    {
        $newsletter = $this->createNewsletter();

        $result = $this->creationRepo->findByNewsletterId($newsletter->id);

        $this->assertNull($result);
    }

    public function test_find_active_for_newsletter_returns_only_active(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'paused',
        ]);
        $active = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $result = $this->creationRepo->findActiveForNewsletter($newsletter->id);

        $this->assertNotNull($result);
        $this->assertEquals($active->id, $result->id);
    }

    public function test_has_active_schedule_returns_true_when_active_exists(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->creationRepo->hasActiveScheduleForNewsletter($newsletter->id));
    }

    public function test_has_active_schedule_returns_false_when_only_paused(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'paused',
        ]);

        $this->assertFalse($this->creationRepo->hasActiveScheduleForNewsletter($newsletter->id));
    }

    public function test_has_active_schedule_returns_false_when_only_cancelled(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'cancelled',
        ]);

        $this->assertFalse($this->creationRepo->hasActiveScheduleForNewsletter($newsletter->id));
    }

    public function test_create_persists_all_fields(): void
    {
        $newsletter = $this->createNewsletter();
        $nextRunAt = '2026-02-25 12:00:00';

        $schedule = $this->creationRepo->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'day_of_month' => null,
            'time' => '12:00',
            'status' => 'active',
            'next_run_at' => $nextRunAt,
        ]);

        $this->assertInstanceOf(NewsletterCreationSchedule::class, $schedule);
        $this->assertEquals('weekly', $schedule->frequency);
        $this->assertEquals(1, $schedule->day_of_week);
        $this->assertEquals('12:00', $schedule->time);
        $this->assertEquals('active', $schedule->status);
        $this->assertNotNull($schedule->next_run_at);
    }

    public function test_update_modifies_status_and_next_run_at(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
            'next_run_at' => '2026-02-25 12:00:00',
        ]);

        $this->creationRepo->update($schedule->id, [
            'status' => 'cancelled',
            'next_run_at' => null,
        ]);

        $refreshed = NewsletterCreationSchedule::find($schedule->id);
        $this->assertEquals('cancelled', $refreshed->status);
        $this->assertNull($refreshed->next_run_at);
    }

    public function test_schedules_are_isolated_by_newsletter(): void
    {
        $newsletter1 = $this->createNewsletter();
        $newsletter2 = $this->createNewsletter();

        $this->createNewsletterCreationSchedule(['newsletter_id' => $newsletter1->id, 'status' => 'active']);
        $this->createNewsletterCreationSchedule(['newsletter_id' => $newsletter2->id, 'status' => 'active']);

        $result = $this->creationRepo->findByNewsletterId($newsletter1->id);

        $this->assertEquals($newsletter1->id, $result->newsletter_id);
    }

    public function test_send_find_by_newsletter_id_returns_active_schedule(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $result = $this->sendRepo->findByNewsletterId($newsletter->id);

        $this->assertNotNull($result);
        $this->assertEquals($schedule->id, $result->id);
    }

    // =========================================================================
    // NewsletterSendScheduleRepository
    // =========================================================================

    public function test_send_find_by_newsletter_id_does_not_return_cancelled(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'cancelled',
        ]);

        $result = $this->sendRepo->findByNewsletterId($newsletter->id);

        $this->assertNull($result);
    }

    public function test_send_has_active_schedule_returns_false_when_none(): void
    {
        $newsletter = $this->createNewsletter();

        $this->assertFalse($this->sendRepo->hasActiveScheduleForNewsletter($newsletter->id));
    }

    public function test_send_create_persists_creation_schedule_link(): void
    {
        $newsletter = $this->createNewsletter();
        $creationSchedule = $this->createNewsletterCreationSchedule(['newsletter_id' => $newsletter->id]);

        $sendSchedule = $this->sendRepo->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'creation_schedule_id' => $creationSchedule->id,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '14:30',
            'status' => 'active',
            'next_run_at' => '2026-02-25 14:30:00',
        ]);

        $this->assertEquals($creationSchedule->id, $sendSchedule->creation_schedule_id);
    }

    public function test_send_creation_schedule_link_set_null_on_creation_schedule_delete(): void
    {
        $newsletter = $this->createNewsletter();
        $creationSchedule = $this->createNewsletterCreationSchedule(['newsletter_id' => $newsletter->id]);
        $sendSchedule = $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'creation_schedule_id' => $creationSchedule->id,
        ]);

        // Simulate cascade null via DB constraint
        NewsletterCreationSchedule::where('id', $creationSchedule->id)->delete();

        $refreshed = NewsletterSendSchedule::find($sendSchedule->id);
        $this->assertNull($refreshed->creation_schedule_id);
    }

    public function test_get_due_schedules_returns_active_schedule_with_past_next_run_at(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
            'next_run_at' => now_datetime()->subMinutes(1)->format('Y-m-d H:i:s'),
        ]);

        $results = $this->sendRepo->getDueSchedules();

        $this->assertTrue($results->contains('id', $schedule->id));
    }

    public function test_get_due_schedules_excludes_paused_and_future(): void
    {
        $newsletter = $this->createNewsletter();

        $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'paused',
            'next_run_at' => now_datetime()->subMinutes(1)->format('Y-m-d H:i:s'),
        ]);

        $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
            'next_run_at' => now_datetime()->addHours(1)->format('Y-m-d H:i:s'),
        ]);

        $results = $this->sendRepo->getDueSchedules();

        $this->assertCount(0, $results);
    }

    public function test_get_due_schedules_filters_by_site_id(): void
    {
        $newsletter = $this->createNewsletter();

        $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'site_id' => 999,
            'status' => 'active',
            'next_run_at' => now_datetime()->subMinutes(1)->format('Y-m-d H:i:s'),
        ]);

        $results = $this->sendRepo->getDueSchedules($this->siteId);

        $this->assertCount(0, $results);
    }
}