import { CreditCard, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { ProblemError } from '@/api/problem';
import { ProblemMessage } from '@/components/ProblemMessage';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
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
      className="flex flex-col gap-4 rounded-lg bg-card p-4"
      onSubmit={(event) => {
        event.preventDefault();
        pay.mutate({ id: booking.id, paymentMethod: method }, { onSuccess: onPaid });
      }}
    >
      <div className="space-y-1">
        <h4 className="font-semibold">Payment</h4>
        <p className="text-sm text-muted-foreground">Test mode: pick a card to see what happens.</p>
      </div>
      <div className="grid gap-1.5">
        <Label htmlFor="payment-card">Test card</Label>
        <NativeSelect id="payment-card" value={method} onChange={(e) => setMethod(e.target.value)}>
          {TEST_CARDS.map((card) => (
            <option key={card.id} value={card.id}>
              {card.label}
            </option>
          ))}
        </NativeSelect>
      </div>
      {declined ? (
        <Alert variant="destructive">
          <CreditCard />
          <AlertTitle>Payment declined</AlertTitle>
          <AlertDescription>Reason: {declined.declineReason}. Your booking is still on hold; try another card.</AlertDescription>
        </Alert>
      ) : (
        <ProblemMessage error={pay.error} title="Payment failed" />
      )}
      <Button type="submit" size="lg" className="mt-auto w-full" disabled={pay.isPending}>
        {pay.isPending ? <LoaderCircle className="animate-spin" aria-hidden /> : <CreditCard aria-hidden />}
        Pay and confirm
      </Button>
    </form>
  );
}
