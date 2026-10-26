export const CONFIG = Symbol('RealtimeConfig');

export interface RealtimeConfig {
  readonly port: number;
  /** The same value as WEBHOOK_SECRET on the Bookly API: it proves a webhook came from Bookly. */
  readonly webhookSecret: string;
  /** The Bookly API, used to turn a dashboard's bearer token into a customer. */
  readonly apiUrl: string;
}

export function loadConfig(env: NodeJS.ProcessEnv = process.env): RealtimeConfig {
  const webhookSecret = env['WEBHOOK_SECRET'] ?? '';
  if (webhookSecret === '') {
    throw new Error('WEBHOOK_SECRET is required and must match the Bookly API.');
  }

  return {
    port: Number(env['PORT'] ?? 3000),
    webhookSecret,
    apiUrl: (env['API_URL'] ?? 'http://127.0.0.1:8000').replace(/\/+$/, ''),
  };
}
