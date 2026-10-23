import { useState } from 'react';
import { ProblemError } from '../../api/problem';
import { ProblemMessage } from '../../components/ProblemMessage';
import { usePayBooking, type Booking } from '../bookings/queries';

// Stripe test-mode payment methods; the fake gateway accepts the same ones.
const TEST_CARDS = [
  { id: 'pm_card_visa', label: 'Visa (succeeds)' },
  { id: 'pm_card_chargeDeclined', label: 'Card declined' },
  { id: 'pm_card_visa_chargeDeclinedInsufficientFunds', label: 'Insufficient funds' },
];

export function PaymentForm({ booking, onPaid }: { booking: Booking; onPaid: (booking: Booking) => void }) {
  const [method, setMethod] = useState(TEST_CARDS[0]?.id ?? '');
  const pay = usePayBooking();
  const declined = pay.error instanceof ProblemError && pay.error.status === 402 ? pay.error : null;

  return (
    <form
      aria-label="Payment"
      onSubmit={(event) => {
        event.preventDefault();
        pay.mutate({ id: booking.id, paymentMethod: method }, { onSuccess: onPaid });
      }}
    >
      <label>
        Test card
        <select value={method} onChange={(e) => setMethod(e.target.value)}>
          {TEST_CARDS.map((card) => (
            <option key={card.id} value={card.id}>
              {card.label}
            </option>
          ))}
        </select>
      </label>
      {declined ? (
        <p role="alert" className="problem">
          Payment declined ({declined.declineReason}). Your booking is still on hold; try another card.
        </p>
      ) : (
        <ProblemMessage error={pay.error} />
      )}
      <button type="submit" disabled={pay.isPending}>
        Pay and confirm
      </button>
    </form>
  );
}
