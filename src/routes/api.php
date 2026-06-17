<?php

require __DIR__ . '/api.original.php';

$router->post(
    '/api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/retention-incentive',
    [\App\Controllers\Crm\CrmSubscriptionRetentionController::class, 'apply']
);
