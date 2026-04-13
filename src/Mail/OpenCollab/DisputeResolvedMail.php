<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\EarningsDispute;
use App\Models\User;

/**
 * Sent to the contributor when an admin resolves or rejects their dispute.
 */
class DisputeResolvedMail extends Mailable
{
    public function __construct(
        private readonly EarningsDispute $dispute,
        private readonly User            $contributor,
        private readonly bool            $wasApproved,
        private readonly ?string         $adminNotes = null,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $subject = $this->wasApproved
            ? "Your earnings dispute has been resolved in your favour"
            : "Update on your earnings dispute";

        return $this
            ->subject($subject)
            ->markdown('emails.open-collab.dispute-resolved', [
                'contributor' => $this->contributor,
                'dispute' => $this->dispute,
                'wasApproved' => $this->wasApproved,
                'adminNotes' => $this->adminNotes,
                'earningsUrl' => rtrim(config('app.url'), '/') . '/contributor/earnings',
            ]);
    }
}