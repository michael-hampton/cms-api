<?php

namespace App\Framework\Authorization;

use App\Framework\Exceptions\AuthorizationException;

class Gate
{
    protected static array $abilities = [];
    private static array $policies = [];

    public static function define(string $ability, callable $callback): void
    {
        self::$abilities[$ability] = $callback;
    }

    public static function policy(string $class, string $policy): void
    {
        self::$policies[$class] = $policy;
    }

    public static function allows(string $ability, $arguments = []): bool
    {
        return self::check($ability, $arguments);
    }

    public static function denies(string $ability, $arguments = null): bool
    {
        return !self::allows($ability, $arguments);
    }

    public static function check(string $ability, $arguments = []): bool
    {
        //$user = Auth::user();
        $user = $arguments[0];

        // Check policies first
        if (!empty($arguments)) {
            // Corrected line: access the model at index 1
            $model = is_array($arguments) ? $arguments[1] : $arguments;

            $modelClass = null;

            if (is_object($model)) {
                // It's an instance, get the class name
                $modelClass = get_class($model);
            } elseif (is_string($model)) {
                // It's a string, assume it's a class name
                $modelClass = $model;
            }

            // Handle simple class names by adding the namespace
            if ($modelClass && !class_exists($modelClass)) {
                $namespacedClass = "App\\Models\\{$modelClass}";
                if (class_exists($namespacedClass)) {
                    $modelClass = $namespacedClass;
                }
            }

            if ($modelClass && isset(self::$policies[$modelClass])) {
                $policyClass = self::$policies[$modelClass];
                $policy = new $policyClass();

                if (method_exists($policy, $ability)) {
                    // Pass the user and a new instance of the model class to the policy method if the original was a string
                    if (is_string($model) && class_exists($modelClass)) {
                        $modelInstance = new $modelClass();
                        return $policy->$ability($user, $modelInstance);
                    }

                    // If it's an object, pass the original object
                    return $policy->$ability($user, $model);
                }
            }
        }

        // Check defined abilities
        if (isset(self::$abilities[$ability])) {
            $callback = self::$abilities[$ability];
            return $callback($user, $arguments);
        }

        return false;
    }

    public static function authorize(string $ability, $arguments = []): void
    {
        if (self::denies($ability, $arguments)) {
            throw new AuthorizationException("Access denied for ability: {$ability}");
        }
    }
}
