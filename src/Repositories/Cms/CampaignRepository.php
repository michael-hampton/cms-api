<?php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Models\Campaign;
use App\Models\CampaignSignup;
use App\Models\Model;
use App\Repositories\Repository;

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
                'signup_count' => DB::raw('signup_count + 1'),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }


    protected function getModelClass(): string
    {
        return Campaign::class;
    }
}