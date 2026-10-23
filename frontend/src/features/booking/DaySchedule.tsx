import type { ScheduleRow } from './schedule';

export function DaySchedule({ rows, selected, onPick }: { rows: ScheduleRow[]; selected: Set<string>; onPick: (time: string) => void }) {
  if (rows.length === 0) {
    return <p className="hint">Closed on this day.</p>;
  }

  return (
    <ol className="schedule" aria-label="Day schedule">
      {rows.map((row) => (
        <li key={row.time} className={`${row.state}${selected.has(row.time) ? ' selected' : ''}`}>
          <button type="button" disabled={row.state !== 'free'} onClick={() => onPick(row.time)} aria-label={`${row.time} ${row.state}`}>
            {row.time}
            <span>{row.state}</span>
          </button>
        </li>
      ))}
    </ol>
  );
}
