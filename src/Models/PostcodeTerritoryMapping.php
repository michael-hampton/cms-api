<?php

namespace App\Models;

/**
 * Maps a UK postcode prefix (e.g. "CF", "EH", "SW") to a Territory record.
 *
 * This table is a pure lookup — it has no content or display properties of
 * its own. The Territory it points to is the existing territories table
 * which belongs to a RegionSet and carries site/slug/is_active context.
 *
 * @property int $id
 * @property string $postcode_prefix  Uppercase 2-character prefix, e.g. "CF"
 * @property int $territory_id     FK → territories.id
 */
class PostcodeTerritoryMapping extends Model
{
    public $timestamps = false;
    protected $table = 'postcode_territory_mappings';
    protected $fillable = [
        'postcode_prefix',
        'territory_id',
    ];

    public function territory(bool $relation = false)
    {
        return $this->belongsTo(Territory::class, 'territory_id', 'id', $relation);
    }
}