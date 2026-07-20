<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions\BusinessDecisions;

/**
 * The platform domain a BusinessDecision governs. Business Decisions are a
 * generic, reusable concept (name, is_default, assignments) — this enum is
 * what tags a given decision to the domain that interprets it.
 *
 * NOT a cancellation type. Within CANCELLATIONS, per-reason behaviour
 * (e.g. bereavement vs price-objection) is an outcome of each
 * CancellationReasonPolicy row's resolved options, not a category value.
 */
enum BusinessDecisionCategoryEnum: string
{
    case CANCELLATIONS = 'cancellations';
    case FULFILMENT = 'fulfilment';
    case RENEWALS = 'renewals';
    case SUSPENSIONS = 'suspensions';
    case REFUNDS = 'refunds';
}
