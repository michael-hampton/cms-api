<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Page;
use App\Models\User;

class ArticleRejectedMail extends Mailable
{
    public function __construct(
        private readonly Page    $article,
        private readonly User    $user,
        private readonly ?string $reason = null,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Your article \"{$this->article->title}\" was not approved")
            ->markdown('emails.open-collab.article-rejected', [
                'user' => $this->user,
                'article' => $this->article,
                'reason' => $this->reason,
            ]);
    }
}