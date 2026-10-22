Hi {{ $customerName }},

Your booking is confirmed.

Booking: {{ $booking['booking_id'] }}
From:    {{ $booking['start'] }}
To:      {{ $booking['end'] }}
Paid:    {{ $booking['currency'] }} {{ number_format(((int) $booking['paid_amount']) / 100, 2) }}

See you soon!
