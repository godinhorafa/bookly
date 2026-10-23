import { isoWeekday, localToIso, timesBetween } from '../../lib/time';
import type { Availability, Space } from '../spaces/queries';

export type SlotState = 'free' | 'busy' | 'closed';

export interface ScheduleRow {
  time: string;
  state: SlotState;
}

/**
 * The day split into `step`-minute rows, each marked free, busy or closed,
 * in the space's local time. Pure, so it is unit tested without a browser.
 */
export function buildSchedule(space: Space, day: string, availability: Availability | undefined, step = 30): ScheduleRow[] {
  const windows = space.opening_hours.filter((w) => w.weekdays.includes(isoWeekday(day)));
  const busy = (availability?.busy ?? []).map((slot) => [Date.parse(slot.start), Date.parse(slot.end)] as const);

  return windows
    .flatMap((w) => timesBetween(w.start, w.end, step))
    .map((time) => {
      const start = Date.parse(localToIso(day, time, space.timezone));
      const end = start + step * 60_000;
      const taken = busy.some(([from, to]) => start < to && from < end);

      return { time, state: taken ? 'busy' : 'free' };
    });
}
