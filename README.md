# AI Refund System

This project is a production-minded demonstration of an AI-assisted e-commerce refund workflow. AI interprets customer language, while trusted Laravel services and PostgreSQL data retain exclusive authority over eligibility, financial decisions, and refund execution.

## Repository structure

```text
.
├── backend/             # Laravel API and domain application
├── frontend/            # Nuxt customer and admin applications
├── docker/              # Container definitions and entrypoints
│   ├── backend/
│   │   └── .env.example # Safe backend configuration template
│   └── frontend/
│       └── .env.example # Safe frontend configuration template
└── docker-compose.yml   # Local multi-service runtime
```

## Local runtime

The local stack contains separate services for:

- Laravel backend;
- Nuxt frontend;
- PostgreSQL;
- Redis;
- Laravel Horizon;
- Laravel Reverb;
- Laravel Scheduler.

Start the stack with:

```bash
./docker/start.sh
```

The startup script creates both ignored service environment files before Compose loads them:

- Edit `docker/backend/.env` to change Laravel, PostgreSQL, Redis, queue, Reverb, AI provider, and related backend settings.
- Edit `docker/frontend/.env` to change Nuxt public runtime settings and the published frontend port.

Their safe templates are `docker/backend/.env.example` and `docker/frontend/.env.example`. Rerun the startup script after an environment change so Compose recreates affected containers with the new values.

The Laravel application directory intentionally contains neither `.env` nor `.env.example`. The backend image build verifies that neither file is copied into `/var/www/html`; Laravel receives its configuration exclusively through the Docker service environment.

The application endpoints are:

- Nuxt frontend: `http://localhost:3000`
- Laravel backend: `http://localhost:8000`
- Reverb WebSocket server: `ws://localhost:8080`
- Horizon dashboard: `http://localhost:8000/horizon`

PostgreSQL is published on `5432` and Redis on `6380` for local development tools. The Redis container still listens on `6379` inside the Compose network. Override `POSTGRES_PORT` or `REDIS_FORWARD_PORT` in `docker/backend/.env` when those host ports are already occupied.

The backend container applies outstanding migrations before it starts serving requests. PostgreSQL data, Redis data, and Laravel runtime storage use named volumes.

## Runtime inspection

Check all seven services and follow their logs with:

```bash
docker compose ps
docker compose logs --tail=100 backend frontend postgres redis horizon reverb scheduler
```

## Configuration safety

The generated `docker/backend/.env` and `docker/frontend/.env` files are the local Docker environment files. Replace placeholder values there and never commit real Gemini credentials, application keys, Reverb secrets, or production database credentials.

Full setup, architecture, testing, security, and walkthrough documentation will be completed alongside the application.
