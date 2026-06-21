<?php

use App\Controllers\Crm\CrmIssueResolutionController;
use App\Framework\Authorization\AuthenticateWithToken;
use App\Framework\Http\Router;

/** @var Router $router */

$middleware = [AuthenticateWithToken::class];

$router->post(
    '/api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/resolution',
    [CrmIssueResolutionController::class, 'resolve'],
    middleware: $middleware
);

$router->post(
    '/api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/replace',
    [CrmIssueResolutionController::class, 'replace'],
    middleware: $middleware
);
