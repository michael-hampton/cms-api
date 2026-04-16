<?php

namespace App\Enums;

enum MemberMetricType: string
{
    case PageView = 'page_view';
    case PageLike = 'page_like';
    case CommentPosted = 'comment_posted';
    case RewardClaimed = 'reward_claimed';
    case OrderCreated = 'order_created';
}