<?php

namespace App\Database\Seeders;

use App\Enums\Subscriptions\CommunicationTypeEnum;
use App\Framework\Database\Seeder\Seeder;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;

/**
 * Seeds default subscription communication definitions and their schedules.
 *
 * Idempotent: uses firstOrCreate keyed on `key` for communications,
 * and on (subscription_communication_id, name) for schedules.
 *
 * ITD is intentionally omitted — it will be seeded in a later iteration.
 */
class SubscriptionCommunicationSeeder extends Seeder
{
    /**
     * Communication definitions.
     *
     * Each entry maps to a row in subscription_communications.
     * The `schedules` key is an array of schedule definitions belonging
     * to that communication.
     *
     * @var array<int, array{
     *     key: string,
     *     name: string,
     *     type: string,
     *     template: string,
     *     channels: array<int, string>,
     *     is_active: bool,
     *     sort_order: int,
     *     schedules: array<int, array{
     *         name: string,
     *         trigger_type: string,
     *         relative_to: string|null,
     *         offset_days: int|null,
     *         fixed_date: string|null,
     *         send_time: string|null,
     *         is_active: bool,
     *         sort_order: int,
     *     }>
     * }>
     */
    private array $communications = [
        [
            'key'        => 'renewal_reminder_default',
            'name'       => 'Renewal Reminder',
            'type'       => CommunicationTypeEnum::RENEWAL_REMINDER,
            'template'   => \App\Mail\Subscriptions\RenewalReminderMail::class,
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 10,
            'schedules'  => [
                [
                    'name'         => '30 days before renewal',
                    'trigger_type' => 'relative',
                    'relative_to'  => 'renewal_date',
                    'offset_days'  => -30,
                    'fixed_date'   => null,
                    'send_time'    => '08:00:00',
                    'is_active'    => true,
                    'sort_order'   => 10,
                ],
            ],
        ],

        [
            'key'        => 'acknowledgement_default',
            'name'       => 'Acknowledgement',
            'type'       => CommunicationTypeEnum::ACKNOWLEDGEMENT,
            'template'   => \App\Mail\Subscriptions\AcknowledgementMail::class,
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 20,
            'schedules'  => [],
        ],
        [
            'key'        => 'itd_price_rise_default',
            'name'       => 'ITD Price Rise Notice',
            'type'       => CommunicationTypeEnum::ITD,
            'template'   => \App\Mail\Subscriptions\ItdPriceRiseNoticeMail::class,
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 50,
            'schedules'  => [],
        ],
        [
            'key'        => 'customer_care_default',
            'name'       => 'Customer Care',
            'type'       => CommunicationTypeEnum::CUSTOMER_CARE,
            'template'   => \App\Mail\Subscriptions\CustomerCareMail::class,
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 30,
            'schedules'  => [],
        ],

        [
            'key'        => 'ccc_expiry_reminder_default',
            'name'       => 'CCC Expiry Reminder',
            'type'       => CommunicationTypeEnum::CCC_EXPIRY_REMINDER,
            'template'   => \App\Mail\Subscriptions\CccExpiryReminderMail::class,
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 40,
            'schedules'  => [
                [
                    'name'         => '14 days before expiry',
                    'trigger_type' => 'relative',
                    'relative_to'  => 'expiry_date',
                    'offset_days'  => -14,
                    'fixed_date'   => null,
                    'send_time'    => '08:00:00',
                    'is_active'    => true,
                    'sort_order'   => 10,
                ],
            ],
        ],

        // ITD is intentionally omitted from this seeder.
        // It will be added in a subsequent iteration once
        // the ITD mailable and template are in place.
    ];

    public function run(): void
    {
        foreach ($this->communications as $definition) {
            $scheduleDefinitions = $definition['schedules'];
            unset($definition['schedules']);

            $communication = $this->upsertCommunication($definition);

            foreach ($scheduleDefinitions as $scheduleDefinition) {
                $this->upsertSchedule($communication, $scheduleDefinition);
            }
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Create or update a SubscriptionCommunication record by key.
     *
     * firstOrCreate ensures idempotency: running the seeder twice will not
     * create duplicate rows. We pass all mutable fields via the second
     * argument so they are set on creation but NOT overwritten on a re-run,
     * preserving any admin edits made after initial seeding.
     */
    private function upsertCommunication(array $definition): SubscriptionCommunication
    {
        /** @var SubscriptionCommunication $communication */
        $communication = SubscriptionCommunication::firstOrCreate(
            ['key' => $definition['key']],
            [
                'name'       => $definition['name'],
                'type'       => $definition['type'] instanceof \BackedEnum
                    ? $definition['type']->value
                    : $definition['type'],
                'template'   => $definition['template'],
                'channels'   => $definition['channels'],
                'is_active'  => $definition['is_active'],
                'sort_order' => $definition['sort_order'],
            ]
        );

        return $communication;
    }

    /**
     * Create or update a schedule keyed on (subscription_communication_id, name).
     *
     * Using the schedule name as a natural key means renaming a schedule in the
     * seeder will create a new row rather than silently updating an existing one —
     * which is the safer default for named seed data.
     */
    private function upsertSchedule(
        SubscriptionCommunication $communication,
        array                     $definition
    ): SubscriptionCommunicationSchedule {
        /** @var SubscriptionCommunicationSchedule $schedule */
        $schedule = SubscriptionCommunicationSchedule::firstOrCreate(
            [
                'subscription_communication_id' => $communication->id,
                'name'                          => $definition['name'],
            ],
            [
                'trigger_type' => $definition['trigger_type'],
                'relative_to'  => $definition['relative_to'],
                'offset_days'  => $definition['offset_days'],
                'fixed_date'   => $definition['fixed_date'],
                'send_time'    => $definition['send_time'],
                'is_active'    => $definition['is_active'],
                'sort_order'   => $definition['sort_order'],
            ]
        );

        return $schedule;
    }
}