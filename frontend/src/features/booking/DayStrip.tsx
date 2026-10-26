import { dayParts, localDate } from '@/lib/time';
import { cn } from '@/lib/utils';

/** The next two weeks as one-tap buttons; the date input next to it reaches any other day. */
export function DayStrip({ timeZone, value, onChange, days = 14 }: { timeZone: string; value: string; onChange: (day: string) => void; days?: number }) {
  const today = localDate(timeZone);
  const options = Array.from({ length: days }, (_, i) => localDate(timeZone, i));

  return (
    <div
      className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 [mask-image:linear-gradient(to_right,black_85%,transparent)] [scrollbar-width:none] pr-10"
      role="group"
      aria-label="Next days"
    >
      {options.map((day) => {
        const { weekday, date, month } = dayParts(day);
        const active = day === value;

        return (
          <button
            key={day}
            type="button"
            aria-pressed={active}
            aria-label={`${weekday} ${date} ${month}`}
            onClick={() => onChange(day)}
            className={cn(
              'flex w-14 shrink-0 flex-col items-center rounded-lg border py-2 text-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
              active ? 'border-primary bg-primary text-primary-foreground shadow-sm' : 'bg-card hover:border-primary/40 hover:bg-accent',
            )}
          >
            <span className={cn('font-medium', !active && 'text-muted-foreground')}>{day === today ? 'Today' : weekday}</span>
            <span className="text-lg leading-tight font-semibold">{date}</span>
            <span className={cn(!active && 'text-muted-foreground')}>{month}</span>
          </button>
        );
      })}
    </div>
  );
}
