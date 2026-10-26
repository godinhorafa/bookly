import { describe, expect, it } from 'vitest';
import { RecentEvents } from './recent-events.js';

describe('RecentEvents', () => {
  it('recognises a redelivered event', () => {
    const recent = new RecentEvents();

    expect(recent.firstTime('a')).toBe(true);
    expect(recent.firstTime('b')).toBe(true);
    expect(recent.firstTime('a')).toBe(false);
  });

  it('forgets the oldest ids beyond its capacity', () => {
    const recent = new RecentEvents(2);
    recent.firstTime('a');
    recent.firstTime('b');
    recent.firstTime('c');

    expect(recent.firstTime('b')).toBe(false);
    expect(recent.firstTime('a')).toBe(true);
  });
});
