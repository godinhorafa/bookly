/**
 * Remembers the most recent event ids. Bookly delivers webhooks at least once,
 * so a retried delivery must not reach the dashboards twice.
 *
 * Bounded and in memory: a duplicate arriving after `capacity` newer events, or
 * after a restart, is pushed again. That is harmless here, since dashboards
 * only refetch on these messages.
 */
export class RecentEvents {
  private readonly ids = new Set<string>();

  constructor(private readonly capacity = 10_000) {}

  /** True the first time an id is seen, false for a redelivery. */
  firstTime(id: string): boolean {
    if (this.ids.has(id)) return false;

    this.ids.add(id);
    if (this.ids.size > this.capacity) {
      // Sets iterate in insertion order: the first one is the oldest.
      this.ids.delete(this.ids.values().next().value as string);
    }

    return true;
  }
}
