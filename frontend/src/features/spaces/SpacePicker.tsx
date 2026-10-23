import { formatMoney } from '../../lib/money';
import type { Space } from './queries';

const POLICY: Record<Space['cancellation_policy'], string> = {
  flexible: 'Flexible: full refund up to 24h before',
  moderate: 'Moderate: full refund up to 48h, half up to 24h',
  strict: 'Strict: half refund up to 7 days before',
  non_refundable: 'Non-refundable',
};

export function SpacePicker({ spaces, selectedId, onSelect }: { spaces: Space[]; selectedId: string | undefined; onSelect: (space: Space) => void }) {
  return (
    <fieldset className="card">
      <legend>Spaces</legend>
      {spaces.map((space) => (
        <label key={space.id} className="space-option">
          <input type="radio" name="space" checked={space.id === selectedId} onChange={() => onSelect(space)} />
          <span>
            <strong>{space.name}</strong> · {formatMoney(space.hourly_rate)}/h · {space.timezone}
            <br />
            <small>{POLICY[space.cancellation_policy]}</small>
          </span>
        </label>
      ))}
    </fieldset>
  );
}
