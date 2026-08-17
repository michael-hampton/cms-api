<?php

namespace App\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\PrintRunWorkflowInput;
use App\Models\Model;
use App\Models\WorkflowRun;

class WorkflowRunFactory
{
    public function create(PrintRunWorkflowInput $input): Model
    {
        // WorkflowRun::start() is a static Model call. It's intentionally
        // quarantined behind this factory (rather than called directly from
        // PrintRunWorkflow) so callers depend on an injectable, mockable
        // seam instead of the static call itself.
        return WorkflowRun::start($input);
    }
}