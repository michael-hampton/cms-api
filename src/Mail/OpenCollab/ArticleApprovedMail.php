<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Page;
use App\Models\User;

class ArticleApprovedMail extends Mailable
{
    public function __construct(
        private readonly Page $article,
        private readonly User $user,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Your article \"{$this->article->title}\" was approved 🎉")
            ->markdown('emails.open-collab.article-approved', [
                'user' => $this->user,
                'article' => $this->article,
                'url' => rtrim(config('app.url'), '/') . '/articles/' . $this->article->id,
            ]);
    }
}