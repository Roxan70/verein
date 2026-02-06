import axios from 'axios';
import { env } from '../config/env.js';

const client = axios.create({
  baseURL: env.coordinatorUrl,
  timeout: 30000
});

export async function routeChat(payload, authHeader) {
  const { data } = await client.post('/v1/route-chat', payload, {
    headers: { Authorization: authHeader }
  });
  return data;
}

export async function getCoordinatorHealth() {
  const { data } = await client.get('/v1/health');
  return data;
}
