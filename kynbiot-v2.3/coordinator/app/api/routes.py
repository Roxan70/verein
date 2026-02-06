import json

from fastapi import APIRouter, Header, HTTPException
from jose import jwt, JWTError

from app.core.config import settings
from app.db.clients import db_conn, redis_client
from app.models.schemas import RouteChatRequest, RouteChatResponse
from app.services.llm_router import route_with_cascade
from app.services.logging_service import log_action
from app.services.permissions import check_permission

router = APIRouter(prefix='/v1')


def decode_auth(auth_header: str | None):
    if not auth_header or not auth_header.startswith('Bearer '):
        raise HTTPException(status_code=401, detail='Missing bearer token')
    token = auth_header[7:]
    try:
        return jwt.decode(token, settings.jwt_secret, algorithms=['HS256'])
    except JWTError as exc:
        raise HTTPException(status_code=401, detail='Invalid token') from exc


@router.get('/health')
async def health():
    cache = await redis_client.get('coordinator:service_health')
    data = json.loads(cache) if cache else {'services': {}, 'timestamp': None}
    return {'status': 'ok', 'cached_health': data}


@router.post('/route-chat', response_model=RouteChatResponse)
async def route_chat(payload: RouteChatRequest, authorization: str | None = Header(default=None)):
    claims = decode_auth(authorization)
    if claims.get('sub') != payload.user_id:
        raise HTTPException(status_code=403, detail='Token user mismatch')

    async with db_conn() as conn:
        result = await conn.execute(
            'SELECT autonomy_level FROM avatars WHERE id = %s AND user_id = %s',
            (payload.avatar_id, payload.user_id),
        )
        row = await result.fetchone()

    if not row:
        raise HTTPException(status_code=404, detail='Avatar not found')

    check_permission(claims.get('role', 'user'), row[0])
    output, service_used, response_ms = await route_with_cascade(payload.input_text)
    await log_action(payload.user_id, payload.avatar_id, payload.input_text, output, service_used, response_ms)
    return RouteChatResponse(output_text=output, service_used=service_used, response_time_ms=response_ms)
