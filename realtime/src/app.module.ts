import { Module } from '@nestjs/common';
import { CONFIG, loadConfig } from './config.js';
import { BookingEvents, BROADCASTER } from './events/booking-events.js';
import { BooklyApiIdentity } from './gateway/bookly-api-identity.js';
import { IDENTITY } from './gateway/identity.js';
import { RealtimeGateway } from './gateway/realtime.gateway.js';
import { BooklySignatureGuard } from './webhooks/bookly-signature.guard.js';
import { WebhooksController } from './webhooks/webhooks.controller.js';

@Module({
  controllers: [WebhooksController],
  providers: [
    { provide: CONFIG, useFactory: () => loadConfig() },
    { provide: IDENTITY, useClass: BooklyApiIdentity },
    { provide: BROADCASTER, useExisting: RealtimeGateway },
    RealtimeGateway,
    BookingEvents,
    BooklySignatureGuard,
  ],
})
export class AppModule {}
