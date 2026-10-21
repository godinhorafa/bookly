<?php

declare(strict_types=1);

use Domain\Booking\TimeSlot;
use Domain\Cancellation\CancellationPolicyType;
use Domain\Cancellation\Exception\InvalidCancellationPolicy;
use Domain\Cancellation\RefundTier;
use Domain\Cancellation\TieredRefundPolicy;
use Domain\Shared\Currency;
use Domain\Shared\Money;

const SLOT_START = '2026-11-10T10:00:00Z';

function refund(CancellationPolicyType|TieredRefundPolicy $policy, int $hoursBefore, int $paid = 10_000): int
{
    $slot = TimeSlot::fromStrings(SLOT_START, '2026-11-10T12:00:00Z');
    $at = (new DateTimeImmutable(SLOT_START))->modify(sprintf('%+d hours', -$hoursBefore));
    $strategy = $policy instanceof CancellationPolicyType ? $policy->policy() : $policy;

    return $strategy->refundFor(Money::of($paid, Currency::BRL), $slot, $at)->amount;
}

it('applies the published refund tables', function (CancellationPolicyType $type, int $hoursBefore, int $expected): void {
    expect(refund($type, $hoursBefore))->toBe($expected);
})->with([
    'flexible, 2 days' => [CancellationPolicyType::Flexible, 48, 10_000],
    'flexible, exactly 24h' => [CancellationPolicyType::Flexible, 24, 10_000],
    'flexible, 23h' => [CancellationPolicyType::Flexible, 23, 5_000],
    'flexible, 1h' => [CancellationPolicyType::Flexible, 1, 5_000],
    'moderate, exactly 48h' => [CancellationPolicyType::Moderate, 48, 10_000],
    'moderate, 47h' => [CancellationPolicyType::Moderate, 47, 5_000],
    'moderate, exactly 24h' => [CancellationPolicyType::Moderate, 24, 5_000],
    'moderate, 23h' => [CancellationPolicyType::Moderate, 23, 0],
    'strict, 8 days' => [CancellationPolicyType::Strict, 192, 5_000],
    'strict, 6 days' => [CancellationPolicyType::Strict, 144, 0],
    'non-refundable, 30 days' => [CancellationPolicyType::NonRefundable, 720, 0],
]);

it('refunds nothing once the slot has started, whatever the tiers say', function (): void {
    $policy = new TieredRefundPolicy(new RefundTier(0, 100));

    expect(refund($policy, -1))->toBe(0)
        ->and(refund($policy, 0))->toBe(10_000);
});

it('does not depend on the order tiers are declared in', function (): void {
    $ordered = new TieredRefundPolicy(new RefundTier(72, 100), new RefundTier(24, 30));
    $shuffled = new TieredRefundPolicy(new RefundTier(24, 30), new RefundTier(72, 100));

    foreach ([100, 50, 10] as $hours) {
        expect(refund($shuffled, $hours))->toBe(refund($ordered, $hours));
    }
});

it('rounds partial refunds half up to the cent', function (): void {
    expect(refund(CancellationPolicyType::Moderate, 30, paid: 999))->toBe(500);
});

it('rejects ambiguous or invalid tiers', function (Closure $build): void {
    $build();
})->with([
    'duplicate notice period' => [fn (): TieredRefundPolicy => new TieredRefundPolicy(new RefundTier(24, 100), new RefundTier(24, 50))],
    'negative hours' => [fn (): RefundTier => new RefundTier(-1, 50)],
    'over 100%' => [fn (): RefundTier => new RefundTier(24, 101)],
])->throws(InvalidCancellationPolicy::class);
