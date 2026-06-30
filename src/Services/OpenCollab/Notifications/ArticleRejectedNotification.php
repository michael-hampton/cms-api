<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Models\Page;
use App\Models\User;

final class ArticleRejectedNotification extends OpenCollabUserNotification
    implements EmailableNotification, ConsentAwareNotification
{
    public function __construct(
        public readonly Page    $article,
        public readonly User    $user,
        public readonly ?string $reason = null,
    )
    {
        parent::__construct(userId: $user->id, email: $user->email);
    }

    public function subject(): string
    {
        return "Your article \"{$this->article->title}\" was not approved";
    }

    public function toMailable(): Mailable
    {
        return new ArticleRejectedMail($this->article, $this->user, $this->reason);
    }

    public function consentType(): string
    {
        return 'contributor.article_rejected';
    }
}