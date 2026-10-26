export const IDENTITY = Symbol('IdentityProvider');

/** Port: who does a dashboard's bearer token belong to? */
export interface IdentityProvider {
  /** The customer id, or null when the token is not (or no longer) valid. */
  customerFor(token: string): Promise<number | null>;
}

/** The identity could not be checked (API down, unexpected answer): not the same as a bad token. */
export class IdentityUnavailable extends Error {}
