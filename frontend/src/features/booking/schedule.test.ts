import type { Availability, Space } from '../spaces/queries';
import { buildSchedule } from './schedule';

const space: Space = {
  id: 'space-1',
  name: 'Focus room',
  timezone: 'America/Sao_Paulo',
  capacity: 6,
  hourly_rate: { amount: 6000, currency: 'BRL' },
  cancellation_policy: 'moderate',
  opening_hours: [
    { weekdays: [1, 2, 3, 4, 5], start: '08:00', end: '10:00' },
    { weekdays: [6, 7], start: '09:00', end: '10:00' },
  ],
};

const availability = (busy: Availability['busy']): Availability => ({ space_id: space.id, from: '', to: '', busy });

describe('buildSchedule', () => {
  it('uses the opening hours of that weekday', () => {
    expect(buildSchedule(space, '2026-11-03', undefined).map((r) => r.time)).toEqual(['08:00', '08:30', '09:00', '09:30']);
    expect(buildSchedule(space, '2026-11-07', undefined).map((r) => r.time)).toEqual(['09:00', '09:30']);
  });

  it('marks rows overlapping a booking as busy, in local time', () => {
    // 08:30-09:30 in São Paulo is 11:30-12:30 UTC
    const rows = buildSchedule(space, '2026-11-03', availability([{ start: '2026-11-03T11:30:00+00:00', end: '2026-11-03T12:30:00+00:00' }]));

    expect(rows.map((r) => `${r.time} ${r.state}`)).toEqual(['08:00 free', '08:30 busy', '09:00 busy', '09:30 free']);
  });

  it('is empty on days the space is closed', () => {
    expect(buildSchedule({ ...space, opening_hours: [] }, '2026-11-03', undefined)).toEqual([]);
  });
});
