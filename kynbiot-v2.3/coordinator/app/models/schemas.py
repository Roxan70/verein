from pydantic import BaseModel


class RouteChatRequest(BaseModel):
    user_id: str
    avatar_id: str
    input_text: str


class RouteChatResponse(BaseModel):
    output_text: str
    service_used: str
    response_time_ms: int
