<?php

namespace App\Models;

class OrderHistory extends Model
{
    protected $table = 'order_history';

    protected $fillable = [
        'order_id',
        'action',
        'user_id',
        'changes',
        'notes',
        'created_at'
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime'
    ];

    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id', 'id');
    }

    public function toArray(): array
    {
        $data = parent::toArray();

        if ($this->relationLoaded('user') && $this->user) {
            $data['user_name'] = $this->user->first_name . ' ' . $this->user->last_name;
        } else {
            $data['user_name'] = 'System';
        }

        return $data;
    }
}