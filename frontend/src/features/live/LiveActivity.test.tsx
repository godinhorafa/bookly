import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen } from '@testing-library/react';
import type { Space } from '../spaces/queries';
import { LiveActivity } from './LiveActivity';
import type { Connect, LiveSocket } from './socket';
import { useLiveUpdates } from './useLiveUpdates';

const space = { id: 'space-1', name: 'Tennis court', timezone: 'America/Sao_Paulo' } as Space;

/** Stands in for the Socket.IO connection: the test plays the server. */
class FakeSocket implements LiveSocket {
  private listeners = new Map<string, (message?: unknown) => void>();
  disconnected = false;

  on(event: string, listener: (message: never) => void) {
    this.listeners.set(event, listener as (message?: unknown) => void);
    return this;
  }

  disconnect() {
    this.disconnected = true;
  }

  emit(event: string, message?: unknown) {
    act(() => this.listeners.get(event)?.(message));
  }
}

function Harness({ connect }: { connect: Connect }) {
  const live = useLiveUpdates('token-1', connect);
  return <LiveActivity status={live.status} activity={live.activity} spaces={[space]} />;
}

function renderLive() {
  const socket = new FakeSocket();
  const connect = vi.fn(() => socket);
  const client = new QueryClient();
  const invalidate = vi.spyOn(client, 'invalidateQueries');
  const { unmount } = render(
    <QueryClientProvider client={client}>
      <Harness connect={connect} />
    </QueryClientProvider>,
  );

  return { socket, connect, invalidate, unmount };
}

describe('live updates', () => {
  it('connects with the bearer token and shows the connection state', () => {
    const { socket, connect } = renderLive();
    expect(connect).toHaveBeenCalledWith('token-1');
    expect(screen.getByText('connecting…')).toBeInTheDocument();

    socket.emit('connect');
    expect(screen.getByText('live')).toBeInTheDocument();

    socket.emit('disconnect');
    expect(screen.getByText('offline')).toBeInTheDocument();
  });

  it('refetches the availability of a space when one of its slots changes, and lists it', () => {
    const { socket, invalidate } = renderLive();

    socket.emit('slot.changed', { space_id: 'space-1', start: '2026-10-28T18:00:00Z', end: '2026-10-28T19:00:00Z', busy: true });

    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['availability', 'space-1'] });
    expect(screen.getByRole('listitem')).toHaveTextContent('Tennis court, Wed 28 Oct, 15:00–16:00: just booked');
  });

  it('refetches my bookings when one of them changes', () => {
    const { socket, invalidate } = renderLive();

    socket.emit('booking.changed', { event_id: 'e', booking_id: 'b', status: 'confirmed' });

    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['bookings'] });
  });

  it('refetches everything after reconnecting, in case it missed something', () => {
    const { socket, invalidate } = renderLive();

    socket.emit('connect');

    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['availability'] });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['bookings'] });
  });

  it('keeps only the latest activity', () => {
    const { socket } = renderLive();

    for (let hour = 10; hour < 17; hour++) {
      socket.emit('slot.changed', { space_id: 'space-1', start: `2026-10-28T${hour}:00:00Z`, end: `2026-10-28T${hour}:30:00Z`, busy: false });
    }

    expect(screen.getAllByRole('listitem')).toHaveLength(5);
    expect(screen.getAllByRole('listitem')[0]).toHaveTextContent('13:00–13:30: just freed');
  });

  it('disconnects when the dashboard goes away (log out)', () => {
    const { socket, unmount } = renderLive();

    unmount();

    expect(socket.disconnected).toBe(true);
  });
});
