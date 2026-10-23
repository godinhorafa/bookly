import createClient, { type Middleware } from 'openapi-fetch';
import { tokenStore } from '../auth/tokenStore';
import { ProblemError, type ProblemBody } from './problem';
import type { paths } from './schema';

/**
 * Typed API client: paths, parameters and response bodies all come from
 * schema.d.ts, which is generated from docs/api/openapi.json (`npm run api:types`).
 */
// Absolute URL from the page origin: same-origin in the browser (through the dev/preview
// proxy), and valid where relative URLs are not, such as Request objects in tests.
export const api = createClient<paths>({
  baseUrl: `${globalThis.location.origin}/api`,
  // Looked up on every call rather than captured at import, so it can be replaced (tests, instrumentation).
  fetch: (request) => globalThis.fetch(request),
});

const auth: Middleware = {
  onRequest({ request }) {
    const token = tokenStore.get();
    if (token !== null) request.headers.set('Authorization', `Bearer ${token}`);
    request.headers.set('Accept', 'application/json');
    return request;
  },
};

api.use(auth);

/**
 * A fresh Idempotency-Key per user action: if the request is retried (network
 * hiccup, double click), the API replays the first response instead of booking twice.
 */
export const idempotencyKey = (): Record<string, string> => ({ 'Idempotency-Key': crypto.randomUUID() });

/**
 * Returns the data of a successful response, or throws a ProblemError.
 */
export async function unwrap<T>(call: Promise<{ data?: T; error?: unknown; response: Response }>): Promise<T> {
  const { data, error, response } = await call;
  if (!response.ok || data === undefined) {
    throw new ProblemError(response.status, error as ProblemBody | undefined);
  }
  return data;
}
