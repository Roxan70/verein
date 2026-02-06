import express from 'express';
import helmet from 'helmet';
import cors from 'cors';
import rateLimit from 'express-rate-limit';
import { env } from './config/env.js';
import { initDb } from './config/db.js';
import { redis } from './config/redis.js';
import { authRouter } from './routes/auth.js';
import { avatarRouter } from './routes/avatars.js';
import { chatRouter } from './routes/chat.js';
import { healthRouter } from './routes/health.js';
import { errorHandler, notFoundHandler } from './middleware/error.js';

async function bootstrap() {
  await initDb();
  await redis.ping();

  const app = express();
  app.use(helmet());
  app.use(cors({ origin: env.corsOrigin === '*' ? true : env.corsOrigin }));
  app.use(express.json({ limit: '1mb' }));
  app.use(
    rateLimit({
      windowMs: env.rateWindowMs,
      max: env.rateMax,
      standardHeaders: true,
      legacyHeaders: false
    })
  );

  app.use('/api/auth', authRouter);
  app.use('/api/avatars', avatarRouter);
  app.use('/api/chat', chatRouter);
  app.use('/api/health', healthRouter);

  app.use(notFoundHandler);
  app.use(errorHandler);

  app.listen(env.port, '0.0.0.0', () => {
    console.log(`[gateway] listening on :${env.port}`);
  });
}

bootstrap().catch((err) => {
  console.error('[gateway] bootstrap failed', err);
  process.exit(1);
});
