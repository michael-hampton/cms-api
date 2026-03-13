<?php

namespace App\Tests\Functional\Controllers\Newsletters;

use App\Models\NewsletterCreationSchedule;
use App\Models\NewsletterSendSchedule;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterScheduleControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /newsletters/{id}/schedules
    // =========================================================================

    public function test_index_returns_null_schedules_when_none_exist(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/schedules");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('schedules', $data);
        $this->assertNull($data['schedules']['creation']);
        $this->assertNull($data['schedules']['send']);
    }

    public function test_index_returns_existing_creation_schedule(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '12:00',
            'status' => 'active',
        ]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/schedules");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data['schedules']['creation']);
        $this->assertEquals('weekly', $data['schedules']['creation']['frequency']);
        $this->assertEquals(1, $data['schedules']['creation']['day_of_week']);
        $this->assertEquals('12:00', $data['schedules']['creation']['time']);
        $this->assertEquals('active', $data['schedules']['creation']['status']);
        $this->assertArrayHasKey('next_run_at', $data['schedules']['creation']);
    }

    public function test_index_returns_both_schedules_when_both_exist(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule(['newsletter_id' => $newsletter->id]);
        $this->createNewsletterSendSchedule(['newsletter_id' => $newsletter->id]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/schedules");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data['schedules']['creation']);
        $this->assertNotNull($data['schedules']['send']);
    }

    public function test_index_does_not_return_cancelled_schedules(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'cancelled',
        ]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/schedules");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['schedules']['creation']);
    }

    // =========================================================================
    // POST /newsletters/{id}/schedules/creation
    // =========================================================================

    public function test_store_creation_creates_weekly_schedule(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '12:00',
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('schedule', $data);
        $this->assertEquals('weekly', $data['schedule']['frequency']);
        $this->assertEquals(1, $data['schedule']['day_of_week']);
        $this->assertEquals('12:00', $data['schedule']['time']);
        $this->assertEquals('active', $data['schedule']['status']);
        $this->assertNotNull($data['schedule']['next_run_at']);
    }

    public function test_store_creation_creates_daily_schedule(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'daily',
            'time' => '08:00',
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('daily', $data['schedule']['frequency']);
        $this->assertNull($data['schedule']['day_of_week']);
    }

    public function test_store_creation_creates_monthly_schedule(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'monthly',
            'day_of_month' => 15,
            'time' => '09:00',
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('monthly', $data['schedule']['frequency']);
        $this->assertEquals(15, $data['schedule']['day_of_month']);
    }

    public function test_store_creation_rejects_when_active_schedule_exists(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterCreationSchedule(['newsletter_id' => $newsletter->id, 'status' => 'active']);

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'weekly',
            'day_of_week' => 2,
            'time' => '10:00',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('already exists', $data['error']);
    }

    public function test_store_creation_validates_missing_frequency(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'time' => '12:00',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('frequency', $data['errors']);
    }

    public function test_store_creation_validates_invalid_frequency(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'hourly',
            'time' => '12:00',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('frequency', $data['errors']);
    }

    public function test_store_creation_validates_missing_day_of_week_for_weekly(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'weekly',
            'time' => '12:00',
        ]);

        $this->assertResponseStatus(500, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('day_of_week', $data['error']);
    }

    public function test_store_creation_validates_missing_day_of_month_for_monthly(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'monthly',
            'time' => '09:00',
        ]);

        $this->assertResponseStatus(500, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('day_of_month', $data['error']);
    }

    public function test_store_creation_validates_missing_time(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'weekly',
            'day_of_week' => 1,
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('time', $data['errors']);
    }

    public function test_store_creation_validates_invalid_time_format(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/creation", [
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '9:00am',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('time', $data['errors']);
    }

    // =========================================================================
    // PUT /newsletters/{id}/schedules/creation/{scheduleId}
    // =========================================================================

    public function test_update_creation_schedule_changes_time(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '12:00',
        ]);

        $response = $this->putForSite(
            "/api/newsletters/{$newsletter->id}/schedules/creation/{$schedule->id}",
            ['time' => '14:00']
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('14:00', $data['schedule']['time']);
        $this->assertNotNull($data['schedule']['next_run_at']);
    }

    public function test_update_creation_schedule_pauses_schedule(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $response = $this->putForSite(
            "/api/newsletters/{$newsletter->id}/schedules/creation/{$schedule->id}",
            ['status' => 'paused']
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('paused', $data['schedule']['status']);
    }

    public function test_update_creation_schedule_resumes_schedule(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'paused',
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '12:00',
        ]);

        $response = $this->putForSite(
            "/api/newsletters/{$newsletter->id}/schedules/creation/{$schedule->id}",
            ['status' => 'active']
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('active', $data['schedule']['status']);
        $this->assertNotNull($data['schedule']['next_run_at']);
    }

    public function test_update_creation_schedule_returns_422_for_invalid_status(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        // 'cancelled' is not an allowed value in UpdateNewsletterScheduleRequest
        $response = $this->putForSite(
            "/api/newsletters/{$newsletter->id}/schedules/creation/{$schedule->id}",
            ['status' => 'cancelled']
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('status', $data['errors']);
    }

    public function test_update_creation_schedule_returns_422_for_cancelled(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'cancelled',
        ]);

        $response = $this->putForSite(
            "/api/newsletters/{$newsletter->id}/schedules/creation/{$schedule->id}",
            ['status' => 'active']
        );

        $this->assertResponseStatus(422, $response);
    }

    // =========================================================================
    // DELETE /newsletters/{id}/schedules/creation/{scheduleId}
    // =========================================================================

    public function test_destroy_creation_schedule_cancels_it(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterCreationSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $response = $this->deleteForSite(
            "/api/newsletters/{$newsletter->id}/schedules/creation/{$schedule->id}"
        );

        $this->assertResponseStatus(200, $response);

        $refreshed = NewsletterCreationSchedule::find($schedule->id);
        $this->assertEquals('cancelled', $refreshed->status);
        $this->assertNull($refreshed->next_run_at);
    }

    public function test_destroy_creation_schedule_returns_404_for_nonexistent(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->deleteForSite("/api/newsletters/{$newsletter->id}/schedules/creation/99999");

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /newsletters/{id}/schedules/send
    // =========================================================================

    public function test_store_send_creates_weekly_schedule(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/send", [
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '14:30',
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('weekly', $data['schedule']['frequency']);
        $this->assertEquals('14:30', $data['schedule']['time']);
        $this->assertEquals('active', $data['schedule']['status']);
        $this->assertNotNull($data['schedule']['next_run_at']);
    }

    public function test_store_send_rejects_when_active_schedule_exists(): void
    {
        $newsletter = $this->createNewsletter();
        $this->createNewsletterSendSchedule(['newsletter_id' => $newsletter->id, 'status' => 'active']);

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/send", [
            'frequency' => 'weekly',
            'day_of_week' => 2,
            'time' => '10:00',
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_send_validates_missing_frequency(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/schedules/send", [
            'time' => '14:30',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('frequency', $data['errors']);
    }

    // =========================================================================
    // PUT /newsletters/{id}/schedules/send/{scheduleId}
    // =========================================================================

    public function test_update_send_schedule_pauses_schedule(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $response = $this->putForSite(
            "/api/newsletters/{$newsletter->id}/schedules/send/{$schedule->id}",
            ['status' => 'paused']
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('paused', $data['schedule']['status']);
    }

    public function test_update_send_schedule_returns_422_for_invalid_status(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $response = $this->putForSite(
            "/api/newsletters/{$newsletter->id}/schedules/send/{$schedule->id}",
            ['status' => 'cancelled']
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('status', $data['errors']);
    }

    // =========================================================================
    // DELETE /newsletters/{id}/schedules/send/{scheduleId}
    // =========================================================================

    public function test_destroy_send_schedule_cancels_it(): void
    {
        $newsletter = $this->createNewsletter();
        $schedule = $this->createNewsletterSendSchedule([
            'newsletter_id' => $newsletter->id,
            'status' => 'active',
        ]);

        $response = $this->deleteForSite(
            "/api/newsletters/{$newsletter->id}/schedules/send/{$schedule->id}"
        );

        $this->assertResponseStatus(200, $response);

        $refreshed = NewsletterSendSchedule::find($schedule->id);
        $this->assertEquals('cancelled', $refreshed->status);
        $this->assertNull($refreshed->next_run_at);
    }

    public function test_destroy_send_schedule_returns_404_for_nonexistent(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->deleteForSite("/api/newsletters/{$newsletter->id}/schedules/send/99999");

        $this->assertResponseStatus(404, $response);
    }
}