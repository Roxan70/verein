import express from 'express';
import { authenticate } from '../middleware/auth.js';
import { routeChat } from '../services/coordinatorClient.js';

export const chatRouter = express.Router();
chatRouter.use(authenticate);

chatRouter.post('/', async (req, res, next) => {
  try {
    const { avatar_id, input_text } = req.body;
    if (!avatar_id || !input_text) {
      return res.status(400).json({ error: 'avatar_id and input_text are required' });
    }

    const response = await routeChat(
      {
        user_id: req.user.sub,
        avatar_id,
        input_text
      },
      req.headers.authorization
    );

    return res.json(response);
  } catch (err) {
    if (err.response) {
      return res.status(err.response.status).json(err.response.data);
    }
    return next(err);
  }
});
