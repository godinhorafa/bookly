import { CalendarClock, CircleCheck, Globe, LoaderCircle, Ticket } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { ProblemMessage } from '@/components/ProblemMessage';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { formatMoney } from '@/lib/money';
import { formatDateTime, formatTime, localDate, localToIso, timesBetween } from '@/lib/time';
import { useCreateBooking, type Booking } from '../bookings/queries';
import { POLICY } from '../spaces/policy';
import { useAvailability, type Space } from '../spaces/queries';
import { DaySchedule } from './DaySchedule';
import { DayStrip } from './DayStrip';
import { HoldCountdown } from './HoldCountdown';
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
  const end = useMemo(() => (start === null ? null : addMinutes(start, minutes)), [start, minutes]);
  const selected = useMemo(() => new Set(start === null || end === null ? [] : timesBetween(start, end, 30)), [start, end]);

  function pickDay(next: string) {
    setDay(next);
    setStart(null);
    create.reset();
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    if (start === null) return;
    const from = localToIso(day, start, space.timezone);
    const to = new Date(Date.parse(from) + minutes * 60_000).toISOString();
    setConfirmed(null);
    create.mutate({ space_id: space.id, start: from, end: to, coupon: coupon.trim() === '' ? null : coupon });
  }

  const booking = create.data;
  const estimate = { amount: Math.round((space.hourly_rate.amount * minutes) / 60), currency: space.hourly_rate.currency };

  return (
    <Card aria-label={`Book ${space.name}`} role="region">
      <CardHeader>
        <CardTitle className="text-xl">{space.name}</CardTitle>
        <CardDescription className="flex flex-wrap items-center gap-x-3 gap-y-1">
          <span className="flex items-center gap-1">
            <Globe className="size-3.5" aria-hidden />
            Times are in {space.timezone}.
          </span>
          <span>
            {POLICY[space.cancellation_policy].name} cancellation: {POLICY[space.cancellation_policy].detail.toLowerCase()}
          </span>
        </CardDescription>
        <CardAction>
          <Badge variant="outline" className="text-sm">
            {formatMoney(space.hourly_rate)}/h
          </Badge>
        </CardAction>
      </CardHeader>

      <CardContent className="space-y-6">
        <form onSubmit={submit} aria-label="New booking" className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
          <div className="min-w-0 space-y-5">
            <div className="space-y-2">
              <div className="flex items-center justify-between gap-3">
                <span className="text-sm font-medium">Choose a day</span>
                <div className="flex items-center gap-2">
                  <Label htmlFor="booking-day" className="text-muted-foreground">
                    Day
                  </Label>
                  <Input id="booking-day" type="date" className="h-8 w-40" value={day} min={localDate(space.timezone)} onChange={(e) => e.target.value && pickDay(e.target.value)} />
                </div>
              </div>
              <DayStrip timeZone={space.timezone} value={day} onChange={pickDay} />
            </div>

            <div className="space-y-2">
              <span className="text-sm font-medium">Pick a start time</span>
              {availability.isPending ? (
                <div className="grid grid-cols-[repeat(auto-fill,minmax(5.25rem,1fr))] gap-1.5" aria-hidden>
                  {Array.from({ length: 16 }, (_, i) => (
                    <div key={i} className="h-12 animate-pulse rounded-lg bg-muted" />
                  ))}
                </div>
              ) : (
                <DaySchedule
                  rows={rows}
                  selected={selected}
                  onPick={(time) => {
                    setStart(time);
                    create.reset();
                  }}
                />
              )}
            </div>
          </div>

          <div className="h-fit space-y-4 rounded-xl border bg-muted/40 p-4 xl:sticky xl:top-24">
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-1.5">
                <Label htmlFor="booking-start">Start</Label>
                <Input id="booking-start" value={start ?? ''} readOnly placeholder="—" aria-label="Start" className="tabular-nums" />
              </div>
              <div className="grid gap-1.5">
                <Label htmlFor="booking-duration">Duration</Label>
                <NativeSelect id="booking-duration" value={minutes} onChange={(e) => setMinutes(Number(e.target.value))}>
                  {DURATIONS.map((d) => (
                    <option key={d} value={d}>
                      {d} min
                    </option>
                  ))}
                </NativeSelect>
              </div>
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="booking-coupon">Coupon</Label>
              <div className="relative">
                <Ticket className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                <Input id="booking-coupon" value={coupon} onChange={(e) => setCoupon(e.target.value)} placeholder="Optional, e.g. WELCOME10" className="pl-9 uppercase placeholder:normal-case" />
              </div>
            </div>

            <div className="rounded-lg bg-card p-3 text-sm">
              {start !== null && end !== null ? (
                <>
                  <p className="font-medium tabular-nums">
                    {start}–{end} · {minutes} min
                  </p>
                  <p className="text-muted-foreground">
                    From {formatMoney(estimate)}. Peak hours, long stays and coupons are applied when you book.
                  </p>
                </>
              ) : (
                <p className="text-muted-foreground">Pick a free slot to see the price.</p>
              )}
            </div>

            <ProblemMessage error={create.error} />
            <Button type="submit" size="lg" className="w-full" disabled={start === null || create.isPending}>
              {create.isPending && <LoaderCircle className="animate-spin" aria-hidden />}
              Book
            </Button>
          </div>
        </form>

        {booking && !confirmed && (
          <section className="grid gap-6 rounded-xl border border-primary/30 bg-accent/40 p-5 md:grid-cols-2" role="region" aria-label="Booking on hold">
            <div className="space-y-4">
              <div className="space-y-1">
                <h3 className="flex items-center gap-2 font-semibold">
                  <CalendarClock className="size-4 text-primary" aria-hidden />
                  On hold until paid · {formatMoney(booking.price)}
                </h3>
                <p className="text-sm text-muted-foreground">
                  {formatDateTime(booking.start, space.timezone)}–{formatTime(booking.end, space.timezone)} at {space.name}
                </p>
              </div>
              {booking.hold_until && <HoldCountdown holdUntil={booking.hold_until} timeZone={space.timezone} />}
              {booking.quote && (
                <div className="rounded-lg bg-card p-4">
                  <QuoteSummary quote={booking.quote} />
                </div>
              )}
            </div>
            <PaymentForm booking={booking} onPaid={setConfirmed} />
          </section>
        )}

        {confirmed && (
          <div className="flex items-start gap-3 rounded-xl border border-success/30 bg-success/10 p-4">
            <CircleCheck className="mt-0.5 size-5 text-success" aria-hidden />
            <div className="text-sm">
              <p role="status" className="font-semibold text-success">
                Booking confirmed. See you there!
              </p>
              <p className="text-muted-foreground">
                {space.name}, {formatDateTime(confirmed.start, space.timezone)}–{formatTime(confirmed.end, space.timezone)} · paid {formatMoney(confirmed.price)}
              </p>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function addMinutes(time: string, minutes: number): string {
  const [h, m] = time.split(':').map(Number) as [number, number];
  const total = h * 60 + m + minutes;

  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}
