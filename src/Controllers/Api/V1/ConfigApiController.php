<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Config\ConfigEntry;
use App\Framework\Support\Config\ConfigJsonDraft;
use App\Framework\Support\Config\ConfigModel;
use App\Framework\Support\Config\ConfigValidator;
use App\Framework\Support\Config\Publishing\ConcurrentModificationException;
use App\Framework\Support\Config\Publishing\ConfigConflictResolver;
use App\Framework\Support\Config\Publishing\ConfigPublishService;
use App\Framework\Support\Config\Publishing\ConflictResolutionChoice;
use App\Framework\Support\SiteContext;

/**
 * Request-handling logic behind the configuration editor API.
 * Properly accounts for the object differentiation between database models and service DTOs.
 */
final class ConfigApiController extends Controller
{
    private const array CONFLICT_AWARE_TYPES = ['public_content', 'settings'];

    public function __construct(
        private readonly ConfigPublishService $publishService,
        private readonly ConfigValidator $validator = new ConfigValidator(),
        private readonly ConfigConflictResolver $conflictResolver = new ConfigConflictResolver(),
    ) {
        parent::__construct();
    }

    /**
     * GET /api/v1/{site_id}/content/config/{type}
     */
    public function show(Request $request): JsonResponse
    {
        $type = $request->route('type');

        // $record here is the raw database Eloquent Model ('ConfigDocument')
        ['record' => $record, 'fingerprint' => $fingerprint] = $this->publishService->load($type, SiteContext::getId());

        // Process through the polymorphic shape-aware hydrator to safely handle the raw array
        $configModel = $this->hydrateModelFromPayload($record?->payload);

        return $this->resourceResponse([
            'type' => $type,
            'entries' => $configModel->toSerializableArray(),
            'fingerprint' => $fingerprint,
        ]);
    }

    /**
     * PUT /api/v1/{site_id}/content/config/{type}
     */
    public function update(Request $request): JsonResponse
    {
        $type              = $request->route('type');
        $rawJson           = $request->input('rawJson');
        $loadedFingerprint = $request->input('loadedFingerprint');
        $updatedBy         = $request->input('updatedBy');

        $draft = ConfigJsonDraft::fromJsonText($rawJson, $this->validator);

        if (!$draft->isValidConfiguration()) {
            return $this->resourceResponse([
                'status' => 'invalid',
                'syntaxError' => $draft->syntaxError,
                'validationErrors' => array_map(
                    static fn ($e) => $e->toArray(),
                    $draft->validationErrors,
                ),
            ]);
        }

        try {
            // $record returned here is a ConfigDocumentRecord DTO
            $record = $this->publishService->publish($type, $draft->model, $loadedFingerprint, SiteContext::getId(), $updatedBy);
        } catch (ConcurrentModificationException $e) {
            return $this->resourceResponse($this->conflictResponse($type, $draft->model, $loadedFingerprint, $e));
        }

        // ✅ FIXED: Read directly from the DTO's ->model property. No fromArray mapping needed!
        return $this->resourceResponse([
            'success' => true,
            'status' => 'saved',
            'entries' => $record->model->toSerializableArray(),
            'fingerprint' => $record->fingerprint ?? $loadedFingerprint,
        ]);
    }

    /**
     * POST /api/v1/{site_id}/content/config/{type}/publish
     */
    public function publishWithResolutions(Request $request): JsonResponse
    {
        $type        = $request->route('type');
        $baseData    = $request->input('base', []);
        $mineData    = $request->input('mine', []);
        $resolutions = $request->input('resolutions', []);
        $updatedBy   = $request->input('updatedBy');

        if (!in_array($type, self::CONFLICT_AWARE_TYPES, true)) {
            throw new \LogicException(sprintf('Selective publish is not enabled for document type "%s"', $type));
        }

        $baseEntries = [];
        foreach ($baseData as $entry) {
            $baseEntries[] = new ConfigEntry($entry['key'], $entry['value'], $entry['id'] ?? null);
        }
        $base = new ConfigModel($baseEntries);

        $mineEntries = [];
        foreach ($mineData as $entry) {
            $mineEntries[] = new ConfigEntry($entry['key'], $entry['value'], $entry['id'] ?? null);
        }
        $mine = new ConfigModel($mineEntries);

        // $latestRecord here is the database Eloquent Model from the load() method
        ['record' => $latestRecord, 'fingerprint' => $loadedFingerprint] = $this->publishService->load($type, SiteContext::getId());
        $latest = $this->hydrateModelFromPayload($latestRecord?->payload);

        $choices = [];
        foreach ($resolutions as $key => $resolution) {
            $choices[$key] = match ($resolution['choice']) {
                'keep_mine'   => ConflictResolutionChoice::keepMine(),
                'keep_theirs' => ConflictResolutionChoice::keepTheirs(),
                'edited'      => array_key_exists('value', $resolution)
                    ? ConflictResolutionChoice::edited($resolution['value'])
                    : ConflictResolutionChoice::editedDelete(),
                default => throw new \InvalidArgumentException(sprintf('Unknown resolution choice "%s"', $resolution['choice'])),
            };
        }

        $rebuilt = $this->conflictResolver->buildPublishableModel($base, $mine, $latest, $choices);

        // $record returned here is a ConfigDocumentRecord DTO
        $record  = $this->publishService->publish($type, $rebuilt, $loadedFingerprint, SiteContext::getId(), $updatedBy, force: true);

        // ✅ FIXED: Read directly from the save DTO's ->model property
        return $this->resourceResponse([
            'success' => true,
            'status' => 'saved',
            'entries' => $record->model->toSerializableArray(),
            'fingerprint' => $record->fingerprint ?? $loadedFingerprint,
        ]);
    }

    /**
     * Polymorphic Shape Decoder
     * Inspects data structures to correctly identify sequential lists versus object dictionaries.
     */
    private function hydrateModelFromPayload(?array $payload): ConfigModel
    {
        if ($payload === null || empty($payload)) {
            return new ConfigModel();
        }

        if (array_is_list($payload) && isset($payload[0]['key'])) {
            $entries = [];
            foreach ($payload as $item) {
                $entries[] = new ConfigEntry(
                    $item['key'],
                    $item['value'] ?? null,
                    $item['id'] ?? null
                );
            }
            return new ConfigModel($entries);
        }

        return ConfigModel::fromArray($payload);
    }

    private function conflictResponse(string $type, ConfigModel $mine, string $loadedFingerprint, ConcurrentModificationException $e): array
    {
        $latest = $e->currentRecord->model;

        $response = [
            'status' => 'conflict',
            'latestFingerprint' => $e->currentRecord->fingerprint,
        ];

        if (in_array($type, self::CONFLICT_AWARE_TYPES, true)) {
            $response['diff'] = array_map(
                static fn ($d) => $d->toArray(),
                $this->conflictResolver->diff($latest, $mine, $latest),
            );
        }

        return $response;
    }
}