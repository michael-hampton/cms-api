<?php

namespace App\Models;

/**
 * Links a reward definition to a product.
 * When a member purchases that product, any pending MemberReward
 * associated with this link is moved to 'approved'.
 *
 * @property int $id
 * @property int $reward_id
 * @property int $product_id
 */
class ProductRewardDefinition extends Model
{
    protected $table = 'product_reward_definitions';

    protected $fillable = [
        'reward_definition_id',
        'product_id',
    ];

    protected $timestamps = false;

    public function reward()
    {
        return $this->belongsTo(RewardDefinition::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}