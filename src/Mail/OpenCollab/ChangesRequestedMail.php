<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Page;
use App\Models\User;

/**
 * Sent to a contributor when moderation changes are requested
 * for one of their submitted pages.
 */
class ChangesRequestedMail extends Mailable
{
    public function __construct(
        private readonly Page $page,
        private readonly User $requestedBy,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Changes requested for \"{$this->page->title}\"")
            ->markdown('emails.open-collab.changes-requested', [
                'page' => $this->page,
                'requestedBy' => $this->requestedBy,
                'moderationNotes' => $this->page->moderation_notes,
                'pageUrl' => $this->buildPageUrl(),
            ]);
    }

    private function buildPageUrl(): string
    {
        return rtrim(config('app.url'), '/')
            . '/open-collab/pages/'
            . $this->page->id
            . '/edit';
    }
}