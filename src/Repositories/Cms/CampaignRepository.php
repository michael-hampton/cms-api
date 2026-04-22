<?php

namespace App\Repositories\Cms;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Campaign;
use App\Models\CampaignDelivery;
use App\Models\CampaignEvent;
use App\Models\CampaignSignup;
use App\Models\Model;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class CampaignRepository extends Repository
{
    public function getActiveCampaigns(int $siteId): Collection
    {
        $campaigns = Campaign::where('site_id', $siteId)
            ->where('is_active', true)
            ->get();

        return $campaigns->filter(function ($campaign) {
            return $campaign->isActive();
        });
    }

    public function paginate(int $perPage = 20, int $page = 1, ?int $siteId = null): array
    {
        return Campaign::orderBy('name')
            ->when(!empty($siteId), function ($query) use ($siteId) {
                return $query->where('site_id', $siteId);
            })
            ->paginate($perPage);
    }

    public function getActiveCampaignsWithNewsletter(int $siteId): Collection
    {
        $campaigns = Campaign::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereNotNull('newsletter_id')
            ->get();

        return $campaigns->filter(function ($campaign) {
            return $campaign->isActive();
        });
    }

    public function getBySite(int $siteId): Collection
    {
        return Campaign::where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPremiumGatingCampaigns(int $siteId): Collection
    {
        $campaigns = Campaign::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('gates_premium_content', true)
            ->get();

        return $campaigns->filter(function ($campaign) {
            return $campaign->isActive();
        });
    }

    public function getSubscriberCount(int $campaignId): int
    {
        return $this->database->query(
            'SELECT COUNT(*) as count FROM subscribers WHERE campaign_id = ?',
            [$campaignId]
        )->fetch()['count'] ?? 0;
    }

    public function cloneForSite(int $campaignId, int $targetSiteId): ?Model
    {
        $original = $this->find($campaignId);

        if (!$original) {
            return null;
        }

        // Generate unique slug for target site
        $slug = $original->slug;
        $counter = 1;

        while ($this->findBySlug($slug, $targetSiteId)) {
            $slug = $original->slug . '-' . $counter;
            $counter++;
        }

        $data = [
            'site_id' => $targetSiteId,
            'name' => $original->name . ' (Copy)',
            'slug' => $slug,
            'description' => $original->description,
            'newsletter_id' => null, // Don't copy newsletter reference
            'is_active' => false, // Clones start inactive
            'gates_premium_content' => $original->gates_premium_content,
            'start_date' => $original->start_date,
            'end_date' => $original->end_date,
            'tracking_params' => $original->tracking_params,
        ];

        return $this->create($data);
    }

    // CampaignRepository.php
    public function incrementSignupCount(int $campaignId): void
    {
        $this->model
            ->where('id', $campaignId)
            ->increment('signup_count');
    }

    public function incrementDailySignupCount(int $campaignId, int $siteId): void
    {
        $today = now_datetime();

        CampaignSignup::updateOrInsert(
            [
                'campaign_id' => $campaignId,
                'signup_date' => $today,
            ],
            [
                'site_id' => $siteId,
                'signup_count' => Database::raw('signup_count + 1'),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('campaign');
        $engine = new SearchEngine($configuration);

        // Replace with however your repository accesses its base query builder,
        // e.g. Campaign::query() or $this->model->newQuery()
        return $engine->search($this->query(), $criteria);
    }

    /**
     * Returns aggregate counts for the campaign index stats block.
     *
     * NOTE: The original buildStats() in CampaignController mixed two different
     * "active" concepts — isActive() (domain method, likely date-aware) and
     * status === 'active' (raw string). These have been separated into explicit,
     * named keys. Align with your CampaignStatus enum once confirmed.
     */
    public function getStatsBySite(int $siteId): array
    {
        $base = $this->query()->where('site_id', $siteId);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'approved' => (clone $base)->where('status', 'active')->count(),
            'paused' => (clone $base)->where('status', 'paused')->count(),
            'ended' => (clone $base)->where('status', 'ended')->count(),
        ];
    }

    public function existsBySlugForSite(mixed $slug, int $siteId, ?int $id = null)
    {
        return Campaign::where('slug', $slug)
            ->where('site_id', $siteId)
            ->when(!empty($id), function ($query) use ($id) {
                return $query->where('id', '!=', $id);
            })
            ->first();
    }

    public function paginateForSite(?int $siteId, int $perPage, int $page)
    {
        return Campaign::where('site_id', $siteId)->paginate($perPage, $page);
    }

    /**
     * Aggregate CampaignDelivery + CampaignEvent counts per variant key.
     *
     * Intentionally avoids a JOIN so neither table needs to know about the
     * other's schema — we resolve variant keys via the in-memory collection.
     */
    private function aggregateStats(int $campaignId, Collection $variants): array
    {
        // Map variant_id → key for lookup
        $keyById = $variants->pluck('key', 'id')->toArray();
        $variantIds = array_keys($keyById);

        // Delivery counts per variant
        $deliveryCounts = CampaignDelivery::where('campaign_id', $campaignId)
            ->whereIn('variant_id', $variantIds)
            ->selectRaw('variant_id, COUNT(*) as total')
            ->groupBy('variant_id')
            ->get()
            ->pluck('total', 'variant_id')
            ->toArray();

        // Open counts per variant (event_type = 'open')
        $openCounts = CampaignEvent::whereIn('delivery_id', function ($q) use ($campaignId, $variantIds) {
            $q->select('id')
                ->from('campaign_deliveries')
                ->where('campaign_id', $campaignId)
                ->whereIn('variant_id', $variantIds);
        })
            ->where('event_type', 'open')
            ->selectRaw('
                (SELECT variant_id FROM campaign_deliveries WHERE id = campaign_events.delivery_id LIMIT 1) as variant_id,
                COUNT(*) as total
            ')
            ->groupBy('variant_id')
            ->pluck('total', 'variant_id')
            ->toArray();

        // Click counts per variant (event_type = 'click')
        $clickCounts = CampaignEvent::whereIn('delivery_id', function ($q) use ($campaignId, $variantIds) {
            $q->select('id')
                ->from('campaign_deliveries')
                ->where('campaign_id', $campaignId)
                ->whereIn('variant_id', $variantIds);
        })
            ->where('event_type', 'click')
            ->selectRaw('
                (SELECT variant_id FROM campaign_deliveries WHERE id = campaign_events.delivery_id LIMIT 1) as variant_id,
                COUNT(*) as total
            ')
            ->groupBy('variant_id')
            ->pluck('total', 'variant_id')
            ->toArray();

        $stats = [];
        foreach ($variants as $variant) {
            $id = $variant->id;
            $deliveries = (int)($deliveryCounts[$id] ?? 0);
            $opens = (int)($openCounts[$id] ?? 0);
            $clicks = (int)($clickCounts[$id] ?? 0);

            $stats[] = [
                'key' => $keyById[$id],
                'deliveries' => $deliveries,
                'opens' => $opens,
                'clicks' => $clicks,
                'open_rate' => $deliveries > 0 ? round(($opens / $deliveries) * 100, 2) : 0,
                'click_rate' => $deliveries > 0 ? round(($clicks / $deliveries) * 100, 2) : 0,
            ];
        }

        return $stats;
    }


    public function findBySlug(string $slug, ?int $siteId = null): ?Model
    {
        $query = Campaign::where('slug', $slug);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        } else {
            $this->applySiteFilter($query);
        }

        return $query->first();
    }

    public function findValidForSignup(string $slug, int $siteId): ?Campaign
    {
        $campaign = $this->findBySlug($slug, $siteId);

        return ($campaign && $campaign instanceof Campaign && $campaign->isValidForSignup()) ? $campaign : null;
    }

    protected function getModelClass(): string
    {
        return Campaign::class;
    }
}