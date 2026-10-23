/**
 * Client-side view of an RFC 9457 problem document returned by the API
 * (see docs/api/problems.md). Every failed request becomes a ProblemError.
 */
export interface ProblemBody {
  type?: string;
  title?: string;
  status?: number;
  detail?: string;
  violations?: string[];
  decline_reason?: string;
  errors?: Record<string, string[]>;
}

export class ProblemError extends Error {
  readonly status: number;
  readonly kind: string;
  readonly violations: string[];
  readonly declineReason: string | undefined;
  readonly fieldErrors: Record<string, string[]>;

  constructor(status: number, body: ProblemBody | undefined) {
    super(body?.detail ?? body?.title ?? `Request failed with status ${status}`);
    this.name = 'ProblemError';
    this.status = status;
    // The type is a URL ending in #<kind>, e.g. ...problems.md#slot-unavailable
    this.kind = body?.type?.split('#').pop() ?? 'unknown';
    this.violations = body?.violations ?? [];
    this.declineReason = body?.decline_reason;
    this.fieldErrors = body?.errors ?? {};
  }

  /** Human-readable lines to show the user, most specific first. */
  messages(): string[] {
    if (this.violations.length > 0) return this.violations;
    const fields = Object.values(this.fieldErrors).flat();
    return fields.length > 0 ? fields : [this.message];
  }
}
