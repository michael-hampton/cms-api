<?php

use App\Controllers\Crm\CrmIssueResolutionController;
use App\Framework\Http\Router;

/** @var Router $router */

$router->post(
    '/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/resolution',
    [CrmIssueResolutionController::class, 'resolve']
);

$router->post(
    '/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/replace',
    [CrmIssueResolutionController::class, 'replace']
);
