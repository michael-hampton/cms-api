<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add your authorization logic here
    }

    public function rules(): array
    {
        return [
            'menu_id' => 'required|exists:menus,id',
            'parent_id' => 'exists:menu_items,id',
            'label' => 'required|string|max:255',
            'target_type' => 'required|in:page,category,custom,external',
            'target_id' => 'string|max:255',
            'custom_url' => 'max:500',
            'css_class' => 'string|max:255',
            'icon' => 'string|max:255',
            'attributes' => 'array',
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'menu_id.exists' => 'Selected menu does not exist.',
            'parent_id.exists' => 'Selected parent item does not exist.',
            'label.required' => 'Menu item label is required.',
            'target_type.required' => 'Target type is required.',
            'target_type.in' => 'Target type must be page, category, custom, or external.',
            'custom_url.url' => 'Please provide a valid URL.',
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {

                $targetType = $this->target_type ?? $this->getOriginalTargetType();

                // Validate target_id is required for page/category types
                if (in_array($targetType, ['page', 'category']) && empty($this->target_id)) {
                    $request->validator->errorCollection()->merge(['target_id' => 'Target ID is required for ' . $targetType . ' type.']);
                }

                // Validate custom_url is required for custom/external types
                if (in_array($targetType, ['custom', 'external']) && empty($this->custom_url)) {
                    $request->validator->errorCollection()->merge(['custom_url' => 'Custom URL is required for ' . $targetType . ' type.']);;
                }

                // Validate no circular reference (exclude current item)
                if ($request->get('parent_id')) {
                    $this->validateNoCircularReference($request->validator, $request);
                }

                // Validate parent belongs to same menu
                if ($request->get('parent_id') && ($this->menu_id ?? $this->getOriginalMenuId())) {
                    $menuId = $this->menu_id ?? $this->getOriginalMenuId();
                    $parent = \App\Models\MenuItem::find($request->get('parent_id'));
                    if ($parent && $parent->menu_id !== $menuId) {
                        $request->validator->errorCollection()->merge(['parent_id' => 'Parent item must belong to the same menu.']);
                    }
                }
            }
        ];
    }

    private function validateNoCircularReference($validator, $request): void
    {
        $currentItemId = $this->route('id');
        $parentId = $request->get('parent_id');
        $visited = [];

        while ($parentId && !in_array($parentId, $visited)) {
            if ($parentId == $currentItemId) {
                $validator->errors()->add('parent_id', 'Cannot create circular reference in menu hierarchy.');
                return;
            }

            $visited[] = $parentId;
            $parent = \App\Models\MenuItem::find($parentId);

            if (!$parent) {
                break;
            }

            $parentId = $parent->parent_id;
        }
    }

    private function getOriginalTargetType(): ?string
    {
        $menuItem = \App\Models\MenuItem::find($this->route('id'));
        return $menuItem?->target_type;
    }

    private function getOriginalMenuId(): ?int
    {
        $menuItem = \App\Models\MenuItem::find($this->route('id'));
        return $menuItem?->menu_id;
    }
}