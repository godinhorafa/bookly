import { Globe } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { POLICY } from './policy';
import type { Space } from './queries';

const POLICY_BADGE: Record<Space['cancellation_policy'], 'success' | 'secondary' | 'warning' | 'muted'> = {
  flexible: 'success',
  moderate: 'secondary',
  strict: 'warning',
  non_refundable: 'muted',
};

export function SpacePicker({ spaces, selectedId, onSelect }: { spaces: Space[]; selectedId: string | undefined; onSelect: (space: Space) => void }) {
  return (
    <fieldset className="space-y-3">
      <legend className="mb-3 text-sm font-medium text-muted-foreground">Spaces</legend>
      {spaces.map((space) => (
        <label
          key={space.id}
          className={cn(
            'relative block cursor-pointer rounded-xl border bg-card p-4 shadow-xs transition-all hover:border-primary/40 hover:shadow-sm',
            'has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/20 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
          )}
        >
          {/* The real radio covers the card, so keyboard, screen readers and clicks all use it. */}
          <input
            type="radio"
            name="space"
            className="absolute inset-0 z-10 cursor-pointer opacity-0"
            checked={space.id === selectedId}
            onChange={() => onSelect(space)}
          />
          <span className="flex items-start gap-3">
            <span aria-hidden className="grid size-10 shrink-0 place-items-center rounded-lg bg-accent text-sm font-semibold text-accent-foreground">
              {initials(space.name)}
            </span>
            <span className="min-w-0 flex-1 space-y-1">
              <span className="flex items-baseline justify-between gap-2">
                <span className="truncate font-medium">{space.name}</span>
                <span className="shrink-0 text-sm font-semibold">
                  {formatMoney(space.hourly_rate)}
                  <span className="font-normal text-muted-foreground">/h</span>
                </span>
              </span>
              <span className="flex items-center gap-1 text-xs text-muted-foreground">
                <Globe className="size-3" aria-hidden />
                {space.timezone.replace('_', ' ')}
              </span>
              <span className="flex flex-wrap items-center gap-1.5 pt-1">
                <Badge variant={POLICY_BADGE[space.cancellation_policy]}>{POLICY[space.cancellation_policy].name}</Badge>
                <span className="text-xs text-muted-foreground">{POLICY[space.cancellation_policy].detail}</span>
              </span>
            </span>
          </span>
        </label>
      ))}
    </fieldset>
  );
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word[0]?.toUpperCase() ?? '')
    .join('');
}
