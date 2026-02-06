import pg from 'pg';
import { env } from './env.js';

const { Pool } = pg;

export const pool = new Pool({ connectionString: env.dbUrl });

export async function initDb() {
  await pool.query(`
    CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";

    CREATE TABLE IF NOT EXISTS users (
      id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
      username VARCHAR(64) UNIQUE NOT NULL,
      email VARCHAR(255) UNIQUE NOT NULL,
      password_hash TEXT NOT NULL,
      role VARCHAR(32) NOT NULL DEFAULT 'user',
      created_at TIMESTAMP NOT NULL DEFAULT NOW()
    );

    CREATE TABLE IF NOT EXISTS avatars (
      id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
      user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      name VARCHAR(120) NOT NULL,
      role VARCHAR(120) NOT NULL,
      description TEXT,
      autonomy_level VARCHAR(20) NOT NULL CHECK (autonomy_level IN ('safe', 'assisted', 'admin')),
      created_at TIMESTAMP NOT NULL DEFAULT NOW()
    );

    CREATE TABLE IF NOT EXISTS action_logs (
      id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
      user_id UUID REFERENCES users(id) ON DELETE SET NULL,
      avatar_id UUID REFERENCES avatars(id) ON DELETE SET NULL,
      input_text TEXT,
      output_text TEXT,
      service_used VARCHAR(32),
      response_time_ms INTEGER,
      created_at TIMESTAMP NOT NULL DEFAULT NOW()
    );

    CREATE INDEX IF NOT EXISTS idx_avatars_user_id ON avatars(user_id);
    CREATE INDEX IF NOT EXISTS idx_action_logs_user_id ON action_logs(user_id);
    CREATE INDEX IF NOT EXISTS idx_action_logs_created_at ON action_logs(created_at);
  `);
}
