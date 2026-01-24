<?php

namespace App\Services\Product;

use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Repositories\Product\MerchantProductFeedRepository;
use Exception;

class MerchantProductFeedService
{
    protected MerchantProductFeedRepository $repository;

    public function __construct(MerchantProductFeedRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getFeed(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function createFeed(array $data): Model
    {
        // Set default values
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        // Calculate next fetch time based on frequency
        if (isset($data['fetch_frequency']) && $data['fetch_frequency'] !== 'manual') {
            $data['next_fetch_at'] = $this->calculateNextFetchTime($data['fetch_frequency']);
        }

        return $this->repository->create($data);
    }

    protected function calculateNextFetchTime(string $frequency): ?string
    {
        $now = time();

        switch ($frequency) {
            case 'hourly':
                $next = strtotime('+1 hour', $now);
                break;
            case 'daily':
                $next = strtotime('+1 day', $now);
                break;
            case 'weekly':
                $next = strtotime('+1 week', $now);
                break;
            default:
                return null;
        }

        return date('Y-m-d H:i:s', $next);
    }

    public function updateFeed(int $id, array $data): ?Model
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            return null;
        }

        // Recalculate next fetch time if frequency changed
        if (isset($data['fetch_frequency']) && $data['fetch_frequency'] !== 'manual') {
            $data['next_fetch_at'] = $this->calculateNextFetchTime($data['fetch_frequency']);
        }

        return $this->repository->update($id, $data);
    }

    public function deleteFeed(int $id): bool
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            return false;
        }

        return $this->repository->delete($id);
    }

    public function fetchFeed(int $id): ?Model
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            return null;
        }

        try {
            // Update status to processing
            $this->repository->update($id, [
                'status' => 'processing',
                'last_error' => null
            ]);

            // Here you would implement actual feed fetching logic
            // For now, we'll simulate success
            $updateData = [
                'status' => 'success',
                'last_fetched_at' => date('Y-m-d H:i:s'),
                'next_fetch_at' => $this->calculateNextFetchTime($feed->fetch_frequency)
            ];

            return $this->repository->update($id, $updateData);
        } catch (Exception $e) {
            Logger::error('Feed fetch failed: ' . $e->getMessage());

            $this->repository->update($id, [
                'status' => 'failed',
                'last_error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function downloadFeedData(int $id): string
    {
        $feed = $this->repository->find($id);

        if (!$feed) {
            throw new Exception('Feed not found');
        }

        // Here you would implement actual feed download logic
        // For now, return sample data based on feed type
        switch ($feed->feed_type) {
            case 'xml':
                return '<?xml version="1.0"?><products></products>';
            case 'csv':
                return 'id,name,price\n1,Product 1,10.00';
            case 'json':
                return json_encode(['products' => []]);
            default:
                return '';
        }
    }

    public function getActiveFeedsForMerchant(int $merchantId): Collection
    {
        return $this->repository->getActiveFeedsByMerchant($merchantId);
    }

    public function getFeedsDueForFetch(): Collection
    {
        return $this->repository->getDueForFetch();
    }
}