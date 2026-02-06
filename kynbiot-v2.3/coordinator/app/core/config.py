from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file='.env', env_file_encoding='utf-8', extra='ignore')

    app_name: str = 'kynbiot-coordinator'
    port: int = 8001
    database_url: str
    redis_url: str
    jwt_secret: str

    nim_url: str = 'http://nim:8000'
    ollama_url: str = 'http://ollama:11434'
    groq_api_url: str = 'https://api.groq.com/openai/v1/chat/completions'
    groq_api_key: str = ''
    health_poll_interval_s: int = 30


settings = Settings()
