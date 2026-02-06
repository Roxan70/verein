import express from 'express';
import { pool } from '../config/db.js';
import { redis } from '../config/redis.js';
import { getCoordinatorHealth } from '../services/coordinatorClient.js';

export const healthRouter = express.Router();

healthRouter.get('/', async (_req, res) => {
  const report = {
    service: 'gateway',
    status: 'ok',
    timestamp: new Date().toISOString(),
    checks: {
      database: 'unknown',
      redis: 'unknown',
      coordinator: 'unknown'
    }
  };

  try {
    await pool.query('SELECT 1');
    report.checks.database = 'ok';
  } catch {
    report.checks.database = 'error';
    report.status = 'degraded';
  }

  try {
    await redis.ping();
    report.checks.redis = 'ok';
  } catch {
    report.checks.redis = 'error';
    report.status = 'degraded';
  }

  try {
    const health = await getCoordinatorHealth();
    report.checks.coordinator = health.status || 'ok';
  } catch {
    report.checks.coordinator = 'error';
    report.status = 'degraded';
  }

  return res.status(report.status === 'ok' ? 200 : 503).json(report);
});
