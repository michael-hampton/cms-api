<?php

namespace App\Enums\OpenCollab;

enum ActivityEventType: string
{
    case ArticleCreated = 'article_created';
    case ArticleUpdated = 'article_updated';
    case ArticlePublished = 'article_published';
    case CommentAdded = 'comment_added';
    case InvitationSent = 'invitation_sent';
    case InvitationAccepted = 'invitation_accepted';
    case PaymentReceived = 'payment_received';
}