import { Inject, Injectable } from '@nestjs/common';
import { CONFIG, type RealtimeConfig } from '../config.js';
import { IdentityUnavailable, type IdentityProvider } from './identity.js';

/**
 * Asks the Bookly API who a token belongs to (GET /api/me). The API stays the
 * only place that knows about tokens: this service never touches its database.
 */
@Injectable()
export class BooklyApiIdentity implements IdentityProvider {
  constructor(@Inject(CONFIG) private readonly config: RealtimeConfig) {}

  async customerFor(token: string): Promise<number | null> {
    let response: Response;
    try {
      response = await fetch(`${this.config.apiUrl}/api/me`, {
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        signal: AbortSignal.timeout(5_000),
      });
    } catch (error) {
      throw new IdentityUnavailable('The Bookly API could not be reached.', { cause: error });
    }

    if (response.status === 401) return null;
    if (!response.ok) throw new IdentityUnavailable(`The Bookly API answered ${response.status}.`);

    const body: unknown = await response.json();
    const id = typeof body === 'object' && body !== null && 'id' in body ? body.id : undefined;
    if (!Number.isInteger(id)) throw new IdentityUnavailable('The Bookly API sent an unexpected /api/me body.');

    return id as number;
  }
}
