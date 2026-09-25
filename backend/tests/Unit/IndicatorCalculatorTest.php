<?php

namespace Tests\Unit;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Services\IndicatorCalculator;
use PHPUnit\Framework\TestCase;

class IndicatorCalculatorTest extends TestCase
{
    public function test_it_calculates_addition_division_percentage_and_zero_denominator(): void
    {
        $indicators = collect([
            $this->indicator(1, 'A', 'base'),
            $this->indicator(2, 'B', 'base'),
            $this->indicator(3, 'TOTAL', 'derived', ['op' => 'add', 'args' => ['A', 'B']]),
            $this->indicator(4, 'RATIO', 'derived', ['op' => 'divide', 'args' => ['A', 'B']], 2),
            $this->indicator(5, 'PERCENT', 'derived', ['op' => 'percent', 'args' => ['A', 'TOTAL']], 2),
        ]);
        $values = collect([
            $this->value(1, 10),
            $this->value(2, 0),
        ]);

        $result = (new IndicatorCalculator)->calculate($indicators, $values);

        $this->assertSame(10.0, $result[3]);
        $this->assertNull($result[4]);
        $this->assertSame(100.0, $result[5]);
    }

    public function test_nested_sums_keep_missing_values_and_zero_denominators_uncomputed(): void
    {
        $indicators = collect([
            $this->indicator(1, 'CHILD', 'base'),
            $this->indicator(2, 'SENIOR', 'base'),
            $this->indicator(3, 'WORKING', 'base'),
            $this->indicator(4, 'DEPENDENCY', 'derived', ['op' => 'percent', 'args' => [
                ['op' => 'add', 'args' => ['CHILD', 'SENIOR']],
                ['op' => 'add', 'args' => ['WORKING']],
            ]], 2),
        ]);
        $calculator = new IndicatorCalculator;

        $this->assertSame(50.0, $calculator->calculate($indicators, collect([$this->value(1, 10), $this->value(2, 5), $this->value(3, 30)]))[4]);
        $this->assertNull($calculator->calculate($indicators, collect([$this->value(1, 10), $this->value(3, 30)]))[4]);
        $this->assertNull($calculator->calculate($indicators, collect([$this->value(1, 10), $this->value(2, 5), $this->value(3, 0)]))[4]);
    }

    private function indicator(int $id, string $code, string $kind, ?array $formula = null, int $decimalPlaces = 0): Indicator
    {
        $indicator = new Indicator([
            'code' => $code,
            'value_kind' => $kind,
            'formula' => $formula,
            'decimal_places' => $decimalPlaces,
        ]);
        $indicator->id = $id;

        return $indicator;
    }

    private function value(int $indicatorId, float $number): IndicatorValue
    {
        $value = new IndicatorValue(['numeric_value' => $number, 'not_applicable' => false]);
        $value->indicator_id = $indicatorId;

        return $value;
    }
}
