<?php

namespace App\Models;

class Contract extends Model
{
    protected $table = 'oc_contracts';

    protected $fillable = [
        'site_id',
        'version',
        'content',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function signatures($relation = false)
    {
        return $this->hasMany(UserContractSignature::class, 'contract_id', 'id', $relation);
    }
}