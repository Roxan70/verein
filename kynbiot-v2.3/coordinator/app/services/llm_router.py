import time

import httpx
from fastapi import HTTPException

from app.core.config import settings
from app.db.clients import redis_client


async def _nim_call(prompt: str) -> str:
    async with httpx.AsyncClient(timeout=20) as client:
        resp = await client.post(f"{settings.nim_url}/v1/chat/completions", json={
            'model': 'meta/llama-3.1-8b-instruct',
            'messages': [{'role': 'user', 'content': prompt}],
            'temperature': 0.2,
        })
        resp.raise_for_status()
        data = resp.json()
        return data['choices'][0]['message']['content']


async def _ollama_call(prompt: str) -> str:
    async with httpx.AsyncClient(timeout=25) as client:
        resp = await client.post(f"{settings.ollama_url}/api/generate", json={
            'model': 'llama3.1',
            'prompt': prompt,
            'stream': False,
        })
        resp.raise_for_status()
        return resp.json().get('response', '')


async def _groq_call(prompt: str) -> str:
    if not settings.groq_api_key:
        raise HTTPException(status_code=503, detail='Groq API key not configured')
    async with httpx.AsyncClient(timeout=25) as client:
        resp = await client.post(
            settings.groq_api_url,
            headers={'Authorization': f'Bearer {settings.groq_api_key}'},
            json={
                'model': 'llama-3.1-8b-instant',
                'messages': [{'role': 'user', 'content': prompt}],
                'temperature': 0.2,
            },
        )
        resp.raise_for_status()
        return resp.json()['choices'][0]['message']['content']


async def route_with_cascade(prompt: str):
    attempts = [
        ('nim', _nim_call),
        ('ollama', _ollama_call),
        ('groq', _groq_call),
    ]

    last_error = None
    for service_name, handler in attempts:
        start = time.perf_counter()
        try:
            output = await handler(prompt)
            elapsed = int((time.perf_counter() - start) * 1000)
            await redis_client.set(f'coordinator:last_service:{service_name}', 'ok', ex=300)
            return output, service_name, elapsed
        except Exception as exc:  # noqa: BLE001
            last_error = f'{service_name} failed: {exc}'
            await redis_client.set(f'coordinator:last_service:{service_name}', 'fail', ex=300)

    raise HTTPException(status_code=503, detail=f'All LLM services unavailable: {last_error}')
