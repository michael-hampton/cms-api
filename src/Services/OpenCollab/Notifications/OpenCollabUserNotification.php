<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Database\Database;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\UserRecipientNotification;
use App\Models\User;
use App\Repositories\OpenCollab\EarningsLedgerRepository;

abstract class OpenCollabUserNotification extends AbstractNotification implements UserRecipientNotification
{
    public function notificationData(): array
    {
        $data = [
            'title' => $this->subject(),
            'message' => method_exists($this, 'body') ? $this->body() : null,
            'url' => method_exists($this, 'url') ? $this->url() : null,
        ];

        $siteId = $this->resolveSiteId();
        if ($siteId !== null) {
            $data['site_id'] = $siteId;
        }

        return $data;
    }

    private function resolveSiteId(): ?int
    {
        if (property_exists($this, 'siteId') && $this->siteId) {
            return (int) $this->siteId;
        }

        foreach (get_object_vars($this) as $value) {
            if ($value instanceof User) {
                continue;
            }

            if (is_object($value) && isset($value->site_id)) {
                return (int) $value->site_id;
            }
        }

        foreach (get_object_vars($this) as $value) {
            if (is_object($value) && isset($value->article_id) && $value->article_id) {
                return $this->siteIdForArticle((int) $value->article_id);
            }

            if (is_object($value) && isset($value->earnings_ledger_id) && $value->earnings_ledger_id) {
                return $this->siteIdForLedger((int) $value->earnings_ledger_id);
            }
        }

        return null;
    }

    private function siteIdForArticle(int $articleId): ?int
    {
        $row = Database::table('pages')
            ->where('id', $articleId)
            ->select('site_id')
            ->first();

        return $row && isset($row->site_id) ? (int) $row->site_id : null;
    }

    /**
     * Notification objects are plain data (not container-resolved), so this
     * delegates to EarningsLedgerRepository::siteIdForLedger() — the single
     * source of truth for this query — rather than instantiating the query
     * inline. Previously this was a byte-for-byte duplicate of the same
     * query in EarningsDisputeService.
     */
    private function siteIdForLedger(int $ledgerId): ?int
    {
        return (new EarningsLedgerRepository())->siteIdForLedger($ledgerId);
    }
}
