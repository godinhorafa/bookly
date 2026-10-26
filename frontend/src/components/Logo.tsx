import { CalendarCheck } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Logo({ className }: { className?: string }) {
  return (
    <span className={cn('flex items-center gap-2.5', className)}>
      <span className="grid size-8 place-items-center rounded-lg bg-primary text-primary-foreground shadow-sm">
        <CalendarCheck className="size-4.5" aria-hidden />
      </span>
      <span className="text-lg font-semibold tracking-tight">Bookly</span>
    </span>
  );
}
