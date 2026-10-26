import type * as React from 'react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/** Toasts themed with the app's tokens; light or dark follows the system, like the rest of the UI. */
function Toaster(props: ToasterProps) {
  return (
    <Sonner
      theme="system"
      className="toaster group"
      style={
        {
          '--normal-bg': 'var(--popover)',
          '--normal-text': 'var(--popover-foreground)',
          '--normal-border': 'var(--border)',
        } as React.CSSProperties
      }
      {...props}
    />
  );
}

export { Toaster };
