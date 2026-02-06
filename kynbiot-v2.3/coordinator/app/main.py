import asyncio
from contextlib import asynccontextmanager

from fastapi import FastAPI

from app.api.routes import router
from app.services.health import health_loop


@asynccontextmanager
async def lifespan(_app: FastAPI):
    stop_event = asyncio.Event()
    task = asyncio.create_task(health_loop(stop_event))
    yield
    stop_event.set()
    await task


app = FastAPI(title='KYNBIOT Coordinator', version='2.3.0', lifespan=lifespan)
app.include_router(router)
