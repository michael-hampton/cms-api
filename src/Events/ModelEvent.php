<?php

namespace App\Events;

use App\Models\Model;

abstract class ModelEvent
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
