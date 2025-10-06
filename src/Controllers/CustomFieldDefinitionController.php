<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Http\JsonResponse;
use App\Framework\Validation\Validator;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\CustomFieldDefinitionRepository;
use App\Repositories\PageCustomFieldRepository;
use App\Requests\CreateCustomFieldDefinitionRequest;
use App\Requests\UpdateCustomFieldDefinitionRequest;
use Exception;

class CustomFieldDefinitionController extends Controller
{
    private $pageCustomFieldRepository;

    private $customFieldDefinitionRepository;
    private $validator;

    public function __construct(
        PageCustomFieldRepository $pageCustomFieldRepository,
        CustomFieldDefinitionRepository $customFieldDefinitionRepository,
        Validator $validator)
    {
        $this->pageCustomFieldRepository = $pageCustomFieldRepository;
        $this->customFieldDefinitionRepository = $customFieldDefinitionRepository;
        $this->validator = $validator;
        parent::__construct();
    }

    public function index(string $siteName): JsonResponse
    {
        try {
            $siteId = Site::resolveSite($siteName);
            $fields = $this->customFieldDefinitionRepository->getActive($siteId);
            return $this->jsonResponse(['fields' => $fields]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function grouped(string $siteName): JsonResponse
    {
        try {
            $grouped = $this->customFieldDefinitionRepository->getGroupedFields($siteName);
            $result = [];
            foreach ($grouped as $group => $fields) {
                $result[$group] = array_map(fn($field) => $field->toArray(), $fields);
            }
            return $this->jsonResponse(['groups' => $result]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $field = is_numeric($id)
                ? $this->customFieldDefinitionRepository->find((int)$id)
                : $this->customFieldDefinitionRepository->findByKey($id);

            if (!$field) {
                return $this->errorResponse('Custom field definition not found', 404);
            }

            return $this->jsonResponse(['field' => $field->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateCustomFieldDefinitionRequest $request): JsonResponse
    {
        try {
            $field = $this->customFieldDefinitionRepository->createField($request->validated());
            return $this->jsonResponse(['field' => $field->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateCustomFieldDefinitionRequest $request): JsonResponse
    {
        try {
            $field = $this->customFieldDefinitionRepository->update($id, $request->validated());
            if (!$field) {
                return $this->errorResponse('Custom field definition not found', 404);
            }
            return $this->jsonResponse(['field' => $field->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->customFieldDefinitionRepository->delete($id);
            if (!$result) {
                return $this->errorResponse('Custom field definition not found', 404);
            }

            return $this->successResponse('Custom field definition deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function required(): JsonResponse
    {
        try {
            $fields = $this->customFieldDefinitionRepository->getRequired();
            return $this->jsonResponse(['fields' => $fields->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function searchable(): JsonResponse
    {
        try {
            $fields = $this->customFieldDefinitionRepository->getSearchableFields();
            return $this->jsonResponse(['fields' => $fields->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get custom fields and their values for a specific page
     *
     * @param int $pageId
     * @return JsonResponse
     */
    public function getCustomFields(int $pageId): JsonResponse
    {
        try {
            $page = Page::where('id', $pageId)->first();

            if(empty($page)){
                return $this->jsonResponse(['Page not found', [], 500]);
            }

            // Get all active custom field definitions
            $fieldDefinitions = $this->customFieldDefinitionRepository->getActive($page->site_id);

            // Get the page's custom field values
            $pageFields = $this->pageCustomFieldRepository->getPageCustomFields($pageId);

            if(empty($pageFields)){
                return $this->jsonResponse([
                    'fields' => [],
                    'values' => []
                ]);
            }

            // Create a map of field values by definition ID
            $valueMap = [];
            foreach ($pageFields as $pageField) {
                $valueMap[$pageField->custom_field_definition_id] = $pageField->getTypedValue();
            }

            // Build the response with all field definitions and their values
            $fields = [];
            foreach ($fieldDefinitions as $definition) {
                $fieldData = $definition->toArray();
                $fieldData['has_value'] = isset($valueMap[$definition->id]);
                $fieldData['value'] = $valueMap[$definition->id] ?? null;
                $fields[] = $fieldData;
            }

            // Also create a simple values map for easier consumption
            $values = [];
            foreach ($pageFields as $pageField) {
                $values[$pageField->custom_field_definition_id] = $pageField->getTypedValue();
            }

            return $this->jsonResponse([
                'fields' => $fields,
                'values' => $values
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get custom fields grouped by group_name for a specific page
     *
     * @param int $pageId
     * @return JsonResponse
     */
    public function getCustomFieldsGrouped(int $pageId): JsonResponse
    {
        try {
            $grouped = $this->pageCustomFieldRepository->getPageCustomFieldsGrouped($pageId);

            return $this->jsonResponse([
                'groups' => $grouped
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update custom fields for a page
     *
     * @param int $pageId
     * @return JsonResponse
     */
    public function updateCustomFields(int $pageId, Request $request): JsonResponse
    {
        try {
            $fields = $request->input('fields', []);
            $siteId = $request->input('site_id') ?? config('app.default_site_id');;

            $this->pageCustomFieldRepository->syncCustomFields($pageId, $fields, $siteId);

            return $this->successResponse('Custom fields updated successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}