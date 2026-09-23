# AI Refund System Frontend

Nuxt application for the customer and support experiences. Feature pages are added in later tasks; this directory currently provides the typed UI, state, realtime-client, styling, toast, and test foundations.

## Toolchain

The dependency lock was generated and validated with:

- Node.js 22.23.2 (LTS)
- pnpm 11.25.0 through Corepack
- Nuxt 4.5.2

`package.json` accepts maintained Node.js releases from version 22 onward. Use the pinned `packageManager` value and commit the lockfile so installations remain reproducible.

## Setup

Enable Corepack, then install the locked dependencies:

```bash
corepack enable
pnpm install --frozen-lockfile
```

## Development Server

Start the development server on `http://localhost:3000`:

```bash
pnpm dev
```

Nuxt reads the browser-facing Laravel API origin from `NUXT_PUBLIC_API_BASE`. Server-side requests may use `NUXT_API_BASE` when the backend has a different internal hostname, as it does in Docker Compose. Keep these values in the local environment files; no service URL or credential is embedded in frontend source.

## Quality checks

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```

## Production build

```bash
pnpm build
```
