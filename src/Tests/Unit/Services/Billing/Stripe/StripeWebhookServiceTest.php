<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Actions\Stripe\HandleInvoiceFailed;
use App\Actions\Stripe\HandleInvoicePaid;
use App\Actions\Stripe\HandleInvoiceUpcoming;
use App\Actions\Stripe\HandleSubscriptionCreated;
use App\Actions\Stripe\HandleSubscriptionDeleted;
use App\Actions\Stripe\HandleSubscriptionUpdated;
use App\Models\WebhookEvent;
use App\Repositories\Billing\WebhookEventRepository;
use App\Services\Billing\Stripe\StripeWebhookService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Stripe\Event;

class StripeWebhookServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private WebhookEventRepository&MockInterface $webhookEventRepository;
    private HandleSubscriptionCreated&MockInterface $handleSubscriptionCreated;
    private HandleSubscriptionUpdated&MockInterface $handleSubscriptionUpdated;
    private HandleSubscriptionDeleted&MockInterface $handleSubscriptionDeleted;
    private HandleInvoicePaid&MockInterface $handleInvoicePaid;
    private HandleInvoiceFailed&MockInterface $handleInvoiceFailed;
    private HandleInvoiceUpcoming&MockInterface $handleInvoiceUpcoming;
    private StripeWebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookEventRepository = Mockery::mock(WebhookEventRepository::class);
        $this->handleSubscriptionCreated = Mockery::mock(HandleSubscriptionCreated::class);
        $this->handleSubscriptionUpdated = Mockery::mock(HandleSubscriptionUpdated::class);
        $this->handleSubscriptionDeleted = Mockery::mock(HandleSubscriptionDeleted::class);
        $this->handleInvoicePaid = Mockery::mock(HandleInvoicePaid::class);
        $this->handleInvoiceFailed = Mockery::mock(HandleInvoiceFailed::class);
        $this->handleInvoiceUpcoming = Mockery::mock(HandleInvoiceUpcoming::class);

        $this->service = new StripeWebhookService(
            $this->webhookEventRepository,
            $this->handleSubscriptionCreated,
            $this->handleSubscriptionUpdated,
            $this->handleSubscriptionDeleted,
            $this->handleInvoicePaid,
            $this->handleInvoiceFailed,
            $this->handleInvoiceUpcoming
        );
    }

    private function makeEvent(string $type, string $id = 'evt_123'): Event
    {
        return Event::constructFrom([
            'id'          => $id,
            'type'        => $type,
            'data'        => ['object' => ['id' => 'obj_123', 'object' => 'invoice']],
            'api_version' => '2023-10-16',
        ]);
    }

    public function test_it_ignores_a_duplicate_event(): void
    {
        $event = $this->makeEvent('invoice.paid');

        $this->webhookEventRepository
            ->expects('existsByStripeEventId')
            ->with('evt_123')
            ->andReturn(true);

        $this->webhookEventRepository->expects('recordReceived')->never();
        $this->handleInvoicePaid->expects('handle')->never();

        $this->service->handle($event);
    }

    public function test_it_records_the_event_before_dispatching_to_the_handler(): void
    {
        $event = $this->makeEvent('invoice.paid');
        $webhookEvent = Mockery::mock(WebhookEvent::class);

        $this->webhookEventRepository->expects('existsByStripeEventId')->andReturn(false);
        $this->webhookEventRepository
            ->expects('recordReceived')
            ->with('evt_123', 'invoice.paid', Mockery::type('array'))
            ->andReturn($webhookEvent);

        $this->handleInvoicePaid->expects('handle')->with($event)->once();

        $this->service->handle($event);
    }

    public function test_it_routes_each_known_event_type_to_its_handler(): void
    {
        $cases = [
            'customer.subscription.created' => 'handleSubscriptionCreated',
            'customer.subscription.updated' => 'handleSubscriptionUpdated',
            'customer.subscription.deleted' => 'handleSubscriptionDeleted',
            'invoice.paid'                  => 'handleInvoicePaid',
            'invoice.payment_failed'        => 'handleInvoiceFailed',
            'invoice.upcoming'              => 'handleInvoiceUpcoming',
        ];

        foreach ($cases as $type => $handlerProperty) {
            $event = $this->makeEvent($type, 'evt_' . $handlerProperty);
            $webhookEvent = Mockery::mock(WebhookEvent::class);

            $this->webhookEventRepository->expects('existsByStripeEventId')->andReturn(false);
            $this->webhookEventRepository->expects('recordReceived')->andReturn($webhookEvent);
            $this->{$handlerProperty}->expects('handle')->with($event)->once();

            $this->service->handle($event);
        }
    }

    public function test_it_marks_an_unrecognised_event_type_as_ignored(): void
    {
        $event = $this->makeEvent('some.unhandled.event');
        $webhookEvent = Mockery::mock(WebhookEvent::class);

        $this->webhookEventRepository->expects('existsByStripeEventId')->andReturn(false);
        $this->webhookEventRepository->expects('recordReceived')->andReturn($webhookEvent);
        $this->webhookEventRepository->expects('markIgnored')->with($webhookEvent)->once();

        $this->service->handle($event);
    }

    public function test_it_marks_the_event_failed_and_rethrows_when_the_handler_throws(): void
    {
        $event = $this->makeEvent('invoice.paid');
        $webhookEvent = Mockery::mock(WebhookEvent::class);

        $this->webhookEventRepository->expects('existsByStripeEventId')->andReturn(false);
        $this->webhookEventRepository->expects('recordReceived')->andReturn($webhookEvent);

        $this->handleInvoicePaid
            ->expects('handle')
            ->andThrow(new \RuntimeException('boom'));

        $this->webhookEventRepository
            ->expects('markFailed')
            ->with($webhookEvent, 'boom')
            ->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->service->handle($event);
    }
}
