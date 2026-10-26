import { Moon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ScheduleRow } from './schedule';

export function DaySchedule({ rows, selected, onPick }: { rows: ScheduleRow[]; selected: Set<string>; onPick: (time: string) => void }) {
  if (rows.length === 0) {
    return (
      <p className="flex items-center justify-center gap-2 rounded-lg border border-dashed py-10 text-sm text-muted-foreground">
        <Moon className="size-4" aria-hidden />
        Closed on this day. Try another one.
      </p>
    );
  }

  return (
    <div className="space-y-3">
      <ol className="grid grid-cols-[repeat(auto-fill,minmax(5.25rem,1fr))] gap-1.5" aria-label="Day schedule">
        {rows.map((row) => {
          const isSelected = row.state === 'free' && selected.has(row.time);

          return (
            <li key={row.time}>
              <button
                type="button"
                disabled={row.state !== 'free'}
                onClick={() => onPick(row.time)}
                aria-label={`${row.time} ${row.state}`}
                aria-pressed={row.state === 'free' ? isSelected : undefined}
                className={cn(
                  'flex h-12 w-full flex-col items-center justify-center rounded-lg border text-sm font-medium tabular-nums transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                  row.state === 'free' && !isSelected && 'border-transparent bg-slot-free hover:bg-slot-free-hover',
                  row.state === 'free' && isSelected && 'border-primary bg-primary text-primary-foreground shadow-sm',
                  row.state === 'busy' && 'cursor-not-allowed border-dashed border-border bg-slot-busy text-muted-foreground',
                )}
              >
                <span className={cn(row.state === 'busy' && 'line-through decoration-muted-foreground/60')}>{row.time}</span>
                <span className={cn('text-[0.7rem] font-medium', isSelected ? 'text-primary-foreground/85' : row.state === 'busy' ? 'text-foreground/55' : 'text-success')}>
                  {isSelected ? 'Selected' : row.state === 'busy' ? 'Taken' : 'Free'}
                </span>
              </button>
            </li>
          );
        })}
      </ol>
      <ul className="flex flex-wrap gap-4 text-xs text-muted-foreground" aria-label="Legend">
        <li className="flex items-center gap-1.5">
          <span className="size-3 rounded bg-slot-free ring-1 ring-success/30" /> Free
        </li>
        <li className="flex items-center gap-1.5">
          <span className="size-3 rounded bg-primary" /> Your selection
        </li>
        <li className="flex items-center gap-1.5">
          <span className="size-3 rounded bg-slot-busy ring-1 ring-border" /> Taken
        </li>
      </ul>
    </div>
  );
}
