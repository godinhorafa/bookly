<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Application\Booking\BookSpace;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks the *shape* of the request only. Business rules (notice, duration,
 * opening hours...) belong to the domain and come back as 422 problems.
 */
final class CreateBookingRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'space_id' => ['required', 'uuid'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'coupon' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function toCommand(): BookSpace
    {
        /** @var User $user */
        $user = $this->user();

        return new BookSpace(
            $this->string('space_id')->value(),
            $user->id,
            $this->string('start')->value(),
            $this->string('end')->value(),
            $this->filled('coupon') ? $this->string('coupon')->value() : null,
        );
    }
}
