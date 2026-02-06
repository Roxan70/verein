from app.db.clients import db_conn


async def log_action(user_id: str, avatar_id: str, input_text: str, output_text: str, service_used: str, response_time_ms: int):
    async with db_conn() as conn:
        await conn.execute(
            """
            INSERT INTO action_logs (user_id, avatar_id, input_text, output_text, service_used, response_time_ms)
            VALUES (%s, %s, %s, %s, %s, %s)
            """,
            (user_id, avatar_id, input_text, output_text, service_used, response_time_ms),
        )
        await conn.commit()
