# AI Refund System Backend

Laravel owns the refund conversation workflow, deterministic policy decisions, authorization, idempotency, audit history, and refund persistence. PostgreSQL remains authoritative for customer, order, item, and financial facts.

## Demo seeders

`DatabaseSeeder` runs the base commerce/scenario seeder followed by the conversation seeder. The deterministic dataset contains 15 customers, 30 delivered orders, and two baseline conversations per customer:

- one active thread at an interactive order, item, reason, or detail-collection stage;
- one resolved thread with an approved, denied, or escalated refund request.

Active transcripts contain the realistic exchanges leading to their current state, from two bubbles while choosing an order through eight bubbles while supplying details. Resolved transcripts contain the full ten-bubble guided flow from the opening request through the policy outcome; the processed-refund example also contains a system status message.

Resolved examples are evaluated through the application refund policy so their decisions, policy checks, refunds, messages, and audit records match normal application behavior. Historical customer selections and assistant prompts retain their structured metadata. Stable customer-message UUIDs identify each fixture turn. Repeated seeding backfills only missing turns and does not reset a conversation, rewrite its existing messages, or discard progress after someone continues it.

Normal local Docker startup runs migrations and these additive seeders automatically. To seed manually inside a running stack:

```bash
docker compose exec backend php artisan db:seed --force --no-interaction
```

## Resetting the local database

These commands permanently delete all data in the configured local application database.

Drop all tables and recreate the schema without immediately seeding:

```bash
docker compose exec backend php artisan migrate:fresh --force --no-interaction
```

Drop all tables, recreate the schema, and rebuild the deterministic demo dataset:

```bash
docker compose exec backend php artisan migrate:fresh --seed --force --no-interaction
```

The next normal Compose startup seeds an unseeded schema automatically.

## Backend quality checks

The canonical backend suite runs against the isolated PostgreSQL test database through Docker:

```bash
composer test
composer quality
```

Run focused tests by passing their PHPUnit paths:

```bash
composer test -- tests/Feature/Database/DatabaseSeederTest.php
```

From the backend directory, establish or repair the ignored `backend/.env` symlink before running host-level lightweight tests:

```bash
../docker/ensure-backend-env-link.sh
php artisan test --compact
```

Edit `docker/backend/.env` as the single source of truth; do not edit `backend/.env` independently.
