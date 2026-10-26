import 'reflect-metadata';
import { createHmac } from 'node:crypto';
import type { AddressInfo } from 'node:net';
import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { io, type Socket } from 'socket.io-client';
import request from 'supertest';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { AppModule } from '../src/app.module.js';
import { CONFIG, type RealtimeConfig } from '../src/config.js';
import { IDENTITY, IdentityUnavailable, type IdentityProvider } from '../src/gateway/identity.js';
import { webhookBody } from './support/events.js';

/*
 * The whole service over real HTTP and WebSocket connections: signed webhooks
 * in, Socket.IO messages out. Only the call to the Bookly API is replaced.
 */
const SECRET = 'whsec_e2e';
const CUSTOMERS: Record<string, number> = { 'ana-token': 7, 'bruno-token': 8 };

const identity: IdentityProvider = {
  customerFor: async (token) => {
    if (token === 'api-down') throw new IdentityUnavailable('down');
    return CUSTOMERS[token] ?? null;
  },
};

let app: INestApplication;
let url: string;
const sockets: Socket[] = [];

beforeAll(async () => {
  const module = await Test.createTestingModule({ imports: [AppModule] })
    .overrideProvider(CONFIG).useValue({ port: 0, webhookSecret: SECRET, apiUrl: 'http://unused' } satisfies RealtimeConfig)
    .overrideProvider(IDENTITY).useValue(identity)
    .compile();

  app = module.createNestApplication({ rawBody: true, logger: false });
  await app.listen(0, '127.0.0.1');
  url = `http://127.0.0.1:${(app.getHttpServer().address() as AddressInfo).port}`;
});

afterEach(() => {
  sockets.splice(0).forEach((socket) => socket.disconnect());
});

afterAll(async () => {
  await app.close();
});

function connect(token: string): Promise<Socket> {
  const socket = io(url, { auth: { token }, transports: ['websocket'], reconnection: false });
  sockets.push(socket);

  return new Promise((resolve, reject) => {
    socket.on('connect', () => resolve(socket));
    socket.on('connect_error', reject);
  });
}

/** Collects what a socket hears, by message name. */
function listen(socket: Socket): Record<string, unknown[]> {
  const heard: Record<string, unknown[]> = {};
  socket.onAny((name: string, payload: unknown) => {
    (heard[name] ??= []).push(payload);
  });

  return heard;
}

function sign(body: string, timestamp = Math.floor(Date.now() / 1000), secret = SECRET): string {
  return `t=${timestamp},v1=${createHmac('sha256', secret).update(`${timestamp}.${body}`).digest('hex')}`;
}

function deliver(event: Record<string, unknown>, signature?: string) {
  const body = JSON.stringify(event);

  return request(app.getHttpServer())
    .post('/webhooks/bookly')
    .set('Content-Type', 'application/json')
    .set('Bookly-Signature', signature ?? sign(body))
    .send(body);
}

/** Lets in-flight socket messages arrive before asserting that something did NOT. */
const settle = () => new Promise((resolve) => setTimeout(resolve, 100));

describe('dashboard connections', () => {
  it('accepts a dashboard with a valid Bookly token', async () => {
    await expect(connect('ana-token')).resolves.toMatchObject({ connected: true });
  });

  it.each([
    ['no token', '', 'unauthenticated'],
    ['a token the API rejects', 'stolen-token', 'unauthenticated'],
    ['a token that cannot be checked right now', 'api-down', 'unavailable'],
  ])('refuses %s', async (_case, token, reason) => {
    await expect(connect(token)).rejects.toThrow(reason);
  });
});

describe('booking webhooks', () => {
  it('tells everyone about the slot and only the owner about the booking', async () => {
    const ana = listen(await connect('ana-token'));
    const bruno = listen(await connect('bruno-token'));

    await deliver(webhookBody('booking.requested', { customer_id: 7 })).expect(202, { pushed: true });
    await settle();

    expect(ana['slot.changed']).toEqual([expect.objectContaining({ busy: true })]);
    expect(bruno['slot.changed']).toEqual([expect.objectContaining({ busy: true })]);
    expect(ana['booking.changed']).toEqual([expect.objectContaining({ status: 'pending' })]);
    expect(bruno['booking.changed']).toBeUndefined();
  });

  it('pushes a redelivered event only once', async () => {
    const ana = listen(await connect('ana-token'));
    const event = webhookBody('booking.cancelled');

    await deliver(event).expect(202, { pushed: true });
    await deliver(event).expect(202, { pushed: false });
    await settle();

    expect(ana['slot.changed']).toHaveLength(1);
  });

  it('accepts event types it does not handle, so the API does not retry them forever', async () => {
    await deliver(webhookBody('space.renamed')).expect(202, { pushed: false });
  });

  it('rejects malformed events', async () => {
    await deliver({ ...webhookBody('booking.requested'), data: { customer_id: 'x' } }).expect(400);
  });

  it.each([
    ['unsigned', () => ''],
    ['signed with another secret', (body: string) => sign(body, undefined, 'whsec_other')],
    ['replayed with an old signature', (body: string) => sign(body, Math.floor(Date.now() / 1000) - 600)],
  ])('rejects %s webhooks without pushing anything', async (_case, signatureFor) => {
    const ana = listen(await connect('ana-token'));
    const event = webhookBody('booking.requested');

    await deliver(event, signatureFor(JSON.stringify(event))).expect(401);
    await settle();

    expect(ana).toEqual({});
  });

  it('reports its health', async () => {
    await request(app.getHttpServer()).get('/health').expect(200, { status: 'ok' });
  });
});
