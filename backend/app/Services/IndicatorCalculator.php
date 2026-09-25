<?php

namespace App\Services;

use App\Models\Indicator;
use Illuminate\Support\Collection;

class IndicatorCalculator
{
    public function calculate(Collection $indicators, Collection $values): array
    {
        $indicatorsByCode = $indicators->keyBy('code');
        $valuesByIndicator = $values->keyBy('indicator_id');
        $results = [];

        foreach ($indicators->where('value_kind', 'derived') as $indicator) {
            $results[$indicator->id] = $this->evaluate(
                $indicator,
                $indicatorsByCode,
                $valuesByIndicator,
                $results,
                [],
            );
        }

        return $results;
    }

    private function evaluate(
        Indicator $indicator,
        Collection $indicators,
        Collection $values,
        array &$results,
        array $stack,
    ): ?float {
        if (array_key_exists($indicator->id, $results)) {
            return $results[$indicator->id];
        }
        if (in_array($indicator->id, $stack, true)) {
            return null;
        }

        $result = $this->evaluateFormula($indicator->formula, $indicators, $values, $results, [...$stack, $indicator->id]);

        return $result === null ? null : round($result, $indicator->decimal_places);
    }

    private function evaluateFormula(?array $formula, Collection $indicators, Collection $values, array &$results, array $stack): ?float
    {
        if (! is_array($formula) || ! isset($formula['op'], $formula['args']) || ! is_array($formula['args'])) {
            return null;
        }

        $arguments = [];
        foreach ($formula['args'] as $code) {
            if (is_array($code)) {
                $argument = $this->evaluateFormula($code, $indicators, $values, $results, $stack);
                if ($argument === null) {
                    return null;
                }
                $arguments[] = $argument;

                continue;
            }
            if (! is_string($code)) {
                return null;
            }
            $dependency = $indicators->get($code);
            if (! $dependency) {
                return null;
            }
            if ($dependency->value_kind === 'derived') {
                $argument = $this->evaluate($dependency, $indicators, $values, $results, $stack);
            } else {
                $value = $values->get($dependency->id);
                $argument = $value && ! $value->not_applicable && $value->numeric_value !== null
                    ? (float) $value->numeric_value
                    : null;
            }
            if ($argument === null) {
                return null;
            }
            $arguments[] = $argument;
        }

        $result = match ($formula['op']) {
            'add' => array_sum($arguments),
            'divide' => ($arguments[1] ?? 0.0) == 0.0 ? null : $arguments[0] / $arguments[1],
            'percent' => ($arguments[1] ?? 0.0) == 0.0 ? null : $arguments[0] / $arguments[1] * 100,
            default => null,
        };

        return $result;
    }
}
