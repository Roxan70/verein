import express from 'express';
import { pool } from '../config/db.js';
import { authenticate } from '../middleware/auth.js';

export const avatarRouter = express.Router();
avatarRouter.use(authenticate);

avatarRouter.get('/', async (req, res, next) => {
  try {
    const result = await pool.query(
      'SELECT id, user_id, name, role, description, autonomy_level, created_at FROM avatars WHERE user_id = $1 ORDER BY created_at DESC',
      [req.user.sub]
    );
    return res.json({ avatars: result.rows });
  } catch (err) {
    return next(err);
  }
});

avatarRouter.post('/', async (req, res, next) => {
  try {
    const { name, role, description = null, autonomy_level } = req.body;
    if (!name || !role || !autonomy_level) {
      return res.status(400).json({ error: 'name, role, autonomy_level are required' });
    }
    const result = await pool.query(
      `INSERT INTO avatars (user_id, name, role, description, autonomy_level)
       VALUES ($1, $2, $3, $4, $5)
       RETURNING id, user_id, name, role, description, autonomy_level, created_at`,
      [req.user.sub, name, role, description, autonomy_level]
    );
    return res.status(201).json({ avatar: result.rows[0] });
  } catch (err) {
    return next(err);
  }
});

avatarRouter.delete('/:id', async (req, res, next) => {
  try {
    const { rowCount } = await pool.query('DELETE FROM avatars WHERE id = $1 AND user_id = $2', [
      req.params.id,
      req.user.sub
    ]);
    if (rowCount === 0) {
      return res.status(404).json({ error: 'avatar not found' });
    }
    return res.status(204).send();
  } catch (err) {
    return next(err);
  }
});
