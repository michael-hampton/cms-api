<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Collection;
use App\Models\MenuItem;

class ReorderMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add your authorization logic here
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:menu_items,id',
            'items.*.sort_order' => 'required|integer|min:0',
            'items.*.parent_id' => 'integer|exists:menu_items,id',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Items array is required.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item is required.',
            'items.*.id.required' => 'Each item must have an ID.',
            'items.*.id.exists' => 'One or more menu items do not exist.',
            'items.*.sort_order.required' => 'Each item must have a sort order.',
            'items.*.sort_order.integer' => 'Sort order must be an integer.',
            'items.*.sort_order.min' => 'Sort order must be at least 0.',
            'items.*.parent_id.exists' => 'One or more parent items do not exist.',
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                // Validate all items belong to the same menu
                $items = $request->get('items');
                $menuIds = [];

                foreach ($items as $itemData) {
                    $menuItem = MenuItem::find($itemData['id']);
                    if ($menuItem) {
                        $menuIds[] = $menuItem->menu_id;
                    }
                }

                $uniqueMenuIds = array_unique($menuIds);
                if (count($uniqueMenuIds) > 1) {
                    $request->validator->errorCollection()->merge(['items' => 'All items must belong to the same menu.']);
                }

                // Validate no circular references
                foreach ($items as $index => $itemData) {
                    if (isset($itemData['parent_id'])) {
                        $this->validateItemCircularReference($request->validator, $itemData, $items, "items.{$index}.parent_id");
                    }
                }
            }
        ];
    }

    private function validateItemCircularReference($validator, $currentItem, $allItems, $errorKey): void
    {
        $currentId = $currentItem['id'];
        $parentId = $currentItem['parent_id'] ?? null;
        $visited = [];

        while ($parentId && !in_array($parentId, $visited)) {
            if ($parentId == $currentId) {
                $validator->errorCollection()->merge([$errorKey => 'Cannot create circular reference in menu hierarchy.']);
                return;
            }

            $visited[] = $parentId;

            // Check if parent is in the reorder list
            $parentInList = (new Collection($allItems))->firstWhere('id', $parentId);
            if ($parentInList) {
                $parentId = $parentInList['parent_id'] ?? null;
            } else {
                // Check database
                $parent = MenuItem::find($parentId);
                $parentId = $parent?->parent_id;
            }
        }
    }
}