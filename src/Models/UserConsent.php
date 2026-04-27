<?php

namespace App\Models;

/**
 * UserConsent — stores a contributor's notification channel preferences.
 *
 * Mirrors MemberConsent but is keyed on users.id instead of members.id.
 * Each row is uniquely identified by (user_id, consent_type_id, channel).
 *
 * @property int $id
 * @property int $user_id
 * @property int $consent_type_id
 * @property string $channel          email|in_app|push
 * @property bool $is_granted
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property mixed $granted_at
 * @property mixed $revoked_at
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class UserConsent extends Model
{
    protected $table = 'user_consents';

    protected $fillable = [
        'user_id',
        'consent_type_id',
        'channel',
        'is_granted',
        'ip_address',
        'user_agent',
        'granted_at',
        'revoked_at',
    ];

    protected $casts = [
        'is_granted' => 'boolean',
    ];

    public function isActive(): bool
    {
        return $this->is_granted === true && $this->revoked_at === null;
    }

    public function consentType()
    {
        return $this->belongsTo(ConsentType::class, 'consent_type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}