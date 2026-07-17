<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Framework\Support\Crypt;

/**
 * A print/label vendor's SFTP connection details.
 *
 * Replaces hardcoded print.label_sftp / print.sftp env config —
 * each row is one vendor's server, credentials, and upload path.
 * SftpLabelExportTransport::fromVendorConnection() builds a transport
 * instance directly from one of these.
 *
 * `password` is encrypted at rest (see setPasswordAttribute /
 * getPasswordAttribute) and is never included in serialized output
 * (see $hidden) — API responses use PrintVendorConnectionResource,
 * which exposes only a `has_password` flag.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $connection_type
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string $password        Decrypted on read, encrypted on write.
 * @property string $remote_path
 * @property bool $is_active
 * @property bool $is_default
 * @property string|null $notes
 * @property \DateTime|null $last_tested_at
 * @property string|null $last_test_status
 * @property string|null $last_test_message
 */
class PrintVendorConnection extends Model
{
    protected $table = 'print_vendor_connections';

    protected $fillable = [
        'name',
        'code',
        'connection_type',
        'host',
        'port',
        'username',
        'password',
        'remote_path',
        'is_active',
        'is_default',
        'notes',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_tested_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Never serialize the raw/decrypted password.
     */
    protected $hidden = ['password'];

    // =========================================================================
    // Password encryption (mutator/accessor, since the base Model's $casts
    // system has no 'encrypted' cast type)
    // =========================================================================

    public function setPasswordAttribute(string $value): void
    {
        // Guard against double-encryption if a caller passes the already-
        // encrypted value straight through (e.g. re-saving a loaded model).
        if ($value === '' ) {
            return;
        }

        $this->attributes['password'] = Crypt::encrypt($value);
    }

    public function getPasswordAttribute(): ?string
    {
        $raw = $this->attributes['password'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return Crypt::decrypt($raw);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function type(): PrintVendorConnectionType
    {
        return PrintVendorConnectionType::from($this->connection_type);
    }

    public function supports(PrintVendorConnectionType $required): bool
    {
        return $this->type()->supports($required);
    }

    public function markTestResult(bool $success, string $message): void
    {
        $this->update([
            'last_tested_at' => now_datetime()->format('Y-m-d H:i:s'),
            'last_test_status' => $success ? 'success' : 'failed',
            'last_test_message' => $message,
        ]);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $connectionType)
    {
        return $query->where(function ($q) use ($connectionType) {
            $q->where('connection_type', $connectionType)
                ->orWhere('connection_type', PrintVendorConnectionType::Both->value);
        });
    }
}