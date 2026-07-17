<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Framework\Support\Collection;
use App\Models\PrintVendorConnection;
use App\Repositories\Repository;

class PrintVendorConnectionRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PrintVendorConnection::class;
    }

    /**
     * All connections, including inactive ones — an admin listing screen
     * needs to see deactivated connections to potentially reactivate them.
     */
    public function listAll(): Collection
    {
        return PrintVendorConnection::orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * Connections eligible for a given pipeline (label/batch), including
     * ones flagged 'both'.
     */
    public function listForType(PrintVendorConnectionType $type): Collection
    {
        return PrintVendorConnection::ofType($type->value)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function findByCode(string $code): ?PrintVendorConnection
    {
        return PrintVendorConnection::where('code', $code)->first();
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = PrintVendorConnection::where('code', $code);

        if ($excludeId !== null) {
            $query = $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * The active default connection for a pipeline type. There should
     * only ever be one — PrintVendorConnectionService enforces that
     * invariant on write, this just reads whichever comes first if
     * configuration has somehow drifted.
     */
    public function findDefaultForType(PrintVendorConnectionType $type): ?PrintVendorConnection
    {
        return PrintVendorConnection::ofType($type->value)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Any other active default connection for a pipeline type, excluding
     * the given connection. Used when swapping which connection is default.
     */
    public function findOtherActiveDefault(PrintVendorConnectionType $type, int $excludingId): ?PrintVendorConnection
    {
        return PrintVendorConnection::ofType($type->value)
            ->where('is_active', true)
            ->where('is_default', true)
            ->where('id', '!=', $excludingId)
            ->first();
    }

    /**
     * Unsets is_default on every other connection compatible with the
     * given type. Persistence only — when to call this is
     * PrintVendorConnectionService's decision.
     */
    public function clearDefaultForType(PrintVendorConnectionType $type, ?int $exceptId = null): void
    {
        $query = PrintVendorConnection::ofType($type->value)
            ->where('is_default', true);

        if ($exceptId !== null) {
            $query = $query->where('id', '!=', $exceptId);
        }

        foreach ($query->get() as $connection) {
            $connection->fill(['is_default' => false]);
            $connection->save();
        }
    }
}