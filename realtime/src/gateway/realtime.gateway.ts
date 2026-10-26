import { Inject, Logger } from '@nestjs/common';
import { WebSocketGateway, WebSocketServer, type OnGatewayConnection, type OnGatewayInit } from '@nestjs/websockets';
import type { Server, Socket } from 'socket.io';
import type { Broadcaster } from '../events/booking-events.js';
import { customerRoom, EVERYONE, type Message } from '../events/fan-out.js';
import { IDENTITY, IdentityUnavailable, type IdentityProvider } from './identity.js';

interface ClientData {
  customerId: number;
}

type Client = Socket<Record<string, never>, Record<string, never>, Record<string, never>, ClientData>;

/**
 * The dashboards' WebSocket (Socket.IO). A dashboard connects with its Bookly
 * bearer token (`auth: { token }`) and only ever listens: everything it hears
 * comes from Bookly's webhooks, through `send`.
 */
@WebSocketGateway()
export class RealtimeGateway implements OnGatewayInit<Server>, OnGatewayConnection<Client>, Broadcaster {
  private readonly logger = new Logger(RealtimeGateway.name);

  @WebSocketServer()
  private server!: Server;

  constructor(@Inject(IDENTITY) private readonly identity: IdentityProvider) {}

  afterInit(server: Server): void {
    // Checked during the handshake: an unknown token never gets a connection.
    // The client sees the reason as a `connect_error`.
    server.use((socket, next) => {
      this.authenticate(socket as Client).then(() => next(), (error: Error) => next(error));
    });
  }

  async handleConnection(client: Client): Promise<void> {
    await client.join([EVERYONE, customerRoom(client.data.customerId)]);
  }

  send(message: Message): void {
    this.server.to(message.room).emit(message.name, message.payload);
  }

  private async authenticate(client: Client): Promise<void> {
    const token: unknown = client.handshake.auth['token'];
    if (typeof token !== 'string' || token === '') throw new Error('unauthenticated');

    let customerId: number | null;
    try {
      customerId = await this.identity.customerFor(token);
    } catch (error) {
      if (!(error instanceof IdentityUnavailable)) throw error;
      this.logger.warn(error.message);
      throw new Error('unavailable');
    }

    if (customerId === null) throw new Error('unauthenticated');
    client.data.customerId = customerId;
  }
}
