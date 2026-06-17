<?php

use App\Controllers\Crm\CrmSubscriptionRetentionController;

/** @var \App\Framework\Http\Router $router */
$router->post(
    '/api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/retention-incentive',
    [CrmSubscriptionRetentionController::class, 'apply'],
);
