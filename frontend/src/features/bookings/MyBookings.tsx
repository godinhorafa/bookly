import { CalendarDays, Inbox } from 'lucide-react';
import { toast } from 'sonner';
import { ProblemMessage } from '@/components/ProblemMessage';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatMoney } from '@/lib/money';
import { formatDateTime, formatTime } from '@/lib/time';
import { POLICY } from '../spaces/policy';
import type { Space } from '../spaces/queries';
import { useCancelBooking, useMyBookings, type Booking } from './queries';

const STATUS: Record<Booking['status'], { label: string; variant: 'warning' | 'success' | 'muted' | 'destructive' }> = {
  pending: { label: 'Awaiting payment', variant: 'warning' },
  confirmed: { label: 'Confirmed', variant: 'success' },
  cancelled: { label: 'Cancelled', variant: 'muted' },
  expired: { label: 'Expired', variant: 'destructive' },
};

export function MyBookings({ spaces }: { spaces: Space[] }) {
  const bookings = useMyBookings();
  const cancel = useCancelBooking();
  const byId = new Map(spaces.map((space) => [space.id, space]));

  return (
    <Card role="region" aria-label="My bookings">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-lg">
          <CalendarDays className="size-5 text-primary" aria-hidden />
          My bookings
        </CardTitle>
        <CardDescription>Your bookings, latest first. Times are in each space’s time zone.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <ProblemMessage error={cancel.error} title="Couldn’t cancel" />
        {bookings.isPending ? (
          <div className="space-y-2">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-12" />
            ))}
          </div>
        ) : bookings.isError ? (
          <ProblemMessage error={bookings.error} title="Couldn’t load your bookings" />
        ) : bookings.data.length === 0 ? (
          <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
            <Inbox className="size-6" aria-hidden />
            No bookings yet. Pick a slot above to make your first one.
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>Space</TableHead>
                <TableHead>When</TableHead>
                <TableHead className="text-right">Price</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>
                  <span className="sr-only">Actions</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {bookings.data.map((booking) => (
                <BookingRow
                  key={booking.id}
                  booking={booking}
                  space={byId.get(booking.space_id)}
                  cancelling={cancel.isPending && cancel.variables === booking.id}
                  onCancel={() =>
                    cancel.mutate(booking.id, {
                      onSuccess: (cancelled) => {
                        const refund = cancelled.cancellation?.refund;
                        toast.success('Booking cancelled', {
                          description: refund && refund.amount > 0 ? `${formatMoney(refund)} will be refunded to your card.` : 'Nothing was charged, so there is nothing to refund.',
                        });
                      },
                    })
                  }
                />
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}

function BookingRow({ booking, space, onCancel, cancelling }: { booking: Booking; space: Space | undefined; onCancel: () => void; cancelling: boolean }) {
  const timeZone = space?.timezone ?? 'UTC';
  const upcoming = Date.parse(booking.start) > Date.now();
  const status = STATUS[booking.status];
  const cancellable = (booking.status === 'pending' || booking.status === 'confirmed') && upcoming;

  return (
    <TableRow data-status={booking.status}>
      <TableCell className="font-medium">{space?.name ?? booking.space_id}</TableCell>
      <TableCell className="tabular-nums">
        {formatDateTime(booking.start, timeZone)}–{formatTime(booking.end, timeZone)}
      </TableCell>
      <TableCell className="text-right tabular-nums">{formatMoney(booking.price)}</TableCell>
      <TableCell>
        <div className="flex flex-col gap-1">
          <Badge variant={status.variant}>{status.label}</Badge>
          {booking.hold_until && <span className="text-xs text-muted-foreground">pay by {formatTime(booking.hold_until, timeZone)}</span>}
          {booking.cancellation && <span className="text-xs text-muted-foreground">refunded {formatMoney(booking.cancellation.refund)}</span>}
        </div>
      </TableCell>
      <TableCell className="text-right">
        {cancellable && (
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button variant="outline" size="sm" disabled={cancelling} className="text-destructive hover:text-destructive">
                Cancel
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Cancel this booking?</AlertDialogTitle>
                <AlertDialogDescription>
                  {space?.name}, {formatDateTime(booking.start, timeZone)}–{formatTime(booking.end, timeZone)}.{' '}
                  {booking.status === 'pending'
                    ? 'It isn’t paid yet, so nothing will be charged.'
                    : space
                      ? `${POLICY[space.cancellation_policy].name} policy: ${POLICY[space.cancellation_policy].detail.toLowerCase()}. The refund is calculated when you confirm.`
                      : 'The refund follows the space’s cancellation policy.'}
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Keep it</AlertDialogCancel>
                <AlertDialogAction onClick={onCancel} className="bg-destructive text-white hover:bg-destructive/90">
                  Cancel booking
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        )}
      </TableCell>
    </TableRow>
  );
}
