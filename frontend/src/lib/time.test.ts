import { dayRange, formatTime, isoWeekday, localDate, localToIso, timesBetween } from './time';

describe('time helpers', () => {
  it('turns a local wall-clock time into a UTC instant', () => {
    expect(localToIso('2026-11-03', '18:30', 'America/Sao_Paulo')).toBe('2026-11-03T21:30:00.000Z');
    expect(localToIso('2026-11-03', '10:00', 'Asia/Kolkata')).toBe('2026-11-03T04:30:00.000Z');
  });

  it('builds a local day as [from, to), including 23 and 25 hour days', () => {
    expect(dayRange('2026-11-03', 'America/Sao_Paulo')).toEqual({
      from: '2026-11-03T03:00:00.000Z',
      to: '2026-11-04T03:00:00.000Z',
    });

    // Lisbon leaves summer time on 2026-10-25: that day lasts 25 hours.
    const { from, to } = dayRange('2026-10-25', 'Europe/Lisbon');
    expect((Date.parse(to) - Date.parse(from)) / 3_600_000).toBe(25);
  });

  it('formats instants in the space time zone, not the browser one', () => {
    expect(formatTime('2026-11-03T21:30:00Z', 'America/Sao_Paulo')).toBe('18:30');
    expect(formatTime('2026-11-03T21:30:00Z', 'Europe/Lisbon')).toBe('21:30');
  });

  it('knows today in a given zone', () => {
    // 01:00 UTC on the 4th is still the 3rd in São Paulo
    expect(localDate('America/Sao_Paulo', 0, new Date('2026-11-04T01:00:00Z'))).toBe('2026-11-03');
    expect(localDate('America/Sao_Paulo', 1, new Date('2026-11-04T01:00:00Z'))).toBe('2026-11-04');
  });

  it('computes ISO weekdays and time steps', () => {
    expect(isoWeekday('2026-11-02')).toBe(1); // Monday
    expect(isoWeekday('2026-11-08')).toBe(7); // Sunday
    expect(timesBetween('22:00', '24:00', 30)).toEqual(['22:00', '22:30', '23:00', '23:30']);
  });
});
