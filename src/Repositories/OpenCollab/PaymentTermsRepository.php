<?php

namespace App\Repositories\OpenCollab;

use App\Models\Model;
use App\Models\PaymentTerms;
use App\Repositories\Repository;

class PaymentTermsRepository extends Repository
{
    /**
     * Creates or updates the payment terms for a site.
     */
    public function upsertForSite(int $siteId, int $payoutDelayDays, int $minimumPayoutAmount): Model
    {
        $existing = $this->forSite($siteId);

        if ($existing) {
            $this->update($existing->id, [
                'payout_delay_days' => $payoutDelayDays,
                'minimum_payout_amount' => $minimumPayoutAmount,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->forSite($siteId);
        }

        return $this->create([
            'site_id' => $siteId,
            'payout_delay_days' => $payoutDelayDays,
            'minimum_payout_amount' => $minimumPayoutAmount,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Returns the payment terms for a site, or null if none configured.
     */
    public function forSite(int $siteId): ?PaymentTerms
    {
        /** @var PaymentTerms|null */
        return PaymentTerms::where('site_id', $siteId)->first();
    }

    protected function getModelClass(): string
    {
        return PaymentTerms::class;
    }
}