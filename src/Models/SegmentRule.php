<?php

namespace App\Models;


class SegmentRule extends Model
{
    protected $table = 'segment_rules';
    protected $fillable = [
        'segment_id',
        'field',
        'operator',
        'value',
        'boolean',
        'sort_order',
    ];

    protected $casts = [
        'operator' => 'string',
        'boolean' => 'string'
    ];

    public function setValueAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['value'] = null;
            return;
        }

        if (is_array($value) || is_object($value) || is_scalar($value)) {
            $this->attributes['value'] = json_encode($value);
            return;
        }

        $this->attributes['value'] = $value;
    }

    public function decodedValue(): mixed
    {
        return self::decodeValue($this->value);
    }

    public static function decodeValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = $value;

        for ($i = 0; $i < 3 && is_string($decoded); $i++) {
            $next = json_decode($decoded, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }

            $decoded = $next;
        }

        return $decoded;
    }

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }
}
