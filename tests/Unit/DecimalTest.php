<?php

use App\Support\Decimal;

it('adds decimal values without floating point drift', function () {
    expect(Decimal::add('0.1', '0.2', 4))->toBe('0.3000')
        ->and(Decimal::add('1000.005', '2000.005', 3))->toBe('3000.010');
});

it('subtracts and multiplies decimal values', function () {
    expect(Decimal::subtract('10.5000', '4.2500', 4))->toBe('6.2500')
        ->and(Decimal::multiply('2.5', '4', 2))->toBe('10.00');
});

it('compares decimal values', function () {
    expect(Decimal::compare('10.0001', '10.0000', 4))->toBe(1)
        ->and(Decimal::compare('10.0000', '10.0000', 4))->toBe(0)
        ->and(Decimal::compare('9.9999', '10.0000', 4))->toBe(-1);
});

it('detects sign correctly', function () {
    expect(Decimal::isPositive('0.0001', 4))->toBeTrue()
        ->and(Decimal::isPositive('0.0000', 4))->toBeFalse()
        ->and(Decimal::isZero('0.0000', 4))->toBeTrue()
        ->and(Decimal::isNegative('-0.0001', 4))->toBeTrue()
        ->and(Decimal::isNegative('0.0000', 4))->toBeFalse();
});

it('throws when dividing by zero', function () {
    expect(fn () => Decimal::divide('10.00', '0', 2))
        ->toThrow(InvalidArgumentException::class, 'Division by zero.');
});
