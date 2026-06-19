<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\ContributorProfile;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Services\OpenCollab\ContributorPaymentMethodService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;

class ContributorPaymentMethodServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_list_returns_empty_when_contributor_has_no_stripe_customer(): void
    {
        $repository = Mockery::mock(ContributorProfileRepository::class);
        $repository->shouldReceive('findByUserId')
            ->once()
            ->with(5)
            ->andReturn(null);

        $service = new ContributorPaymentMethodService($repository, Mockery::mock(StripeClient::class));

        $this->assertSame([
            'success' => true,
            'payment_methods' => [],
            'default_payment_method_id' => null,
        ], $service->listForUser($this->makeUser()));
    }

    public function test_list_returns_formatted_cards_and_default(): void
    {
        $profile = $this->makeProfile(stripeCustomerId: 'cus_123');
        $card = $this->makePaymentMethod('pm_123', 'visa', '4242', 12, 2030);

        $repository = Mockery::mock(ContributorProfileRepository::class);
        $repository->shouldReceive('findByUserId')->once()->with(5)->andReturn($profile);

        $customers = Mockery::mock(CustomerService::class);
        $customers->shouldReceive('retrieve')
            ->once()
            ->with('cus_123')
            ->andReturn((object)['invoice_settings' => (object)['default_payment_method' => 'pm_123']]);

        $paymentMethods = Mockery::mock(PaymentMethodService::class);
        $paymentMethods->shouldReceive('all')
            ->once()
            ->with(['customer' => 'cus_123', 'type' => 'card'])
            ->andReturn((object)['data' => [$card]]);

        $service = new ContributorPaymentMethodService($repository, $this->makeStripe($customers, $paymentMethods));

        $this->assertSame([
            'success' => true,
            'payment_methods' => [[
                'id' => 'pm_123',
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2030,
                'is_default' => true,
            ]],
            'default_payment_method_id' => 'pm_123',
        ], $service->listForUser($this->makeUser()));
    }

    public function test_add_creates_customer_attaches_method_and_stores_only_stripe_ids(): void
    {
        $user = $this->makeUser();
        $profile = $this->makeProfile(id: 9);

        $repository = Mockery::mock(ContributorProfileRepository::class);
        $repository->shouldReceive('findOrCreateForUser')->once()->with(5)->andReturn($profile);
        $repository->shouldReceive('update')->once()->with(9, ['stripe_customer_id' => 'cus_new']);
        $repository->shouldReceive('update')->once()->with(9, [
            'payment_method_type' => 'stripe',
            'payment_details' => 'pm_new',
            'stripe_customer_id' => 'cus_new',
            'tax_country' => 'GB',
        ]);
        $repository->shouldReceive('findByUserId')->once()->with(5)->andReturn($this->makeProfile(stripeCustomerId: 'cus_new'));

        $customers = Mockery::mock(CustomerService::class);
        $customers->shouldReceive('create')->once()->with(Mockery::on(function (array $payload): bool {
            return $payload['email'] === 'contributor@example.com'
                && $payload['name'] === 'Contributor User'
                && $payload['metadata']['context'] === 'open_collab_contributor';
        }))->andReturn((object)['id' => 'cus_new']);
        $customers->shouldReceive('update')->once()->with('cus_new', [
            'invoice_settings' => ['default_payment_method' => 'pm_new'],
        ]);
        $customers->shouldReceive('retrieve')->once()->with('cus_new')->andReturn(
            (object)['invoice_settings' => (object)['default_payment_method' => 'pm_new']]
        );

        $paymentMethods = Mockery::mock(PaymentMethodService::class);
        $paymentMethods->shouldReceive('retrieve')
            ->once()
            ->with('pm_new')
            ->andReturn($this->makePaymentMethod('pm_new', customer: null));
        $paymentMethods->shouldReceive('attach')
            ->once()
            ->with('pm_new', ['customer' => 'cus_new']);
        $paymentMethods->shouldReceive('all')
            ->once()
            ->with(['customer' => 'cus_new', 'type' => 'card'])
            ->andReturn((object)['data' => [$this->makePaymentMethod('pm_new', customer: 'cus_new')]]);

        $service = new ContributorPaymentMethodService($repository, $this->makeStripe($customers, $paymentMethods));
        $result = $service->addForUser($user, 'pm_new', 'GB');

        $this->assertTrue($result['success']);
        $this->assertSame('pm_new', $result['default_payment_method_id']);
    }

    public function test_set_default_rejects_payment_method_owned_by_another_customer(): void
    {
        $repository = Mockery::mock(ContributorProfileRepository::class);
        $repository->shouldReceive('findByUserId')
            ->once()
            ->with(5)
            ->andReturn($this->makeProfile(stripeCustomerId: 'cus_123'));

        $paymentMethods = Mockery::mock(PaymentMethodService::class);
        $paymentMethods->shouldReceive('retrieve')
            ->once()
            ->with('pm_other')
            ->andReturn($this->makePaymentMethod('pm_other', customer: 'cus_other'));

        $service = new ContributorPaymentMethodService(
            $repository,
            $this->makeStripe(Mockery::mock(CustomerService::class), $paymentMethods)
        );

        $this->assertSame([
            'success' => false,
            'message' => 'Unauthorized.',
            'error_code' => 'unauthorized',
        ], $service->setDefaultForUser($this->makeUser(), 'pm_other'));
    }

    public function test_remove_default_card_selects_next_card_as_default(): void
    {
        $repository = Mockery::mock(ContributorProfileRepository::class);
        $repository->shouldReceive('findByUserId')
            ->twice()
            ->with(5)
            ->andReturn($this->makeProfile(id: 9, stripeCustomerId: 'cus_123'));
        $repository->shouldReceive('update')->once()->with(9, ['payment_details' => 'pm_next']);

        $customers = Mockery::mock(CustomerService::class);
        $customers->shouldReceive('retrieve')->once()->with('cus_123')->andReturn(
            (object)['invoice_settings' => (object)['default_payment_method' => 'pm_old']]
        );
        $customers->shouldReceive('update')->once()->with('cus_123', [
            'invoice_settings' => ['default_payment_method' => 'pm_next'],
        ]);
        $customers->shouldReceive('retrieve')->once()->with('cus_123')->andReturn(
            (object)['invoice_settings' => (object)['default_payment_method' => 'pm_next']]
        );

        $paymentMethods = Mockery::mock(PaymentMethodService::class);
        $paymentMethods->shouldReceive('retrieve')
            ->once()
            ->with('pm_old')
            ->andReturn($this->makePaymentMethod('pm_old', customer: 'cus_123'));
        $paymentMethods->shouldReceive('detach')->once()->with('pm_old');
        $paymentMethods->shouldReceive('all')
            ->once()
            ->with(['customer' => 'cus_123', 'type' => 'card', 'limit' => 1])
            ->andReturn((object)['data' => [$this->makePaymentMethod('pm_next', customer: 'cus_123')]]);
        $paymentMethods->shouldReceive('all')
            ->once()
            ->with(['customer' => 'cus_123', 'type' => 'card'])
            ->andReturn((object)['data' => [$this->makePaymentMethod('pm_next', customer: 'cus_123')]]);

        $service = new ContributorPaymentMethodService($repository, $this->makeStripe($customers, $paymentMethods));
        $result = $service->removeForUser($this->makeUser(), 'pm_old');

        $this->assertTrue($result['success']);
        $this->assertSame('pm_next', $result['default_payment_method_id']);
    }

    private function makeUser(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 5;
        $user->site_id = 10;
        $user->email = 'contributor@example.com';
        $user->name = 'Contributor User';

        return $user;
    }

    private function makeProfile(
        int $id = 1,
        ?string $stripeCustomerId = null,
    ): ContributorProfile {
        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->id = $id;
        $profile->stripe_customer_id = $stripeCustomerId;

        return $profile;
    }

    private function makePaymentMethod(
        string $id,
        string $brand = 'visa',
        string $last4 = '4242',
        int $expMonth = 12,
        int $expYear = 2030,
        ?string $customer = 'cus_123',
    ): object {
        return (object)[
            'id' => $id,
            'customer' => $customer,
            'card' => (object)[
                'brand' => $brand,
                'last4' => $last4,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
            ],
        ];
    }

    private function makeStripe(CustomerService $customers, PaymentMethodService $paymentMethods): StripeClient
    {
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customers;
        $stripe->paymentMethods = $paymentMethods;

        return $stripe;
    }
}
