import { formatDateTime, formatTime } from '../../lib/time';
import type { Space } from '../spaces/queries';
import type { Activity, LiveStatus } from './useLiveUpdates';

const LABEL: Record<LiveStatus, string> = { connecting: 'connecting…', live: 'live', offline: 'offline' };

export function LiveActivity({ status, activity, spaces }: { status: LiveStatus; activity: Activity[]; spaces: Space[] }) {
  const byId = new Map(spaces.map((space) => [space.id, space]));

  return (
    <section className="card" aria-label="Live activity">
      <h2>
        Live activity <span className={`pill ${status}`}>{LABEL[status]}</span>
      </h2>
      {activity.length === 0 ? (
        <p className="hint">Slots taken or freed by anyone show up here as it happens.</p>
      ) : (
        <ul className="activity" aria-live="polite">
          {activity.map((item) => {
            const space = byId.get(item.space_id);
            const timeZone = space?.timezone ?? 'UTC';

            return (
              <li key={item.key}>
                {space?.name ?? 'A space'}, {formatDateTime(item.start, timeZone)}–{formatTime(item.end, timeZone)}: {item.busy ? 'just booked' : 'just freed'}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
