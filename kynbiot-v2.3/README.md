# KYNBIOT v2.3 – Distributed Multi-User AI Avatar System

Production-ready scaffold for a distributed setup across two Windows 11 nodes connected via Tailscale.

## Architecture

### Node 1 (`100.67.122.46`)
- Express Gateway (`:3100`)
- FastAPI Coordinator (`:8001`)
- PostgreSQL 15
- Redis 7
- Ollama

### Node 2 (`100.67.122.99`)
- NVIDIA NIM (`:8000`)
- Resource Watchdog (CPU/GPU/process-based pause/resume)

## Core Features
- JWT authentication (`/api/auth/register`, `/api/auth/login`)
- Avatar management (`GET/POST/DELETE /api/avatars`)
- Chat routing (`POST /api/chat`) with cascade: **NIM → Ollama → Groq**
- Health endpoints (`/api/health`, `/v1/health`)
- PostgreSQL action logging and indexed query paths
- Security baseline: Helmet, CORS, rate limiting, bcrypt password hashing, centralized error handlers

## Project Layout

```text
kynbiot-v2.3/
├── coordinator/
├── gateway/
├── watchdog/
├── installer/INSTALL_ALL.ps1
├── docker-compose.gateway.yml
└── docker-compose.compute.yml
```

## Environment Variables

Create `.env` in `kynbiot-v2.3/` (installer does this automatically):

```env
JWT_SECRET=<strong-random-secret>
POSTGRES_PASSWORD=<strong-random-password>
GROQ_API_KEY=<optional>
CORS_ORIGIN=*
```

## Run (manual)

### Node 1
```bash
docker compose -f docker-compose.gateway.yml --env-file .env up -d --build
```

### Node 2
```bash
docker compose -f docker-compose.compute.yml up -d --build
```

## API Overview

### Auth
- `POST /api/auth/register`
- `POST /api/auth/login`

### Avatars
- `GET /api/avatars`
- `POST /api/avatars`
- `DELETE /api/avatars/:id`

### Chat
- `POST /api/chat`
  - Body: `{ "avatar_id": "<uuid>", "input_text": "..." }`
  - Coordinator checks avatar autonomy permissions and routes to available LLM service.

### Health
- `GET /api/health` (gateway composite status)
- `GET /v1/health` (coordinator cached service status)

## Watchdog Behavior
- Poll interval: 10s
- Pause NIM if:
  - CPU > 70%, or
  - GPU > 50%, or
  - `dazstudio.exe` / `photoshop.exe` is detected
- Resume NIM after 5 minutes idle

## Security Notes
- Use HTTPS/TLS termination at edge reverse proxy in production
- Restrict CORS origin(s)
- Rotate JWT secret and DB passwords regularly
- Keep Groq key in secure secret manager where possible

## Windows Installer

Run in elevated PowerShell on Node 1:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
.\installer\INSTALL_ALL.ps1
```

This script:
1. Generates secrets
2. Starts Node 1 stack
3. Bootstraps Node 2 stack remotely via `Invoke-Command`
4. Validates health endpoint
