<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Models\ApprovalStageCondition;
use App\Enums\ApprovalConditionOperator;
use App\Support\Decimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class ApprovalConditionEvaluator
{
    /**
     * Groups are ANDed; conditions inside a group are ORed.
     *
     * @param  Collection<int, ApprovalStageCondition>  $conditions
     * @param  array<string, mixed>  $attributes
     */
    public function evaluate(Collection $conditions, array $attributes): bool
    {
        if ($conditions->isEmpty()) {
            return true;
        }

        $groups = $conditions->groupBy(fn (ApprovalStageCondition $condition) => $condition->logical_group ?: 1);

        foreach ($groups as $group) {
            if (! $group->contains(fn (ApprovalStageCondition $condition) => $this->matches($condition, $attributes))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function matches(ApprovalStageCondition $condition, array $attributes): bool
    {
        if (! Arr::has($attributes, $condition->field)) {
            return false;
        }

        $actual = Arr::get($attributes, $condition->field);
        $expected = $condition->value;

        if ($actual === null) {
            return false;
        }

        return match ($condition->operator) {
            ApprovalConditionOperator::Equals => $this->equals($actual, $expected),
            ApprovalConditionOperator::NotEquals => ! $this->equals($actual, $expected),
            ApprovalConditionOperator::In => $this->inList($actual, $expected),
            ApprovalConditionOperator::NotIn => ! $this->inList($actual, $expected),
            ApprovalConditionOperator::GreaterThan => $this->isNumericPair($actual, $expected)
                && Decimal::compare((string) $actual, (string) $expected, 6) === 1,
            ApprovalConditionOperator::LessThan => $this->isNumericPair($actual, $expected)
                && Decimal::compare((string) $actual, (string) $expected, 6) === -1,
            ApprovalConditionOperator::Between => $this->between($actual, $expected),
        };
    }

    private function equals(mixed $actual, mixed $expected): bool
    {
        if (is_array($expected)) {
            return $this->inList($actual, $expected);
        }

        if ($this->isNumericPair($actual, $expected)) {
            return Decimal::compare((string) $actual, (string) $expected, 6) === 0;
        }

        return (string) $actual === (string) $expected;
    }

    private function inList(mixed $actual, mixed $expected): bool
    {
        if (! is_array($expected)) {
            $expected = [$expected];
        }

        foreach ($expected as $candidate) {
            if ($this->equalsWithoutList($actual, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function equalsWithoutList(mixed $actual, mixed $expected): bool
    {
        if ($this->isNumericPair($actual, $expected)) {
            return Decimal::compare((string) $actual, (string) $expected, 6) === 0;
        }

        return (string) $actual === (string) $expected;
    }

    private function between(mixed $actual, mixed $expected): bool
    {
        if (! is_array($expected) || count($expected) !== 2) {
            return false;
        }

        [$min, $max] = array_values($expected);

        if (! $this->isNumericPair($actual, $min) || ! $this->isNumericPair($actual, $max)) {
            return false;
        }

        return Decimal::compare((string) $actual, (string) $min, 6) >= 0
            && Decimal::compare((string) $actual, (string) $max, 6) <= 0;
    }

    private function isNumericPair(mixed $actual, mixed $expected): bool
    {
        return is_numeric($actual) && is_numeric($expected);
    }
}
