import { Inject, Injectable, UnauthorizedException, type CanActivate, type ExecutionContext, type RawBodyRequest } from '@nestjs/common';
import type { Request } from 'express';
import { CONFIG, type RealtimeConfig } from '../config.js';
import { verifySignature } from './signature.js';

/** Lets a request through only if Bookly signed its body with the shared secret. */
@Injectable()
export class BooklySignatureGuard implements CanActivate {
  constructor(@Inject(CONFIG) private readonly config: RealtimeConfig) {}

  canActivate(context: ExecutionContext): boolean {
    const request = context.switchToHttp().getRequest<RawBodyRequest<Request>>();
    const nowSeconds = Math.floor(Date.now() / 1000);

    if (request.rawBody === undefined || !verifySignature(request.rawBody, request.header('Bookly-Signature'), this.config.webhookSecret, nowSeconds)) {
      throw new UnauthorizedException('Missing or invalid Bookly-Signature.');
    }

    return true;
  }
}
