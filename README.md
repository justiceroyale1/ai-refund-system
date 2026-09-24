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

The supported startup command stays attached and watches both applications. Nuxt source changes are synchronized into its development container and applied through Vite hot module replacement. Laravel source changes are synchronized into the backend, Horizon, Reverb, and scheduler containers, then those PHP processes restart so they load the updated code. The equivalent direct command is:

```bash
docker compose up --build --watch
```

Use `docker compose up --build -d` when a detached stack without source watching is preferred. The Dockerfiles retain explicit `production` targets for production-image builds; Compose intentionally uses the development targets for the local runtime.

The startup script creates both ignored service environment files before Compose loads them:

- Edit `docker/backend/.env` to change Laravel, PostgreSQL, Redis, queue, Reverb, AI provider, and related backend settings.
- Edit `docker/frontend/.env` to change Nuxt public runtime settings and the published frontend port.

Their safe templates are `docker/backend/.env.example` and `docker/frontend/.env.example`. Rerun the startup script after an environment change so Compose recreates affected containers with the new values. Dependency manifest and lockfile changes trigger watched image rebuilds. Migration files are synchronized like other Laravel source, but schema changes remain explicit: run the migration service manually rather than applying database migrations automatically on every edit.

For host-level Laravel commands and quality tooling, the backend directory contains the safe, tracked `backend/.env.example` template. Create the ignored local environment file when it is missing:

```bash
cp backend/.env.example backend/.env # if it doesn't already exist
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

The migration service applies outstanding migrations and then runs the additive demo seeders before the backend starts. Stable fixture identifiers ensure repeated starts add missing records without duplicating fixtures or resetting conversations you have continued. PostgreSQL data, Redis data, and Laravel runtime storage use named volumes.

## Demo conversation data

Every demo customer starts with one interactive active conversation and one resolved historical conversation. The active stages and historical outcomes are intentionally distributed so the customer switcher, conversation history, quick actions, chat bubbles, timestamps, and decision badges can be reviewed without creating data manually. Each transcript contains the realistic guided exchanges that lead to its current state: active examples contain two, four, six, or eight bubbles, and resolved examples contain the complete ten-bubble order, item, reason, details, and outcome flow. The processed-refund example adds a system status bubble.

| Demo customer | Active example | Resolved example |
| --- | --- | --- |
| James Munroe | Select an order | Approved damaged item |
| Amelia Carter | Select an item | Approved incorrect item |
| Marcus Bennett | Select a refund reason | Denied final-sale item |
| Nina Patel | Describe item damage | Denied expired refund window |
| Gabriel Okafor | Select an order | Escalated high-value item |
| Olivia Chen | Select an item | Escalated changed-mind request |
| Ethan Williams | Select a refund reason | Escalated missing item |
| Sophia Rossi | Describe an incorrect item | Approved and processed refund |
| Lucas Ferreira | Select an order | Escalated other reason |
| Grace Kim | Select a refund reason | Denied final-sale item |
| Daniel Brooks | Explain a changed-mind request | Denied prompt-manipulation attempt against a final-sale item |
| Amina Yusuf | Select an order | Approved damaged item |
| Noah Thompson | Select an item | Approved incorrect item |
| Maya Singh | Select a refund reason | Escalated changed-mind request |
| Henry Collins | Explain another issue | Denied final-sale item |

Active examples are normal workflow records and can be continued with free-form messages or the offered quick actions. Resolved examples include coherent refund requests, policy checks, audit events, and refunds for approved outcomes. Historical selections retain the same structured metadata as normal quick-action messages. Rerunning the seeders backfills only missing fixture turns and preserves every message or state change added while using the demo.

Run the additive seeders manually with:

```bash
docker compose exec backend php artisan db:seed --force --no-interaction
```

The following commands are destructive and delete all application data in the configured local database. Drop every table and rerun migrations without immediately seeding:

```bash
docker compose exec backend php artisan migrate:fresh --force --no-interaction
```

Drop every table, rerun migrations, and recreate the complete demo dataset:

```bash
docker compose exec backend php artisan migrate:fresh --seed --force --no-interaction
```

An unseeded database created by the first command remains empty only until the next normal Compose startup, because the migration service runs the additive seeders automatically.

## Runtime inspection

Check all seven services and follow their logs with:

```bash
docker compose ps
docker compose logs --tail=100 backend frontend postgres redis horizon reverb scheduler
```

## Configuration safety

The generated `docker/backend/.env` and `docker/frontend/.env` files are the local Docker environment files, while `backend/.env` supports host-level Laravel commands. Replace placeholder values locally and never commit real Gemini credentials, application keys, Reverb secrets, production database credentials, or any `.env` file.

Full setup, architecture, testing, security, and walkthrough documentation will be completed alongside the application.
