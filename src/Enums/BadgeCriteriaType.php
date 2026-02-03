<?php
// src/Enums/BadgeCriteriaType.php

namespace App\Enums;

enum BadgeCriteriaType: string
{
    case COMMENTS_COUNT = 'comments_count';
    case PAGES_READ = 'pages_read';
    case LIKES_GIVEN = 'likes_given';
    case MEMBER_DAYS = 'member_days';
    case ORDERS_COUNT = 'orders_count';
    case TOTAL_SPENT = 'total_spent';
}