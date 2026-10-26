import { TZDate } from '@date-fns/tz';
import { addDays, format } from 'date-fns';

/**
 * All times shown to the user are in the space's own time zone, not the
 * browser's: a room in São Paulo opens at 07:00 São Paulo time for everyone.
 */

/** "2026-11-03" + "18:30" in America/Sao_Paulo → ISO instant with offset. */
export function localToIso(day: string, time: string, timeZone: string): string {
  const [y, m, d] = day.split('-').map(Number) as [number, number, number];
  const [hh, mm] = time.split(':').map(Number) as [number, number];

  // TZDate#toISOString keeps the zone's offset; go through Date for a plain UTC instant.
  return new Date(new TZDate(y, m - 1, d, hh, mm, timeZone).getTime()).toISOString();
}

/** The whole local day as an [from, to) pair of instants, DST-safe. */
export function dayRange(day: string, timeZone: string): { from: string; to: string } {
  // Built from components: a string like "2026-11-03T00:00:00" would be read in the *system* zone.
  const [y, m, d] = day.split('-').map(Number) as [number, number, number];
  const start = new TZDate(y, m - 1, d, 0, 0, timeZone);

  return { from: new Date(start.getTime()).toISOString(), to: new Date(addDays(start, 1).getTime()).toISOString() };
}

/** Today's date in the given zone, shifted by a number of days, as YYYY-MM-DD. */
export function localDate(timeZone: string, offsetDays = 0, now: Date = new Date()): string {
  return format(addDays(new TZDate(now, timeZone), offsetDays), 'yyyy-MM-dd');
}

export function formatTime(iso: string, timeZone: string): string {
  return format(new TZDate(iso, timeZone), 'HH:mm');
}

export function formatDateTime(iso: string, timeZone: string): string {
  return format(new TZDate(iso, timeZone), 'EEE d MMM, HH:mm');
}

/** ISO weekday (1 = Monday ... 7 = Sunday) of a local date. */
export function isoWeekday(day: string): number {
  const weekday = new Date(`${day}T12:00:00Z`).getUTCDay();
  return weekday === 0 ? 7 : weekday;
}

/** "HH:MM" every `step` minutes from `start` (inclusive) to `end` (exclusive). */
export function timesBetween(start: string, end: string, step: number): string[] {
  const toMinutes = (t: string) => {
    const [h, m] = t.split(':').map(Number) as [number, number];
    return h * 60 + m;
  };
  const times: string[] = [];
  for (let t = toMinutes(start); t < toMinutes(end); t += step) {
    times.push(`${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`);
  }
  return times;
}

/** "Mon" and "26" for a local date, for compact day pickers. */
export function dayParts(day: string): { weekday: string; date: string; month: string } {
  const [y, m, d] = day.split('-').map(Number) as [number, number, number];
  const noon = new Date(Date.UTC(y, m - 1, d, 12));
  const part = (options: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('en-GB', { ...options, timeZone: 'UTC' }).format(noon);

  return { weekday: part({ weekday: 'short' }), date: part({ day: 'numeric' }), month: part({ month: 'short' }) };
}

/** "14:59" for a number of seconds, never negative. */
export function formatCountdown(seconds: number): string {
  const s = Math.max(0, Math.floor(seconds));

  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
