<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Database\Database;
use App\Models\Model;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

class IssueDeliveryService
{
    public function __construct(
        private readonly IssueDeliveryRepository $scheduleRepository,
        private readonly Database                $database
    )
    {
    }

    public function activateSchedule(int $scheduleId): ?Model
    {
        return $this->updateScheduleStatus($scheduleId, IssueScheduleStatus::ACTIVE);
    }

    public function updateScheduleStatus(int $scheduleId, IssueScheduleStatus $status): ?Model
    {
        return $this->scheduleRepository->update($scheduleId, [
            'status' => $status->value
        ]);
    }

    public function cancelSchedule(int $scheduleId): ?Model
    {
        return $this->updateScheduleStatus($scheduleId, IssueScheduleStatus::CANCELLED);
    }

    public function importFromCsv(int $siteId, string $csvPath): array
    {
        $rows = $this->parseCsvFile($csvPath);
        return $this->scheduleRepository->bulkCreateFromCsv($siteId, $rows);
    }

    private function parseCsvFile(string $csvPath): array
    {
        if (!file_exists($csvPath)) {
            throw new \Exception('CSV file not found');
        }

        $rows = [];
        $handle = fopen($csvPath, 'r');
        $headers = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    public function exportToCsv(int $siteId): string
    {
        $schedules = $this->scheduleRepository->getAllForSite($siteId);

        $csvData = [];
        $csvData[] = [
            'ID',
            'Title',
            'Issue Number',
            'Issue Code',
            'Product ID',
            'Promotion ID',
            'On Sale Date',
            'Cut Off Date',
            'Fulfilment Date',
            'Status',
            'Created At'
        ];

        foreach ($schedules as $schedule) {
            $csvData[] = [
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
                $schedule->created_at->format('Y-m-d H:i:s')
            ];
        }

        $filename = 'issue_schedules_' . date('Y-m-d_His') . '.csv';
        $filepath = storage_path('exports/' . $filename);

        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $fp = fopen($filepath, 'w');
        foreach ($csvData as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        return $filepath;
    }
}