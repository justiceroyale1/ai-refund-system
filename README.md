# AI Refund System

This project is a production-minded demonstration of an AI-assisted e-commerce refund workflow. AI interprets customer language, while trusted Laravel services and PostgreSQL data retain exclusive authority over eligibility, financial decisions, and refund execution.

## Repository structure

```text
.
├── backend/             # Laravel API and domain application
├── frontend/            # Nuxt customer and admin applications
├── docker/              # Container definitions and entrypoints
├── docker-compose.yml   # Local multi-service runtime
└── .env.example         # Safe local configuration template
```

## Planned runtime

The completed local stack will contain separate services for:

- Laravel backend;
- Nuxt frontend;
- PostgreSQL;
- Redis;
- Laravel Horizon;
- Laravel Reverb;
- Laravel Scheduler.

The target startup command is:

```bash
docker compose up --build
```

## Bootstrap status

This repository currently contains only the approved top-level skeleton. Laravel, Nuxt, and the working container runtime are added by subsequent implementation tasks. The startup command above is therefore a target contract and is not expected to run the application at this checkpoint.

## Configuration safety

Copy `.env.example` to `.env` only for local development. Replace placeholder values locally and never commit real Gemini credentials, application keys, Reverb secrets, or production database credentials.

Full setup, architecture, testing, security, and walkthrough documentation will be completed alongside the application.
