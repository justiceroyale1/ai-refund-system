# AI Refund System

This project is a production-minded demonstration of an AI-assisted e-commerce refund workflow. AI interprets customer language, while trusted Laravel services and PostgreSQL data retain exclusive authority over eligibility, financial decisions, and refund execution.

## Repository structure

```text
.
├── backend/             # Laravel API and domain application
│   └── .env.example     # Safe host-level Laravel template
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

For host-level Laravel commands and quality tooling, the backend directory contains the safe, tracked `backend/.env.example` template. Create the ignored local environment file when it is missing:

```bash
cp backend/.env.example backend/.env
cd backend
composer quality
```

`composer test` and the test phase of `composer quality` run through a development-only Docker image against the isolated `refund_system_test` database in the existing PostgreSQL service. The runner creates `docker/backend/.env.testing` from its safe `.env.testing.example` template, creates the database when missing, reuses it safely on later runs, and refuses to run when the test and application database names match. Use `.env.testing` for local test-only overrides while shared PostgreSQL credentials continue to come from `docker/backend/.env`.

Run the full backend suite or pass a focused PHPUnit path from `backend/`:

```bash
composer test
composer test -- tests/Feature/Actions/Conversations
```

The equivalent repository-root command is `./docker/test-backend.sh`. The Compose test services are profile-gated and do not run during normal application startup. Host-level PHPUnit retains its in-memory SQLite fallback for intentional lightweight checks, but the canonical approval-gate suite uses PostgreSQL so row-lock and concurrency coverage executes without being skipped.

Docker Compose continues to load `docker/backend/.env`; keep the two safe backend templates aligned when shared variables change. Production backend images exclude both `backend/.env` and `backend/.env.example`, while the ephemeral test container creates an empty runtime-only `.env` so Laravel does not probe for a missing file. All effective test configuration still comes from the Compose environment.

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

The generated `docker/backend/.env` and `docker/frontend/.env` files are the local Docker environment files, while `backend/.env` supports host-level Laravel commands. Replace placeholder values locally and never commit real Gemini credentials, application keys, Reverb secrets, production database credentials, or any `.env` file.

Full setup, architecture, testing, security, and walkthrough documentation will be completed alongside the application.
