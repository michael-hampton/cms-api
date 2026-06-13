<?php

namespace App\Repositories\OpenCollab;

use App\Models\ModerationAction;
use App\Repositories\Repository;

class ModerationActionRepository extends Repository
{

    protected function getModelClass(): string
    {
       return ModerationAction::class;
    }
}