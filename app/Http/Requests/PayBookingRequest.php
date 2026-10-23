<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PayBookingRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // A payment method token from the provider, e.g. Stripe's "pm_card_visa" in test mode.
            'payment_method' => ['required', 'string', 'max:255'],
        ];
    }
}
