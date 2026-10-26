import { ProblemMessage } from '../../components/ProblemMessage';
import { formatMoney } from '../../lib/money';
import { formatDateTime, formatTime } from '../../lib/time';
import type { Space } from '../spaces/queries';
import { useCancelBooking, useMyBookings, type Booking } from './queries';

export function MyBookings({ spaces }: { spaces: Space[] }) {
  const bookings = useMyBookings();
  const cancel = useCancelBooking();
  const byId = new Map(spaces.map((space) => [space.id, space]));

  if (bookings.isPending) return <p>Loading your bookings…</p>;
  if (bookings.isError) return <ProblemMessage error={bookings.error} />;

  return (
    <section className="card" aria-label="My bookings">
      <h2>My bookings</h2>
      <ProblemMessage error={cancel.error} />
      {bookings.data.length === 0 ? (
        <p className="hint">No bookings yet.</p>
      ) : (
        <table>
          <thead>
            <tr>
              <th>Space</th>
              <th>When</th>
              <th>Price</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {bookings.data.map((booking) => (
              <BookingRow key={booking.id} booking={booking} space={byId.get(booking.space_id)} onCancel={() => cancel.mutate(booking.id)} cancelling={cancel.isPending && cancel.variables === booking.id} />
            ))}
          </tbody>
        </table>
      )}
    </section>
  );
}

function BookingRow({ booking, space, onCancel, cancelling }: { booking: Booking; space: Space | undefined; onCancel: () => void; cancelling: boolean }) {
  const timeZone = space?.timezone ?? 'UTC';
  const upcoming = Date.parse(booking.start) > Date.now();

  return (
    <tr data-status={booking.status}>
      <td>{space?.name ?? booking.space_id}</td>
      <td>
        {formatDateTime(booking.start, timeZone)}–{formatTime(booking.end, timeZone)}
      </td>
      <td>{formatMoney(booking.price)}</td>
      <td>
        {booking.status}
        {booking.hold_until && <> · pay by {formatTime(booking.hold_until, timeZone)}</>}
        {booking.cancellation && <> · refunded {formatMoney(booking.cancellation.refund)}</>}
      </td>
      <td>
        {booking.status !== 'cancelled' && upcoming && (
          <button type="button" onClick={onCancel} disabled={cancelling}>
            Cancel
          </button>
        )}
      </td>
    </tr>
  );
}
