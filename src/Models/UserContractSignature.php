<?php

namespace App\Models;

class UserContractSignature extends Model
{
    protected $table = 'oc_user_contract_signatures';

    protected $fillable = [
        'user_id',
        'contract_id',
        'signed_at',
        'ip_address',
    ];

    protected $casts = [
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