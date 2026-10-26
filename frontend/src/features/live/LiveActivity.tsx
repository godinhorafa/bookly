import { CalendarPlus, CalendarX, Radio } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime, formatTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import type { Space } from '../spaces/queries';
import type { Activity, LiveStatus } from './useLiveUpdates';

const LABEL: Record<LiveStatus, string> = { connecting: 'connecting…', live: 'live', offline: 'offline' };

/** Connection state of the live updates, with a pulsing dot while connected. */
export function LiveStatusPill({ status }: { status: LiveStatus }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium capitalize',
        status === 'live' ? 'border-success/30 bg-success/10 text-success' : 'text-muted-foreground',
      )}
    >
      <span className="relative flex size-2">
        {status === 'live' && <span className="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60" />}
        <span className={cn('relative inline-flex size-2 rounded-full', status === 'live' ? 'bg-success' : status === 'offline' ? 'bg-destructive' : 'bg-muted-foreground')} />
      </span>
      {LABEL[status]}
    </span>
  );
}

export function LiveActivity({ status, activity, spaces }: { status: LiveStatus; activity: Activity[]; spaces: Space[] }) {
  const byId = new Map(spaces.map((space) => [space.id, space]));

  return (
    <Card className="gap-4" role="region" aria-label="Live activity">
      <CardHeader>
        <CardTitle className="flex items-center justify-between gap-2 text-base">
          Live activity <LiveStatusPill status={status} />
        </CardTitle>
        <CardDescription>Slots taken or freed by anyone, as it happens.</CardDescription>
      </CardHeader>
      <CardContent>
        {activity.length === 0 ? (
          <p className="flex items-center gap-2 rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
            <Radio className="size-4" aria-hidden />
            Nothing yet. Book from another window to see it here.
          </p>
        ) : (
          <ul className="space-y-2" aria-live="polite">
            {activity.map((item) => {
              const space = byId.get(item.space_id);
              const timeZone = space?.timezone ?? 'UTC';
              const Icon = item.busy ? CalendarPlus : CalendarX;

              return (
                <li key={item.key} className="flex items-start gap-3 rounded-lg p-2 animate-in fade-in-0 slide-in-from-top-1">
                  <span className={cn('grid size-8 shrink-0 place-items-center rounded-lg', item.busy ? 'bg-primary/10 text-primary' : 'bg-success/10 text-success')}>
                    <Icon className="size-4" aria-hidden />
                  </span>
                  <span className="min-w-0 text-sm">
                    <span className="font-medium">{space?.name ?? 'A space'}</span> {item.busy ? 'was just booked' : 'is free again'}
                    <span className="block text-muted-foreground">
                      {formatDateTime(item.start, timeZone)}–{formatTime(item.end, timeZone)}
                    </span>
                  </span>
                </li>
              );
            })}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
