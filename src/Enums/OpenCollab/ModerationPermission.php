// src/Enums/OpenCollab/ModerationPermission.php
<?php

namespace App\Enums\OpenCollab;

enum ModerationPermission: string
{
    case ContentReview = 'content.review';
    case ContentApprove = 'content.approve';
    case ContentReject = 'content.reject';
    case ContentRequestChanges = 'content.request_changes';
    case ContentEscalate = 'content.escalate';
    case ContentViewHighRisk = 'content.view_high_risk';
    case ContentAssignReview = 'content.assign_review';
    case ContentOverridePriority = 'content.override_priority';
    case ContentResolveRisk = 'content.resolve_risk';

    case PagesReview = 'pages.review';
    case PagesApprove = 'pages.approve';
    case PagesReject = 'pages.reject';
    case PagesRequestChanges = 'pages.request_changes';
    case PagesEscalate = 'pages.escalate';
    case PagesViewHighRisk = 'pages.view_high_risk';
    case PagesAssignReview = 'pages.assign_review';
    case PagesOverridePriority = 'pages.override_priority';
    case PagesResolveRisk = 'pages.resolve_risk';
}