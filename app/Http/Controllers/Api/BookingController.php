<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DELIBERATE ANTI-PATTERN — the "before" of docs/refactoring/fat-controller.md.
 *
 * This is how the endpoint often looks in a typical codebase: one method that
 * validates, applies business rules, prices, persists and formats the response.
 * The next commit replaces it with a thin controller over the BookSpace use case,
 * with the same feature tests passing unchanged.
 */
final class BookingController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'space_id' => ['required', 'uuid'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'coupon' => ['nullable', 'string'],
        ]);

        $space = DB::table('spaces')->where('id', $data['space_id'])->first();
        if (! $space) {
            return response()->json(['message' => 'Space not found'], 404);
        }

        $start = Carbon::parse($data['start'])->utc();
        $end = Carbon::parse($data['end'])->utc();
        $minutes = $start->diffInMinutes($end);

        // business rules
        if ($start->lt(now()->addMinutes(60))) {
            return response()->json(['message' => 'Bookings must start at least 60 minutes from now.'], 422);
        }
        if ($minutes < 30 || $minutes > 720) {
            return response()->json(['message' => 'Bookings must last between 30 and 720 minutes.'], 422);
        }
        if ($start->minute % 15 !== 0 || $end->minute % 15 !== 0) {
            return response()->json(['message' => 'Bookings must start and end on a 15-minute boundary.'], 422);
        }
        $upcoming = DB::table('bookings')
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', 'cancelled')
            ->whereRaw('upper(period) > now()')
            ->count();
        if ($upcoming >= 5) {
            return response()->json(['message' => 'Too many bookings.'], 422);
        }

        // check availability
        $overlap = DB::table('bookings')
            ->where('space_id', $space->id)
            ->where('status', '!=', 'cancelled')
            ->whereRaw('period && tstzrange(?, ?)', [$start->toIso8601String(), $end->toIso8601String()])
            ->exists();
        if ($overlap) {
            return response()->json(['message' => 'This slot is already booked.'], 409);
        }

        // price
        $price = (int) round($space->hourly_rate_amount * $minutes / 60);
        $hour = $start->hour;
        if ($start->isWeekday() && $hour >= 18 && $hour < 22) {
            $price = (int) round($price * 1.25);
        }
        if ($minutes >= 240) {
            $price = (int) round($price * 0.9);
        }
        if (! empty($data['coupon'])) {
            $coupon = DB::table('coupons')->where('code', strtoupper($data['coupon']))->first();
            if ($coupon && (! $coupon->expires_at || Carbon::parse($coupon->expires_at)->isFuture())) {
                $price = $coupon->discount_type === 'percentage'
                    ? (int) round($price * (100 - $coupon->value) / 100)
                    : max(0, $price - $coupon->value);
            }
        }

        $id = (string) Str::uuid();
        DB::table('bookings')->insert([
            'id' => $id,
            'space_id' => $space->id,
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'total_amount' => $price,
            'currency' => $space->currency,
            'period' => '[' . $start->toIso8601String() . ',' . $end->toIso8601String() . ')',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $id,
                'status' => 'pending',
                'space_id' => $space->id,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'price' => ['amount' => $price, 'currency' => $space->currency],
            ],
        ], 201);
    }
}
