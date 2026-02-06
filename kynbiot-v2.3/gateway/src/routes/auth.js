import express from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { pool } from '../config/db.js';
import { env } from '../config/env.js';

export const authRouter = express.Router();

authRouter.post('/register', async (req, res, next) => {
  try {
    const { username, email, password, role = 'user' } = req.body;
    if (!username || !email || !password) {
      return res.status(400).json({ error: 'username, email and password are required' });
    }

    const passwordHash = await bcrypt.hash(password, 12);
    const result = await pool.query(
      `INSERT INTO users (username, email, password_hash, role)
       VALUES ($1, $2, $3, $4)
       RETURNING id, username, email, role, created_at`,
      [username, email, passwordHash, role]
    );

    return res.status(201).json({ user: result.rows[0] });
  } catch (err) {
    if (String(err.message).includes('duplicate key')) {
      return res.status(409).json({ error: 'username or email already exists' });
    }
    return next(err);
  }
});

authRouter.post('/login', async (req, res, next) => {
  try {
    const { email, password } = req.body;
    if (!email || !password) {
      return res.status(400).json({ error: 'email and password are required' });
    }

    const result = await pool.query('SELECT * FROM users WHERE email = $1', [email]);
    const user = result.rows[0];
    if (!user) {
      return res.status(401).json({ error: 'invalid credentials' });
    }

    const ok = await bcrypt.compare(password, user.password_hash);
    if (!ok) {
      return res.status(401).json({ error: 'invalid credentials' });
    }

    const token = jwt.sign({ sub: user.id, email: user.email, role: user.role }, env.jwtSecret, {
      expiresIn: '12h'
    });

    return res.json({ token });
  } catch (err) {
    return next(err);
  }
});
