import { ChevronDownIcon } from 'lucide-react';
import type * as React from 'react';
import { cn } from '@/lib/utils';

/** A styled native <select>: keyboard, screen reader and mobile pickers work as the platform intends. */
function NativeSelect({ className, ...props }: React.ComponentProps<'select'>) {
  return (
    <div className="relative w-full">
      <select
        data-slot="native-select"
        className={cn(
          'h-9 w-full appearance-none rounded-md border border-input bg-card py-1 pr-9 pl-3 text-sm shadow-xs outline-none transition-[color,box-shadow] disabled:cursor-not-allowed disabled:opacity-50',
          'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
          className,
        )}
        {...props}
      />
      <ChevronDownIcon aria-hidden className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
    </div>
  );
}

export { NativeSelect };
