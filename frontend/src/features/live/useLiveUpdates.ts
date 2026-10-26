import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { connectLive, type Connect, type SlotChanged } from './socket';

export type LiveStatus = 'connecting' | 'live' | 'offline';

export interface Activity extends SlotChanged {
  key: number;
}

const KEEP = 5;

/**
 * Keeps the dashboard current while other people book: availability and "my
 * bookings" are refetched when the realtime service says they changed.
 * Without the service the dashboard still works, just without live updates.
 */
export function useLiveUpdates(token: string, connect: Connect = connectLive) {
  const client = useQueryClient();
  const [status, setStatus] = useState<LiveStatus>('connecting');
  const [activity, setActivity] = useState<Activity[]>([]);
  const nextKey = useRef(0);

  useEffect(() => {
    const socket = connect(token);

    socket.on('connect', () => {
      setStatus('live');
      // Anything could have happened while disconnected.
      void client.invalidateQueries({ queryKey: ['availability'] });
      void client.invalidateQueries({ queryKey: ['bookings'] });
    });
    socket.on('disconnect', () => setStatus('offline'));
    socket.on('connect_error', () => setStatus('offline'));

    socket.on('slot.changed', (slot) => {
      void client.invalidateQueries({ queryKey: ['availability', slot.space_id] });
      setActivity((items) => [{ ...slot, key: nextKey.current++ }, ...items].slice(0, KEEP));
    });
    socket.on('booking.changed', () => {
      void client.invalidateQueries({ queryKey: ['bookings'] });
    });

    return () => {
      socket.disconnect();
    };
  }, [token, connect, client]);

  return { status, activity };
}
