<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Domain\Cancellation\CancellationPolicyType;
use Domain\Pricing\Coupon;
use Domain\Pricing\CouponRepository;
use Domain\Shared\Currency;
use Domain\Shared\Money;
use Domain\Shared\WeeklyWindow;
use Domain\Space\OpeningHours;
use Domain\Space\Space;
use Domain\Space\SpaceId;
use Domain\Space\SpaceRepository;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development and the end-to-end tests. Safe to run twice.
 *
 * Log in with demo@bookly.test / password.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(SpaceRepository $spaces, CouponRepository $coupons): void
    {
        User::query()->updateOrCreate(['email' => 'demo@bookly.test'], ['name' => 'Demo Customer', 'password' => 'password']);

        $spaces->save(new Space(
            SpaceId::fromString('0b9e7e4c-2f4b-4c1a-9d3e-7a1b2c3d4e01'),
            'Focus room',
            'America/Sao_Paulo',
            6,
            Money::of(6_000, Currency::BRL),
            CancellationPolicyType::Moderate,
            new OpeningHours(WeeklyWindow::weekdays('07:00', '22:00'), WeeklyWindow::weekends('09:00', '18:00')),
        ));

        $spaces->save(new Space(
            SpaceId::fromString('0b9e7e4c-2f4b-4c1a-9d3e-7a1b2c3d4e02'),
            'Tennis court',
            'America/Sao_Paulo',
            4,
            Money::of(9_000, Currency::BRL),
            CancellationPolicyType::Flexible,
            new OpeningHours(new WeeklyWindow([1, 2, 3, 4, 5, 6, 7], '06:00', '23:00')),
        ));

        $spaces->save(new Space(
            SpaceId::fromString('0b9e7e4c-2f4b-4c1a-9d3e-7a1b2c3d4e03'),
            'Event hall',
            'Europe/Lisbon',
            80,
            Money::of(25_000, Currency::EUR),
            CancellationPolicyType::Strict,
        ));

        $coupons->save(Coupon::percentage('WELCOME10', 10));
    }
}
