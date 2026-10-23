import { useMemo, useState, type FormEvent } from 'react';
import { ProblemMessage } from '../../components/ProblemMessage';
import { formatMoney } from '../../lib/money';
import { localDate, localToIso, timesBetween } from '../../lib/time';
import { useCreateBooking, type Booking } from '../bookings/queries';
import { useAvailability, type Space } from '../spaces/queries';
import { DaySchedule } from './DaySchedule';
import { PaymentForm } from './PaymentForm';
import { QuoteSummary } from './QuoteSummary';
import { buildSchedule } from './schedule';

const DURATIONS = [30, 60, 90, 120, 180, 240];

export function BookingPanel({ space }: { space: Space }) {
  const [day, setDay] = useState(() => localDate(space.timezone, 1));
  const [start, setStart] = useState<string | null>(null);
  const [minutes, setMinutes] = useState(60);
  const [coupon, setCoupon] = useState('');
  const [confirmed, setConfirmed] = useState<Booking | null>(null);
  const availability = useAvailability(space, day);
  const create = useCreateBooking();

  const rows = useMemo(() => buildSchedule(space, day, availability.data), [space, day, availability.data]);
  const selected = useMemo(() => {
    if (start === null) return new Set<string>();
    const [h, m] = start.split(':').map(Number) as [number, number];
    const endMinutes = h * 60 + m + minutes;
    const end = `${String(Math.floor(endMinutes / 60)).padStart(2, '0')}:${String(endMinutes % 60).padStart(2, '0')}`;
    return new Set(timesBetween(start, end, 30));
  }, [start, minutes]);

  function submit(event: FormEvent) {
    event.preventDefault();
    if (start === null) return;
    const from = localToIso(day, start, space.timezone);
    const to = new Date(Date.parse(from) + minutes * 60_000).toISOString();
    setConfirmed(null);
    create.mutate({ space_id: space.id, start: from, end: to, coupon: coupon.trim() === '' ? null : coupon });
  }

  const booking = create.data;

  return (
    <section className="card" aria-label={`Book ${space.name}`}>
      <h2>{space.name}</h2>
      <p className="hint">Times are in {space.timezone}.</p>

      <form onSubmit={submit} aria-label="New booking">
        <label>
          Day
          <input type="date" value={day} min={localDate(space.timezone)} onChange={(e) => { setDay(e.target.value); setStart(null); create.reset(); }} />
        </label>

        <DaySchedule rows={rows} selected={selected} onPick={(time) => { setStart(time); create.reset(); }} />

        <div className="row">
          <label>
            Start
            <input value={start ?? ''} readOnly placeholder="Pick a free slot" aria-label="Start" />
          </label>
          <label>
            Duration
            <select value={minutes} onChange={(e) => setMinutes(Number(e.target.value))}>
              {DURATIONS.map((d) => (
                <option key={d} value={d}>
                  {d} min
                </option>
              ))}
            </select>
          </label>
          <label>
            Coupon
            <input value={coupon} onChange={(e) => setCoupon(e.target.value)} placeholder="optional" />
          </label>
        </div>

        <ProblemMessage error={create.error} />
        <button type="submit" disabled={start === null || create.isPending}>
          Book
        </button>
      </form>

      {booking && !confirmed && (
        <div className="result" aria-label="Booking on hold">
          <h3>On hold until paid · {formatMoney(booking.price)}</h3>
          {booking.quote && <QuoteSummary quote={booking.quote} />}
          <PaymentForm booking={booking} onPaid={setConfirmed} />
        </div>
      )}

      {confirmed && (
        <p role="status" className="success">
          Booking confirmed. See you there!
        </p>
      )}
    </section>
  );
}
