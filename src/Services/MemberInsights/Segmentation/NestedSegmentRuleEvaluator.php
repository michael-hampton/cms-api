<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Enums\Member\SegmentRuleBoolean;
use App\Enums\Member\SegmentRuleOperator;
use App\Models\Segment;
use App\Models\SegmentRuleGroup;

class NestedSegmentRuleEvaluator
{
    public function evaluate(array $data, Segment $segment): bool
    {
        $groups = $this->value($segment, 'groups');

        if ($groups === null || $groups->isEmpty()) {
            return $this->evaluateFlatRules($data, $this->value($segment, 'rules') ?? collect());
        }

        $rootGroups = $groups
            ->filter(fn ($group) => $this->value($group, 'parent_id') === null)
            ->sortBy(fn ($group) => $this->value($group, 'sort_order') ?? 0)
            ->values();

        return $this->combineGroups($data, $rootGroups);
    }

    private function combineGroups(array $data, $groups): bool
    {
        if ($groups->isEmpty()) {
            return false;
        }

        $result = null;

        foreach ($groups as $group) {
            $groupResult = $this->evaluateGroup($data, $group);

            if ($result === null) {
                $result = $groupResult;
                continue;
            }

            $boolean = $this->boolean($this->value($group, 'boolean'));

            $result = $boolean === SegmentRuleBoolean::AND
                ? $result && $groupResult
                : $result || $groupResult;
        }

        return $result ?? false;
    }

    private function evaluateGroup(array $data, SegmentRuleGroup $group): bool
    {
        $rules = $this->value($group, 'rules') ?? collect();
        $children = $this->value($group, 'children') ?? collect();

        if ($rules->isEmpty() && $children->isEmpty()) {
            return false;
        }

        $result = null;

        foreach ($rules->sortBy(fn ($rule) => $this->value($rule, 'sort_order') ?? 0) as $rule) {
            $match = $this->evaluateRule($data, $rule);

            if ($result === null) {
                $result = $match;
                continue;
            }

            $boolean = $this->boolean($this->value($rule, 'boolean'));

            $result = $boolean === SegmentRuleBoolean::AND
                ? $result && $match
                : $result || $match;
        }

        foreach ($children->sortBy(fn ($child) => $this->value($child, 'sort_order') ?? 0) as $child) {
            $childResult = $this->evaluateGroup($data, $child);

            if ($result === null) {
                $result = $childResult;
                continue;
            }

            $boolean = $this->boolean($this->value($child, 'boolean'));

            $result = $boolean === SegmentRuleBoolean::AND
                ? $result && $childResult
                : $result || $childResult;
        }

        return $result ?? false;
    }

    private function evaluateFlatRules(array $data, $rules): bool
    {
        if ($rules->isEmpty()) {
            return false;
        }

        $result = null;

        foreach ($rules->sortBy(fn ($rule) => $this->value($rule, 'sort_order') ?? 0) as $rule) {
            $match = $this->evaluateRule($data, $rule);

            if ($result === null) {
                $result = $match;
                continue;
            }

            $boolean = $this->boolean($this->value($rule, 'boolean'));

            $result = $boolean === SegmentRuleBoolean::AND
                ? $result && $match
                : $result || $match;
        }

        return $result ?? false;
    }

    private function evaluateRule(array $data, mixed $rule): bool
    {
        $actual = data_get($data, $this->value($rule, 'field'));
        $expected = $this->value($rule, 'value');

        $operator = $this->value($rule, 'operator');

        if ($operator instanceof SegmentRuleOperator) {
            return $operator->compare($actual, $expected);
        }

        $operator = SegmentRuleOperator::tryFrom((string) $operator);

        return $operator?->compare($actual, $expected) ?? false;
    }

    private function boolean(mixed $boolean): SegmentRuleBoolean
    {
        if ($boolean instanceof SegmentRuleBoolean) {
            return $boolean;
        }

        return SegmentRuleBoolean::tryFrom((string) $boolean)
            ?? SegmentRuleBoolean::AND;
    }

    private function value(mixed $target, string $key): mixed
    {
        if (is_array($target)) {
            return $target[$key] ?? null;
        }

        if (! is_object($target)) {
            return null;
        }

        // 1. Safe-check: If it's an Eloquent model, grab raw attributes to prevent mock lazy-loading
        if (method_exists($target, 'getAttributes')) {
            $attributes = $target->getAttributes();
            if (array_key_exists($key, $attributes)) {
                return $attributes[$key];
            }
        }

        // 2. Safe-check: Check already loaded relations
        if (method_exists($target, 'getRelations')) {
            $relations = $target->getRelations();
            if (array_key_exists($key, $relations)) {
                return $relations[$key];
            }
        }

        // 3. Fallback to direct public properties (for standard objects like rule tokens)
        if (isset($target->{$key})) {
            return $target->{$key};
        }

        // 4. Fallback to default Eloquent getters
        if (method_exists($target, 'getAttribute')) {
            $value = $target->getAttribute($key);

            if ($value !== null) {
                return $value;
            }
        }

        if (method_exists($target, 'getRelationValue')) {
            return $target->getRelationValue($key);
        }

        return null;
    }
}