<?php

namespace App\Actions\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Database\Database;
use App\Imports\CsvParser;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use InvalidArgumentException;

class ImportIssueSchedulesAction
{
    public function __construct(
        private readonly CsvParser               $csvParser,
        private readonly IssueDeliveryRepository $scheduleRepository,
        private readonly Database                $database,
    )
    {
    }

    public function execute(int $siteId, string $csvPath): array
    {
        $rows = $this->csvParser->parse($csvPath);

        $result = [
            'created' => [],
            'errors' => [],
            'total' => count($rows),
            'success_count' => 0,
            'error_count' => 0,
        ];

        $this->database->transaction(function () use ($siteId, $rows, &$result): void {
            $validRows = [];

            foreach ($rows as $row) {
                $line = $row['__line'] ?? '?';

                if (!empty($row['__malformed'])) {
                    $result['errors'][] = [
                        'row' => $line,
                        'error' => 'Column count does not match header.',
                        'data' => $row,
                    ];
                    continue;
                }

                try {
                    $this->validateRow($row);
                    $validRows[] = $row;
                } catch (InvalidArgumentException $e) {
                    $result['errors'][] = [
                        'row' => $line,
                        'error' => $e->getMessage(),
                        'data' => $row,
                    ];
                }
            }

            $result['error_count'] = count($result['errors']); // moved up, before early return

            if (empty($validRows)) {
                return;
            }

            $repositoryResult = $this->scheduleRepository->bulkCreateFromCsv($siteId, $validRows);

            $result['created'] = $repositoryResult['created'];
            $result['errors'] = array_merge($result['errors'], $repositoryResult['errors']);
            $result['success_count'] = $repositoryResult['success_count'];
            $result['error_count'] = count($result['errors']); // recalculate after merging repo errors
        });

        return $result;
    }

    private function validateRow(array $row): void
    {
        if (empty($row['title'])) {
            throw new InvalidArgumentException('Title is required');
        }

        if (empty($row['issue_number'])) {
            throw new InvalidArgumentException('Issue number is required');
        }

        if (!is_numeric($row['issue_number'])) {
            throw new InvalidArgumentException('Issue number must be numeric');
        }

        if (empty($row['on_sale_date'])) {
            throw new InvalidArgumentException('On-sale date is required');
        }

        if (false === strtotime($row['on_sale_date'])) {
            throw new InvalidArgumentException('On-sale date must be a valid date');
        }

        if (!empty($row['status']) && !IssueScheduleStatus::tryFrom($row['status'])) {
            throw new InvalidArgumentException('Invalid status value');
        }
    }
}