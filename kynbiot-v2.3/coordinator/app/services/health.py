import asyncio
import json
from datetime import datetime, timezone

import httpx

from app.core.config import settings
from app.db.clients import redis_client


SERVICE_TARGETS = {
    'nim': f"{settings.nim_url}/health",
    'ollama': f"{settings.ollama_url}/api/tags",
    'groq': settings.groq_api_url,
}


async def check_service(client: httpx.AsyncClient, name: str, url: str):
    try:
        resp = await client.get(url, timeout=8)
        ok = 200 <= resp.status_code < 500
        return name, {'status': 'up' if ok else 'down', 'code': resp.status_code}
    except Exception as exc:  # noqa: BLE001
        return name, {'status': 'down', 'error': str(exc)}


async def health_loop(stop_event: asyncio.Event):
    while not stop_event.is_set():
        snapshot = {'timestamp': datetime.now(timezone.utc).isoformat(), 'services': {}}
        async with httpx.AsyncClient() as client:
            for name, url in SERVICE_TARGETS.items():
                svc_name, svc_data = await check_service(client, name, url)
                snapshot['services'][svc_name] = svc_data

        await redis_client.set('coordinator:service_health', json.dumps(snapshot), ex=120)
        await asyncio.sleep(settings.health_poll_interval_s)
