from contextlib import asynccontextmanager
import psycopg
from redis.asyncio import Redis

from app.core.config import settings


redis_client = Redis.from_url(settings.redis_url, decode_responses=True)


@asynccontextmanager
async def db_conn():
    conn = await psycopg.AsyncConnection.connect(settings.database_url)
    try:
        yield conn
    finally:
        await conn.close()
