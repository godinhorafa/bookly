const KEY = 'bookly.token';

/**
 * Keeps the bearer token across reloads. Storage can be unavailable
 * (private mode, blocked cookies), so every access is guarded.
 */
export const tokenStore = {
  get(): string | null {
    try {
      return localStorage.getItem(KEY);
    } catch {
      return null;
    }
  },
  set(token: string): void {
    try {
      localStorage.setItem(KEY, token);
    } catch {
      // Not persisted: the session lasts until reload.
    }
  },
  clear(): void {
    try {
      localStorage.removeItem(KEY);
    } catch {
      // nothing to clear
    }
  },
};
