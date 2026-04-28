<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Models\Page;
use App\Models\User;

final class ArticleApprovedNotification extends AbstractNotification
    implements EmailableNotification, ConsentAwareNotification
{
    public function __construct(
        public readonly Page $article,
        public readonly User $user,
    )
    {
        parent::__construct(userId: $user->id, email: $user->email);
    }

    public function subject(): string
    {
        return "Your article \"{$this->article->title}\" was approved 🎉";
    }

    public function toMailable(): Mailable
    {
        return new ArticleApprovedMail($this->article, $this->user);
    }

    public function consentType(): string
    {
        return 'contributor.article_approved';
    }
}