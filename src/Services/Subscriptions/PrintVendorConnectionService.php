<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\PrintVendorConnection;
use App\Repositories\Subscriptions\PrintVendorConnectionRepository;
use InvalidArgumentException;
use phpseclib3\Net\SFTP;
use Throwable;

/**
 * Orchestrates PrintVendorConnection CRUD and enforces the one invariant
 * that spans multiple rows: at most one active default connection per
 * pipeline type (label / batch).
 *
 * Field-level validation happens in the FormRequest before this service
 * is ever called — this class only concerns itself with cross-row
 * business rules, password handling, and connection testing.
 */
class PrintVendorConnectionService
{
    private const TEST_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly PrintVendorConnectionRepository $repository,
    ) {
    }

    public function list(): Collection
    {
        return $this->repository->listAll();
    }

    public function listForType(PrintVendorConnectionType $type): Collection
    {
        return $this->repository->listForType($type);
    }

    public function find(int $id): ?PrintVendorConnection
    {
        return $this->repository->find($id);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function create(array $data): PrintVendorConnection
    {
        if ($this->repository->codeExists($data['code'])) {
            throw new InvalidArgumentException("A vendor connection with code '{$data['code']}' already exists.");
        }

        $type = PrintVendorConnectionType::from($data['connection_type']);

        if (($data['is_default'] ?? false) === true) {
            // 2 writes (clear existing default + create) -> transaction.
            return Database::runTransaction(function () use ($type, $data) {
                $this->repository->clearDefaultForType($type);

                return $this->repository->create($data);
            });
        }

        return $this->repository->create($data);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function update(int $id, array $data): PrintVendorConnection
    {
        $connection = $this->repository->find($id);

        if (!$connection) {
            throw new InvalidArgumentException('Print vendor connection not found.');
        }

        if (isset($data['code']) && $this->repository->codeExists($data['code'], $id)) {
            throw new InvalidArgumentException("A vendor connection with code '{$data['code']}' already exists.");
        }

        // Blank password on update means "leave unchanged" — the UI never
        // shows the existing password back to the admin, so an empty
        // field is not a deliberate "clear the password" action.
        if (array_key_exists('password', $data) && $data['password'] === '') {
            unset($data['password']);
        }

        $type = PrintVendorConnectionType::from($data['connection_type'] ?? $connection->connection_type);
        $becomingDefault = ($data['is_default'] ?? false) === true && !$connection->is_default;

        if ($becomingDefault) {
            // 2 writes (clear existing default + update this one) -> transaction.
            return Database::runTransaction(function () use ($id, $type, $data) {
                $this->repository->clearDefaultForType($type, $id);

                return $this->repository->update($id, $data);
            });
        }

        $updated = $this->repository->update($id, $data);

        if (!$updated) {
            throw new InvalidArgumentException('Print vendor connection not found.');
        }

        return $updated;
    }

    /**
     * Soft delete: sets active = false rather than physically removing the
     * row, so historical LabelRun/PrintBatch records whose `transport`
     * identifier refers back to this vendor still make sense in context.
     *
     * Refuses to deactivate a connection that is the only active default
     * for its pipeline type, since that would leave label/batch generation
     * with no destination to upload to. Assign a new default first.
     *
     * @throws InvalidArgumentException
     */
    public function deactivate(int $id): PrintVendorConnection
    {
        $connection = $this->repository->find($id);

        if (!$connection) {
            throw new InvalidArgumentException('Print vendor connection not found.');
        }

        if ($connection->is_default && $connection->is_active) {
            $otherDefault = $this->repository->findOtherActiveDefault($connection->type(), $id);

            if (!$otherDefault) {
                throw new InvalidArgumentException(
                    'Cannot deactivate the only active default connection for this pipeline type. '
                    . 'Assign a new default first.'
                );
            }
        }

        $updated = $this->repository->update($id, ['is_active' => false]);

        Logger::info('Print vendor connection deactivated', ['connection_id' => $id]);

        return $updated;
    }

    /**
     * Attempt a live SFTP login (and, when possible, a directory listing
     * of the configured remote_path) to verify the stored credentials
     * actually work. Result is persisted onto the connection row so admins
     * can see "last tested" status in the listing without re-running it.
     *
     * Never throws — failures are captured in the returned result and on
     * the model, since a bad connection is an expected, actionable outcome
     * here, not an application error.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(int $id): array
    {
        $connection = $this->repository->find($id);

        if (!$connection) {
            throw new InvalidArgumentException('Print vendor connection not found.');
        }

        [$success, $message] = $this->attemptLogin($connection);

        $connection->markTestResult($success, $message);

        return ['success' => $success, 'message' => $message];
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function attemptLogin(PrintVendorConnection $connection): array
    {
        try {
            $sftp = new SFTP($connection->host, $connection->port, self::TEST_TIMEOUT_SECONDS);

            if (!$sftp->login($connection->username, $connection->password)) {
                return [false, "Authentication failed for {$connection->username}@{$connection->host}."];
            }

            if (!$sftp->is_dir($connection->remote_path)) {
                return [
                    false,
                    "Connected and authenticated, but remote path '{$connection->remote_path}' "
                    . "does not exist or is not a directory.",
                ];
            }

            return [true, 'Connection succeeded and remote path is reachable.'];
        } catch (Throwable $e) {
            return [false, 'Connection failed: ' . $e->getMessage()];
        }
    }
}