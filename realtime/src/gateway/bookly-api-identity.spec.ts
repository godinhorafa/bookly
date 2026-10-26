import { afterEach, describe, expect, it, vi } from 'vitest';
import { BooklyApiIdentity } from './bookly-api-identity.js';
import { IdentityUnavailable } from './identity.js';

const identity = new BooklyApiIdentity({ port: 0, webhookSecret: 'x', apiUrl: 'http://api.test' });

function apiAnswers(response: Response | Error) {
  const fetch = vi.fn(() => (response instanceof Error ? Promise.reject(response) : Promise.resolve(response)));
  vi.stubGlobal('fetch', fetch);

  return fetch;
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('BooklyApiIdentity', () => {
  it('asks the API who the token belongs to', async () => {
    const fetch = apiAnswers(Response.json({ id: 42, name: 'Ana', email: 'ana@example.com' }));

    await expect(identity.customerFor('tok')).resolves.toBe(42);
    expect(fetch).toHaveBeenCalledWith('http://api.test/api/me', expect.objectContaining({
      headers: { Authorization: 'Bearer tok', Accept: 'application/json' },
    }));
  });

  it('returns null for a token the API rejects', async () => {
    apiAnswers(Response.json({ title: 'Unauthenticated' }, { status: 401 }));

    await expect(identity.customerFor('expired')).resolves.toBeNull();
  });

  it.each([
    ['the API is down', new TypeError('fetch failed')],
    ['the API fails', new Response('oops', { status: 503 })],
    ['the API answers something else', Response.json({ user: 42 })],
  ])('reports the identity as unavailable when %s, rather than calling the token bad', async (_case, answer) => {
    apiAnswers(answer);

    await expect(identity.customerFor('tok')).rejects.toBeInstanceOf(IdentityUnavailable);
  });
});
