import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module.js';
import { CONFIG, type RealtimeConfig } from './config.js';

// rawBody: webhook signatures are computed over the exact bytes Bookly sent.
const app = await NestFactory.create(AppModule, { rawBody: true });
app.enableShutdownHooks();

await app.listen(app.get<RealtimeConfig>(CONFIG).port);
