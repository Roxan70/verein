import dotenv from 'dotenv';

dotenv.config();

const required = ['PORT', 'JWT_SECRET', 'DATABASE_URL', 'REDIS_URL', 'COORDINATOR_URL'];
required.forEach((key) => {
  if (!process.env[key]) {
    throw new Error(`Missing required environment variable: ${key}`);
  }
});

export const env = {
  port: Number(process.env.PORT || 3100),
  nodeEnv: process.env.NODE_ENV || 'production',
  jwtSecret: process.env.JWT_SECRET,
  dbUrl: process.env.DATABASE_URL,
  redisUrl: process.env.REDIS_URL,
  coordinatorUrl: process.env.COORDINATOR_URL,
  corsOrigin: process.env.CORS_ORIGIN || '*',
  rateWindowMs: Number(process.env.RATE_WINDOW_MS || 15 * 60 * 1000),
  rateMax: Number(process.env.RATE_MAX || 100)
};
