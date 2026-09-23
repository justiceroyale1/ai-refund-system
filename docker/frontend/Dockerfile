FROM node:22-alpine AS frontend-base

WORKDIR /app

RUN corepack enable \
    && for attempt in 1 2 3 4 5; do \
        corepack prepare pnpm@11.25.0 --activate && break; \
        if [ "$attempt" = 5 ]; then exit 1; fi; \
        sleep 5; \
    done

COPY docker/frontend/entrypoint.sh /usr/local/bin/frontend-entrypoint

RUN chmod +x /usr/local/bin/frontend-entrypoint \
    && chown node:node /app

COPY --chown=node:node frontend/package.json frontend/pnpm-lock.yaml frontend/pnpm-workspace.yaml ./

USER node

RUN --mount=type=cache,id=frontend-pnpm,target=/home/node/.local/share/pnpm/store,uid=1000,gid=1000 \
    pnpm config set fetch-retries 5 \
    && pnpm config set fetch-retry-mintimeout 10000 \
    && pnpm config set fetch-retry-maxtimeout 120000 \
    && pnpm config set network-concurrency 8 \
    && pnpm config set store-dir /home/node/.local/share/pnpm/store \
    && pnpm install --frozen-lockfile --ignore-scripts

FROM frontend-base AS frontend-source

COPY --chown=node:node frontend/ ./

RUN pnpm postinstall

FROM frontend-source AS development

ENV NODE_ENV=development \
    NITRO_HOST=0.0.0.0 \
    NITRO_PORT=3000

EXPOSE 3000

ENTRYPOINT ["frontend-entrypoint"]
CMD ["pnpm", "dev", "--host", "0.0.0.0", "--port", "3000"]

FROM frontend-source AS build

RUN pnpm build

FROM node:22-alpine AS production

ENV NODE_ENV=production \
    NITRO_HOST=0.0.0.0 \
    NITRO_PORT=3000

WORKDIR /app

COPY --from=build --chown=node:node /app/.output ./.output
COPY --chown=node:node docker/frontend/entrypoint.sh /usr/local/bin/frontend-entrypoint

RUN chmod +x /usr/local/bin/frontend-entrypoint

USER node

EXPOSE 3000

ENTRYPOINT ["frontend-entrypoint"]
CMD ["node", ".output/server/index.mjs"]
