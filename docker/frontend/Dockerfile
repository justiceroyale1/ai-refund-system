FROM node:22-alpine AS build

WORKDIR /app

RUN corepack enable \
    && for attempt in 1 2 3 4 5; do \
        corepack prepare pnpm@11.25.0 --activate && break; \
        if [ "$attempt" = 5 ]; then exit 1; fi; \
        sleep 5; \
    done

COPY frontend/ ./

RUN pnpm config set fetch-retries 5 \
    && pnpm config set fetch-retry-mintimeout 10000 \
    && pnpm config set fetch-retry-maxtimeout 120000 \
    && pnpm install --frozen-lockfile \
    && pnpm build

FROM node:22-alpine

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
