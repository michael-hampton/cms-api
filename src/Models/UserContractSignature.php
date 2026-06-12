<?php

namespace App\Models;

class UserContractSignature extends Model
{
    protected $table = 'oc_user_contract_signatures';

    protected $fillable = [
        'user_id',
        'contract_id',
        'contract_version',
        'signed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'contract_version' => 'integer',
        'signed_at' => 'datetime',
    ];

    public function contract($relation = false)
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'id', $relation);
    }

    public function user($relation = false)
    {
        return $this->belongsTo(User::class, 'user_id', 'id', $relation);
    }
}
