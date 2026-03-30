<?php

namespace App\Actions\Subscriptions;

use App\Framework\Storage\StoragePathResolverInterface;
use App\Framework\Support\Collection;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

class ExportIssueSchedulesAction
{
    private const HEADERS = [
        'ID', 'Title', 'Issue Number', 'Issue Code',
        'Product ID', 'Promotion ID', 'On Sale Date',
        'Cut Off Date', 'Fulfilment Date', 'Status', 'Created At',
    ];

    public function __construct(
        private readonly IssueDeliveryRepository      $scheduleRepository,
        private readonly StoragePathResolverInterface $storagePathResolver,
    )
    {
    }

    public function execute(int $siteId): string
    {
        $schedules = $this->scheduleRepository->getAllForSite($siteId);
        $filepath = $this->storagePathResolver->resolve('exports/issue_schedules_' . date('Y-m-d_His') . '.csv');

        $this->ensureDirectoryExists($filepath);
        $this->writeCsv($filepath, $schedules);

        return $filepath;
    }

    private function ensureDirectoryExists(string $filepath): void
    {
        $directory = dirname($filepath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function writeCsv(string $filepath, Collection $schedules): void
    {
        $fp = fopen($filepath, 'w');

        fputcsv($fp, self::HEADERS);

        foreach ($schedules as $schedule) {
            fputcsv($fp, $this->mapRow($schedule));
        }

        fclose($fp);
    }

    private function mapRow(mixed $schedule): array
    {
        return [
            $schedule->id,
            $schedule->title,
            $schedule->issue_number,
            $schedule->issue_code ?? '',
            $schedule->product_id ?? '',
            $schedule->promotion_id ?? '',
            $schedule->on_sale_date->format('Y-m-d'),
            $schedule->cut_off_date?->format('Y-m-d') ?? '',
            $schedule->fulfilment_date?->format('Y-m-d') ?? '',
            $schedule->status->value,
            $schedule->created_at->format('Y-m-d H:i:s'),
        ];
    }
}