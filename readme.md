# TestGator — Server

The backend for [TestGator](https://github.com/arkdevuk/testgator) — Symfony 7.2 + API Platform 4 (PHP ≥8.4), running on FrankenPHP/Caddy.

Full documentation — features, architecture, license, and deployment guides (Docker Swarm, Kubernetes, single-node Docker) — lives in the main repository: **[arkdevuk/testgator](https://github.com/arkdevuk/testgator)**. This file only covers running this repo locally, so it doesn't drift out of sync with the main docs.

## Local development

Prerequisites: Docker.

```bash
cp .env .env.local   # then edit secrets
make up               # docker compose up, dev image with hot reload
```

See `Makefile` (`make help`) for the rest: running tests, QA checks, resetting the dev database, etc.
