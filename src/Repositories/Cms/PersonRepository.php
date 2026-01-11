<?php

namespace App\Repositories\Cms;

use App\Models\Person;
use App\Repositories\Repository;

class PersonRepository extends Repository
{

    protected function getModelClass(): string
    {
      return Person::class;
    }
}