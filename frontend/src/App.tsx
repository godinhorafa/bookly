import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { LoginForm } from './auth/LoginForm';
import { tokenStore } from './auth/tokenStore';
import { ProblemMessage } from './components/ProblemMessage';
import { BookingPanel } from './features/booking/BookingPanel';
import { MyBookings } from './features/bookings/MyBookings';
import { SpacePicker } from './features/spaces/SpacePicker';
import { useSpaces, type Space } from './features/spaces/queries';

export function App() {
  const [token, setToken] = useState(tokenStore.get());
  const queryClient = useQueryClient();

  if (token === null) {
    return (
      <LoginForm
        onLoggedIn={(t) => {
          tokenStore.set(t);
          setToken(t);
        }}
      />
    );
  }

  return (
    <Dashboard
      onLogout={() => {
        tokenStore.clear();
        queryClient.clear();
        setToken(null);
      }}
    />
  );
}

function Dashboard({ onLogout }: { onLogout: () => void }) {
  const spaces = useSpaces();
  const [selected, setSelected] = useState<Space | undefined>();
  const space = selected ?? spaces.data?.[0];

  return (
    <main className="layout">
      <header>
        <h1>Bookly</h1>
        <button type="button" onClick={onLogout}>
          Log out
        </button>
      </header>
      {spaces.isError && <ProblemMessage error={spaces.error} />}
      {spaces.data && (
        <>
          <SpacePicker spaces={spaces.data} selectedId={space?.id} onSelect={setSelected} />
          {space && <BookingPanel key={space.id} space={space} />}
          <MyBookings spaces={spaces.data} />
        </>
      )}
    </main>
  );
}
