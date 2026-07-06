<?php

namespace App\Framework\Support\Config\Publishing;

use App\Framework\Support\Config\ConfigEntry;
use App\Framework\Support\Config\ConfigModel;
use App\Framework\Support\Config\Storage\ConfigDocumentRecord;
use App\Repositories\PublicContent\ConfigDocumentRepository;

/**
 * Publishes a config document, guarding against silently overwriting a
 * concurrent change (Ticket 4).
 *
 * The caller supplies the fingerprint they got when *they* loaded the
 * document. Immediately before writing, the same fingerprinting
 * algorithm is re-run against whatever is currently stored. If the two
 * don't match, someone else published a change in between, and we
 * refuse to overwrite it silently — a ConcurrentModificationException
 * is thrown carrying the current stored record.
 *
 * This is best-effort concurrency detection, not locking: there is a
 * small window between the check and the write where another publish
 * could still land. That's an accepted tradeoff per the ticket, not an
 * oversight.
 */
final class ConfigPublishService
{
    public function __construct(
        private readonly ConfigDocumentRepository $repository,
        private readonly ConfigFingerprinter $fingerprinter = new ConfigFingerprinter(),
    ) {
    }

    /**
     * Loads the current document for $type along with the fingerprint
     * the caller should hold onto and present back when publishing.
     *
     * @return array{record: ?ConfigDocumentRecord, fingerprint: string}
     */
    public function load(string $type, int $siteId): array
    {
        $record = $this->repository->findByType($type, $siteId);

        $fingerprint = $record !== null
            ? $record->fingerprint
            : $this->fingerprinter->fingerprint(new ConfigModel());

        return ['record' => $record, 'fingerprint' => $fingerprint];
    }

    /**
     * @param string $type
     * @param ConfigModel $incoming The document as the caller wants it to end up.
     * @param string $loadedFingerprint The fingerprint returned by load() when the caller fetched their working copy.
     * @param bool $force If true, publish anyway even if a concurrent change is detected (i.e. the user
     *                     acknowledged the warning and chose to overwrite).
     *
     * @throws ConcurrentModificationException if a concurrent change is detected and $force is false
     */
    /**
     * @param string $type
     * @param ConfigModel $incoming
     * @param string $loadedFingerprint
     * @param int $siteId
     * @param string|null $updatedBy
     * @param bool $force
     *
     * @throws ConcurrentModificationException if a concurrent change is detected and $force is false
     */
    public function publish(
        string $type,
        ConfigModel $incoming,
        string $loadedFingerprint,
        int $siteId,
        ?string $updatedBy = null,
        bool $force = false,
    ): ConfigDocumentRecord {
        $current = $this->repository->findByType($type, $siteId);
        $currentFingerprint = $current !== null
            ? $current->fingerprint
            : $this->fingerprinter->fingerprint(new ConfigModel());

        if (!$force && $currentFingerprint !== $loadedFingerprint) {
            // ✅ FIXED: Safely unpack the baseline model shape using a polymorphic hydrator
            $latestStoredModel = $this->hydrateModelFromPayload($current?->payload);

            throw new ConcurrentModificationException(
                new ConfigDocumentRecord(
                    type: $type,
                    model: $latestStoredModel, // Preserves baseline state accurately
                    fingerprint: $currentFingerprint,
                    updatedBy: $current->updated_by ?? null,
                    updatedAt: $current->updated_at ?? date('Y-m-d H:i:s'),
                    publishedAt: $current->published_at ?? null,
                )
            );
        }

        return $this->repository->save($type, $incoming, $siteId, $updatedBy);
    }

    /**
     * ✅ FIXED: Polymorphic hydrator stops list-vs-map dictionary format crashes
     */
    private function hydrateModelFromPayload(?array $payload): ConfigModel
    {
        if ($payload === null || empty($payload)) {
            return new ConfigModel();
        }

        if (array_is_list($payload) && isset($payload[0]['key'])) {
            $entries = [];
            foreach ($payload as $item) {
                $entries[] = new \App\Framework\Support\Config\ConfigEntry(
                    $item['key'],
                    $item['value'] ?? null,
                    $item['id'] ?? null
                );
            }
            return new ConfigModel($entries);
        }

        return ConfigModel::fromArray($payload);
    }
}