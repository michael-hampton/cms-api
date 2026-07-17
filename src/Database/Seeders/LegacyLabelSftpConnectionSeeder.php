<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Models\PrintVendorConnection;

/**
 * One-off migration helper: if the legacy PRINT_LABEL_SFTP_* / PRINT_SFTP_*
 * env vars are populated (see config/print.php 'label_sftp', deprecated),
 * seed them into a single default PrintVendorConnection row so existing
 * deployments keep working the moment this ships, without an admin having
 * to manually re-enter credentials from a config file into the new admin
 * screen before the next label run fires.
 *
 * Idempotent: does nothing if a connection with the reserved code
 * 'legacy-env-import' already exists, or if the legacy env vars are unset.
 *
 * This is a stopgap, not the long-term source of truth — once migrated,
 * admins should manage this vendor (and add any others) via
 * PrintVendorConnectionController and can then remove the legacy env vars.
 */
class LegacyLabelSftpConnectionSeeder
{
    private const LEGACY_CODE = 'legacy-env-import';

    public function run(): void
    {
        if (PrintVendorConnection::where('code', self::LEGACY_CODE)->exists()) {
            return;
        }

        $host = config('print.label_sftp.host');

        if (empty($host)) {
            return;
        }

        PrintVendorConnection::create([
            'name' => 'Legacy env-configured vendor',
            'code' => self::LEGACY_CODE,
            'connection_type' => PrintVendorConnectionType::Label->value,
            'host' => (string)$host,
            'port' => (int)config('print.label_sftp.port', 22),
            'username' => (string)config('print.label_sftp.user'),
            'password' => (string)config('print.label_sftp.password'),
            'remote_path' => (string)config('print.label_sftp.path', '/labels'),
            'is_active' => true,
            'is_default' => true,
            'notes' => 'Auto-imported from PRINT_LABEL_SFTP_* / PRINT_SFTP_* env vars on deploy. '
                . 'Please verify and rename via the admin screen.',
        ]);
    }
}