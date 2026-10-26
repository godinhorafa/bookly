import type { Space } from './queries';

export const POLICY: Record<Space['cancellation_policy'], { name: string; detail: string }> = {
  flexible: { name: 'Flexible', detail: 'Full refund up to 24h before' },
  moderate: { name: 'Moderate', detail: 'Full refund up to 48h, half up to 24h' },
  strict: { name: 'Strict', detail: 'Half refund up to 7 days before' },
  non_refundable: { name: 'Non-refundable', detail: 'No refund after booking' },
};
