import { Hourglass } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { formatCountdown, formatTime } from '@/lib/time';

/** "Pay by 15:12 · 14:32 left", ticking every second. */
export function HoldCountdown({ holdUntil, timeZone }: { holdUntil: string; timeZone: string }) {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1_000);
    return () => clearInterval(timer);
  }, []);

  const left = (Date.parse(holdUntil) - now) / 1000;

  return (
    <div className="flex flex-wrap items-center gap-2 text-sm">
      <p className="text-muted-foreground">Pay by {formatTime(holdUntil, timeZone)}, or the slot is released for others.</p>
      <Badge variant={left > 120 ? 'secondary' : 'warning'} className="tabular-nums">
        <Hourglass aria-hidden />
        {left > 0 ? `${formatCountdown(left)} left` : 'Time is up'}
      </Badge>
    </div>
  );
}
