# TestGator

Repositories: [testgator_client](https://github.com/arkdevuk/testgator_client) (frontend) · [testgator_server](https://github.com/arkdevuk/testgator_server) (backend)

**TestGator helps teams organize software testing to fight back against bugs.**

Built for non-technical testers — no setup, no jargon. Just clear testing plans, simple feedback, and results everyone can understand. Instead of asking testers to file bug reports through a dev tool, TestGator lets them sign in with nothing but their email: they enter it, get a one-time code, and land straight in their assigned testing plan. They work through it step by step, calling each one **PASS / PASS (with bug) / FAILED** with comments, screenshots, and files where it helps — while TestGator quietly captures browser, OS, screen resolution, and geolocation in the background, so devs get context instead of "it's broken, trust me."

`Testing plans` · `Releases` · `Feedback` · `Open source`

## Why TestGator

- **Reduce testing chaos** — bring structure to testing so teams can focus on building great software instead of chasing testers for status updates.
- **Make feedback simple** — testers share clear, structured feedback with files and context, without learning a dev tool.
- **Keep quality visible** — progress stays transparent and collaboration effortless, for testers and developers alike.

## Features

- **Interactive testing plans** — dev teams build structured, step-by-step test plans.
- **Email OTP login for testers** — testers sign in with their email and a one-time code, no password or account setup.
- **Simple feedback** — PASS / PASS (with bug) / FAILED, plus comments and file/screenshot uploads.
- **Automatic environment capture** — browser, OS, screen resolution, geolocation, no manual reporting.
- **Projects & releases** — plans are organized under releases, releases under projects.
- **Role-based tester assignment** — reuse testers across plans or assign fresh ones.
- **Two auth modes for the product/dev team** — local database accounts or LDAP, selectable per deployment (testers always use email OTP, separate from this).

## Architecture

TestGator ships as two independently deployed applications:

| Component | Path | Stack |
|---|---|---|
| **Client** | `testgator_client` | React 19 + Vite, served as a static build via Nginx |
| **Server** | `testgator_server` | Symfony 7.2 + API Platform 4 (PHP ≥8.4), running on FrankenPHP/Caddy |

The server also expects a PostgreSQL database and, for file uploads, an S3-compatible bucket. Team/dev logins can run against the server's own internal user database (default) or against an LDAP server — LDAP is optional, not required. A Mercure hub (bundled into the FrankenPHP image) handles realtime updates.

## Repository layout

- `testgator_client` — the tester/dev-facing web GUI.
- `testgator_server` — the REST API: projects, releases, plans, feedback, auth, file uploads.

## Local development

Prerequisites: Docker, PostgreSQL (or use the bundled dev DB in the server's `compose.yml`).

```bash
# server
cd testgator_server
cp .env .env.local   # then edit secrets
make up               # docker compose up, dev image with hot reload

# client
cd testgator_client
npm install
npm run dev
```

See each package's own docs for details — this file focuses on shipping the published Docker images.

## License

TestGator is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0-only)** — see [`testgator_server/LICENSE`](testgator_server/LICENSE) / [`testgator_client/LICENSE`](testgator_client/LICENSE).

In short: you're free to use, modify, and redistribute the code, including running it as a hosted service — but the AGPL's network clause applies. If you run a modified version and make it available to users over a network, you must offer those users the corresponding modified source code.

A couple of things the AGPL does **not** grant, per the project's [`NOTICE`](testgator_server/NOTICE) file:

- The **TestGator name, logo, and branding** aren't covered by the license — you can't present a fork or self-hosted instance as the official project without arkdevuk's permission.
- "Official" TestGator releases, hosted services, support, and docs come only from arkdevuk or parties they explicitly authorize.

If you fork or self-host, keep the LICENSE/NOTICE files intact and identify your instance as independent/unofficial.

---

# Deploying to Docker Swarm

This section deploys the published images:

- `ghcr.io/arkdevuk/testgator-client`
- `ghcr.io/arkdevuk/testgator-server`

The client repo publishes its image to GHCR via GitHub Actions on version tags (`vX.Y.Z`). Use a pinned version tag in production rather than `latest` for both images.

## Prerequisites

- A Swarm cluster (`docker swarm init` if you don't have one).
- An external PostgreSQL 16 instance reachable from the swarm (the server image doesn't bundle a database).
- An S3-compatible bucket if you want file uploads to survive across replicas/restarts (see note below).
- A domain name pointed at your swarm ingress.

## 1. Generate the JWT signing key

The server signs auth tokens with an RS256 keypair loaded from `data/JWT.prod/testgator.key` (private) and `testgator.pub` (public) inside the container. These are **not** baked into the image — you must generate and mount them yourself:

```bash
mkdir -p jwt_prod
openssl genrsa -out jwt_prod/testgator.key 4096
openssl rsa -in jwt_prod/testgator.key -pubout -out jwt_prod/testgator.pub
```

Keep `testgator.key` secret. Mount this directory (or a Docker secret containing both files) at `/app/data/JWT.prod/` in the server container. Losing or rotating this key invalidates every issued session token.

## 2. Setting the frontend's API URL (`VITE_API_URL`)

The client image resolves its API base URL at **container start**, not at build time. `docker-entrypoint.sh` regenerates `/usr/share/nginx/html/env.js` from the container's environment on every start, writing `window.__ENV__.VITE_API_URL`; `index.html` loads that file before the app bundle, and `src/Services/Http.js` reads it first, ahead of any build-time value or the `localhost` fallback.

Practically: set `VITE_API_URL` on the `client` service in your stack file (see the example below) and the same published `ghcr.io/arkdevuk/testgator-client` image will call that API — no rebuild needed. Changing it later is a normal `docker service update --env-add VITE_API_URL=... testgator_client`, followed by a restart of the running tasks to regenerate `env.js`.

## 3. Server environment variables

| Variable | Required | Notes |
|---|---|---|
| `APP_ENV` | yes | `prod` |
| `APP_SECRET` | yes | Symfony secret, random 32+ char string |
| `APP_URL` | yes | Public URL of the API, e.g. `https://api.example.com` |
| `SERVER_NAME` | yes | Passed to Caddy, e.g. `https://api.example.com` |
| `DATABASE_URL` | yes | `postgresql://user:pass@host:5432/dbname?serverVersion=16&charset=utf8` |
| `TRUSTED_PROXIES` | recommended | CIDR ranges of your reverse proxy/ingress |
| `TRUSTED_HOSTS` | recommended | Regex matching your public hostname |
| `CORS_ALLOW_ORIGIN` | yes | Regex matching the client's origin |
| `MERCURE_PUBLISHER_JWT_KEY` / `MERCURE_SUBSCRIBER_JWT_KEY` | yes | Set your own — **do not** leave the `compose.yml` default (`!ChangeThisMercureHubJWTSecretKey!`); it's a known placeholder |
| `APP_AUTH_MODE` | yes | `db` (local accounts) or `ldap` |
| `LDAP_*` | if `APP_AUTH_MODE=ldap` | `LDAP_QUERY_STRING` (use `ldaps://` in prod), `LDAP_BASE_DN`, `LDAP_ADMIN_UID`, `LDAP_ADMIN_PASSWORD`, `LDAP_MUST_HAVE_GROUP` |
| `FILE_STORAGE_MODE` | yes | Set to `bucket`. `local` mode exists as a value but file storage for it is currently a stub in the codebase (`FileService` has a `// todo : implement local file storage` with no actual write path) — it will not persist uploads. Bucket mode is effectively required for a working deployment, swarm or not |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_ENDPOINT`, `AWS_BUCKET`, `AWS_PUBLIC_BUCKET`, `PUBLIC_URL_BUCKET`, `PUBLIC_URL_PUBLIC_BUCKET` | yes (with bucket mode) | Works with any S3-compatible provider (AWS S3, MinIO, etc.). `AWS_BUCKET`/`PUBLIC_URL_BUCKET` back private, signed-URL files; `AWS_PUBLIC_BUCKET`/`PUBLIC_URL_PUBLIC_BUCKET` back public assets like avatars — both pairs are read unconditionally, so both buckets must exist |
| `MAILER_DSN` | yes | e.g. `smtp://user:pass@host:587` |
| `MAILER_SENDER_ADDRESS` | yes | From address for outgoing mail |
| `SENTRY_DSN` | optional | Leave blank to disable |

Notes from the project's own audit that still apply: rotate any credentials that ever sat in a local `.env.local`, and prefer `ldaps://` over `ldap://` in production.

## 4. Migrations

The server's entrypoint runs pending Doctrine migrations automatically on container start when `DATABASE_URL` is set and a `migrations/` directory is present — no separate migration step is required in the stack file, but it does mean the first replica to start will briefly run migrations before serving traffic.

## 5. Example stack file

```yaml
# stack.yml
version: "3.9"

services:
  server:
    image: ghcr.io/arkdevuk/testgator-server:1.0.0
    environment:
      APP_ENV: prod
      APP_SECRET: ${APP_SECRET}
      APP_URL: https://api.example.com
      SERVER_NAME: https://api.example.com
      DATABASE_URL: postgresql://testgator:${DB_PASSWORD}@postgres:5432/testgator?serverVersion=16&charset=utf8
      TRUSTED_PROXIES: 0.0.0.0/0
      TRUSTED_HOSTS: '^api\.example\.com$$'
      CORS_ALLOW_ORIGIN: '^https://app\.example\.com$$'
      MERCURE_PUBLISHER_JWT_KEY: ${MERCURE_JWT_SECRET}
      MERCURE_SUBSCRIBER_JWT_KEY: ${MERCURE_JWT_SECRET}
      APP_AUTH_MODE: db
      FILE_STORAGE_MODE: bucket
      AWS_ACCESS_KEY_ID: ${AWS_ACCESS_KEY_ID}
      AWS_SECRET_ACCESS_KEY: ${AWS_SECRET_ACCESS_KEY}
      AWS_DEFAULT_REGION: us-east-1
      AWS_ENDPOINT: https://s3.example.com
      AWS_BUCKET: testgator-files
      PUBLIC_URL_BUCKET: https://s3.example.com/testgator-files
      MAILER_DSN: ${MAILER_DSN}
      MAILER_SENDER_ADDRESS: noreply@example.com
    volumes:
      - jwt_prod:/app/data/JWT.prod
      - server_var:/app/var
    networks:
      - traefik-public
      - internal
    deploy:
      replicas: 2
      update_config:
        order: start-first
      labels:
        - traefik.enable=true
        - traefik.http.routers.testgator-api.rule=Host(`api.example.com`)
        - traefik.http.routers.testgator-api.tls.certresolver=le
        - traefik.http.services.testgator-api.loadbalancer.server.port=80

  client:
    image: ghcr.io/arkdevuk/testgator-client:1.0.0
    environment:
      VITE_API_URL: https://api.example.com
    networks:
      - traefik-public
    deploy:
      replicas: 2
      labels:
        - traefik.enable=true
        - traefik.http.routers.testgator-app.rule=Host(`app.example.com`)
        - traefik.http.routers.testgator-app.tls.certresolver=le
        - traefik.http.services.testgator-app.loadbalancer.server.port=80

networks:
  traefik-public:
    external: true
  internal:
    driver: overlay

volumes:
  jwt_prod:
  server_var:
```

This example assumes an external Traefik (or similar) ingress handling TLS termination and routing — swap the labels for whatever proxy you run, or expose the FrankenPHP container's own Caddy directly on 80/443 if you'd rather let it manage certificates itself (in which case mount volumes for `/data` and `/config` so certs persist across redeploys).

Before deploying, populate `jwt_prod` with the keypair from step 1 (e.g. `docker cp` the files into the volume, or seed it via a one-off `docker run`), and create `internal` as an overlay network reachable by your Postgres instance.

Deploy with:

```bash
docker stack deploy -c stack.yml testgator
```

## 6. Known limitations

- **Worker mode is off.** The server doesn't run FrankenPHP in worker mode — the LDAP extension breaks long-lived worker processes ([dunglas/frankenphp#457](https://github.com/dunglas/frankenphp/issues/457)). Performance is still reasonable for typical usage, but don't expect worker-mode throughput.
- **`FILE_STORAGE_MODE=local` doesn't actually store files** in the current codebase — use `bucket` mode.
