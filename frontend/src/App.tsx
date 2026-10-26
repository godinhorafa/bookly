import { useQueryClient } from '@tanstack/react-query';
import { LogOut } from 'lucide-react';
import { useState } from 'react';
import { LoginForm } from '@/auth/LoginForm';
import { tokenStore } from '@/auth/tokenStore';
import { Logo } from '@/components/Logo';
import { ProblemMessage } from '@/components/ProblemMessage';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Toaster } from '@/components/ui/sonner';
import { BookingPanel } from '@/features/booking/BookingPanel';
import { MyBookings } from '@/features/bookings/MyBookings';
import { LiveActivity, LiveStatusPill } from '@/features/live/LiveActivity';
import { useLiveUpdates } from '@/features/live/useLiveUpdates';
import { SpacePicker } from '@/features/spaces/SpacePicker';
import { useSpaces, type Space } from '@/features/spaces/queries';

export function App() {
  const [token, setToken] = useState(tokenStore.get());
  const queryClient = useQueryClient();

  return (
    <>
      {token === null ? (
        <LoginForm
          onLoggedIn={(t) => {
            tokenStore.set(t);
            setToken(t);
          }}
        />
      ) : (
        <Dashboard
          token={token}
          onLogout={() => {
            tokenStore.clear();
            queryClient.clear();
            setToken(null);
          }}
        />
      )}
      <Toaster position="bottom-right" richColors closeButton />
    </>
  );
}

function Dashboard({ token, onLogout }: { token: string; onLogout: () => void }) {
  const spaces = useSpaces();
  const live = useLiveUpdates(token);
  const [selected, setSelected] = useState<Space | undefined>();
  const space = selected ?? spaces.data?.[0];

  return (
    <div className="min-h-svh">
      <header className="sticky top-0 z-40 border-b bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
          <h1>
            <Logo />
          </h1>
          <div className="flex items-center gap-2">
            <LiveStatusPill status={live.status} />
            <Button variant="ghost" size="sm" onClick={onLogout}>
              <LogOut aria-hidden />
              Log out
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <div>
          <h2 className="text-2xl font-semibold tracking-tight">Book a space</h2>
          <p className="text-muted-foreground">Pick a space and a time. Prices and rules are checked as you book.</p>
        </div>

        {spaces.isError && <ProblemMessage error={spaces.error} title="Couldn’t load the spaces" />}

        {/* Small screens read top to bottom: spaces, booking, live activity. Large ones keep the activity beside the booking. */}
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)] lg:grid-rows-[auto_1fr]">
          <aside className="lg:col-start-1 lg:row-start-1">
            {spaces.data ? (
              <SpacePicker spaces={spaces.data} selectedId={space?.id} onSelect={setSelected} />
            ) : (
              <div className="space-y-3">
                {[0, 1, 2].map((i) => (
                  <Skeleton key={i} className="h-24 rounded-xl" />
                ))}
              </div>
            )}
          </aside>

          <div className="min-w-0 space-y-6 lg:col-start-2 lg:row-span-2 lg:row-start-1">
            {space ? <BookingPanel key={space.id} space={space} /> : <Skeleton className="h-[32rem] rounded-xl" />}
            {spaces.data && <MyBookings spaces={spaces.data} />}
          </div>

          {spaces.data && (
            <div className="lg:col-start-1 lg:row-start-2">
              <LiveActivity status={live.status} activity={live.activity} spaces={spaces.data} />
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
