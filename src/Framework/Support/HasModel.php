<?php

namespace App\Framework\Support;

use App\Models\Model;

trait HasModel
{
    /**
     * Cached model instance (per request)
     */
    protected ?Model $resolvedModelInstance = null;

    protected function getModelInstance(): ?Model
    {
        if ($this->resolvedModelInstance !== null) {
            return $this->resolvedModelInstance;
        }

        $modelClass = $this->getModelClass();
        $id = $this->getModelId();

        if (!$modelClass || !$id) {
            return null;
        }

        return $this->resolvedModelInstance = $modelClass::find($id);
    }

    /**
     * Resolve model class if the request declares one.
     *
     * New requests may implement static model(): string
     * Old requests continue working untouched.
     */
    protected function getModelClass(): ?string
    {
        if (method_exists(static::class, 'model')) {
            $model = static::model();

            return is_string($model) && class_exists($model)
                ? $model
                : null;
        }

        return null;
    }

    protected function getModelId(): mixed
    {
        return $this->input('id');
    }

    protected function getAbility(): string
    {
        $name = class_basename(static::class);

        return match (true) {
            str_starts_with($name, 'Create'),
            str_starts_with($name, 'Store') => 'create',
            str_starts_with($name, 'Update') => 'update',
            str_starts_with($name, 'Delete') => 'delete',
            default => 'view',
        };
    }
}