<?php

namespace App\Repositories;

use App\Models\Person;

class PersonRepository extends Repository
{

    protected function getModelClass(): string
    {
      return Person::class;
    }
}