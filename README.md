# RideLahore: ride booking for Lahore

A small ride-hailing app for Lahore.

- **Rider:** picks a pickup and destination, sees the fare and pickup time for
  Economy and Comfort, and books a ride. They follow each step live, tap
  "I'm coming" when the driver is waiting, and can cancel until the trip starts.
- **Driver:** sees nearby requests and accepts one, then presses Arrived →
  On the way to destination → Completed.

**Contents:** [Quick start](#quick-start) ·
[Try it](#try-it) ·
[Troubleshooting](#troubleshooting) ·
[Tests](#tests) ·
[API](#api) ·
[Project layout](#project-layout) ·
[Configuration and secrets](#configuration-and-secrets) ·
[Stop or reset](#stop-or-reset)

## Documents

| Document | What's in it |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Diagrams, data model, and answers to the 8 architecture questions |
| [docs/openapi.yaml](docs/openapi.yaml) | API reference (OpenAPI 3.1) |
| [docs/DECISIONS.md](docs/DECISIONS.md) | Main design choices and their trade-offs |
| [docs/LIMITATIONS.md](docs/LIMITATIONS.md) | Known limitations and proposed improvements |
| [docs/AI_DISCLOSURE.md](docs/AI_DISCLOSURE.md) | AI tools used and how their output was checked |

## Tech stack

| Part | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.4), MySQL 8.4, Sanctum tokens, Reverb WebSockets |
| Frontend | Next.js 16, React 19, TypeScript, Tailwind CSS 4, SWR |
| Tests | Pest (unit, feature, real concurrency), Vitest + Testing Library, Playwright |

---

## Quick start

**You need:** Docker Desktop (or Docker Engine with Compose v2), and it must
be running.

### 1. Create the settings file

This step is required. Without it, the API cannot connect to the database.

```bash
cp .env.example .env
```

### 2. Start everything

```bash
docker compose up -d --build
```

### 3. Wait until it is ready

The first start installs packages, runs migrations and loads demo data. This
takes a few minutes. Check progress with:

```bash
docker compose ps
```

It is ready when `api` shows **(healthy)**.

### 4. Open the app

| What | Address |
|---|---|
| App | http://localhost:3000 |
| API | http://localhost:8000/api |
| Health check | http://localhost:8000/api/health |
| WebSockets | ws://localhost:8080 |
| MySQL | `127.0.0.1`, port `3307` (for database tools, not a browser) |

---

## Try it

### Demo accounts

All demo passwords are **`password`** (local demo data only). The sign-in
page can fill these in for you.

| Role | Email | Notes |
|---|---|---|
| Rider | `rider@example.com`, `rider2@example.com` | |
| Driver | `driver1@example.com` … `driver4@example.com` | Economy, online |
| Driver | `driver5@example.com` … `driver7@example.com` | Comfort, online |
| Driver | `driver8@example.com` | Offline |

### A full ride, step by step

1. Sign in as a **rider** in a normal browser window.
2. Sign in as **`driver1@example.com`** in a private (incognito) window.
3. **Rider:** book **Liberty Market → MM Alam Road** with **Economy**.
4. **Driver:** press **Accept**, then **Arrived**.
5. **Rider:** tap **I'm coming**.
6. **Driver:** press **On the way to destination**, then **Completed**.

> **Driver can't see the request?** Drivers only see requests within 6 km of
> where they are, and only for their own car type. On the driver screen, set
> **Your location** to the rider's pickup place and the request appears straight
> away.

---

## Troubleshooting

| Problem | What to do |
|---|---|
| Warnings that a variable `is not set`, and `api` exits | You skipped step 1. Run `cp .env.example .env`, then `docker compose up -d` |
| http://localhost:3000 doesn't load | It is still starting. Wait until `docker compose ps` shows `api` as healthy |
| The first start fails while downloading | Slow connection. The container retries 3 times; if it still fails, run `docker compose up -d` again and it continues from what was already downloaded |
| A port is already in use | Change `WEB_HOST_PORT`, `API_HOST_PORT`, `MYSQL_HOST_PORT` or `REVERB_HOST_PORT` in `.env` |
| Frontend code changes don't appear (Windows) | Run `docker compose restart web`. The dev server can't see file changes through Docker on Windows |
| Something else | Check the logs: `docker compose logs api` or `docker compose logs web` |

---

## Tests

### Backend

```bash
docker compose exec api php artisan test
```

These run against a separate `ride_booking_test` database, which is created
automatically. The test setup refuses to run against any database whose name
doesn't end in `_test`, so your demo data is safe.

| Suite | Covers |
|---|---|
| `tests/Unit` | Fare formula (minimum fare, rounding, commission split), allowed and rejected state transitions |
| `tests/Feature` | Ride creation, validation errors, service area, driver availability, location and matching, accept / arrive / "I'm coming" / start / cancel / complete rules, who may see a ride, idempotent retries and key reuse, login and error formats, private channel access |
| `tests/Concurrency` | Separate processes hitting MySQL at the same moment: 5 drivers accept one ride (exactly 1 wins), one driver accepts two rides, a rider requests 3 times at once, cancel races accept |

### Frontend unit tests

```bash
docker compose exec web npm test
```

These cover loading and error states, retry, cancel confirmation, the booking
form, and the API client's retry and sign-out behaviour.

### Frontend checks

Run these on your computer, with Node 22:

```bash
cd frontend && npm ci && npm run typecheck && npm run lint && npm run build
```

### End-to-end test

A rider books, a driver accepts, arrives, drives and completes, the rider taps
"I'm coming", and both screens follow each step. It runs on your computer
against the running app:

```bash
cd frontend && npx playwright install chromium && npx playwright test
```

To use an installed Chrome instead of downloading Playwright's browser, set
`PW_BROWSER_CHANNEL=chrome` before `npx playwright test`.

> **Known issue:** right after the app starts, this test can fail at the
> driver's sign-in (seen with Docker on Windows). Open http://localhost:3000
> once, wait a minute, and run it again. See
> [docs/LIMITATIONS.md](docs/LIMITATIONS.md).

---

## API

The full reference is in [docs/openapi.yaml](docs/openapi.yaml). To read it as
a web page, run this from the project folder (PowerShell), then open
http://localhost:8082:

```powershell
docker run -d --name ride-docs -p 8082:8080 -e SWAGGER_JSON=/docs/openapi.yaml -v "${PWD}/docs:/docs" swaggerapi/swagger-ui
```

### Try it with curl

**1. Sign in as the rider and keep the token:**

```bash
TOKEN=$(curl -s http://localhost:8000/api/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"email":"rider@example.com","password":"password"}' | sed 's/.*"token":"\([^"]*\)".*/\1/')
```

**2. Request a ride:**

```bash
curl -s http://localhost:8000/api/rides -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: demo-key-0001" -d '{"category":"economy","pickup":{"name":"Liberty Market","lat":31.5106,"lng":74.3436},"dropoff":{"name":"MM Alam Road","lat":31.5155,"lng":74.3523}}'
```

**3. Send step 2 again with the same key.** You get the same ride back, with
`Idempotent-Replayed: true` (add `-i` to see headers), not a second ride.

---

## Project layout

```text
backend/                 Laravel API
  app/Http/              Controllers, Form Requests, Resources, Middleware
  app/Services/          Rides (booking, fares, state machine), Dispatch (matching, availability), Auth
  app/Models, app/Enums  Eloquent models and status/category enums
  database/              Migrations, seeders (Lahore + demo users), factories
  tests/                 Unit, Feature, Concurrency
frontend/                Next.js app
  src/app/               Pages: / (rider), /driver, /login
  src/components/        booking, ride, driver, auth, ui
  src/hooks, src/lib     Session, live updates, API client
  tests/                 unit (Vitest), e2e (Playwright)
docs/                    Architecture, OpenAPI, decisions, limitations
compose.yaml             mysql, api, reverb, web
```

---

## Configuration and secrets

- All configuration comes from environment variables. The `.env` files are
  ignored by git; only `.env.example` files with local placeholder values are
  committed.
- `backend/.env` is created from `backend/.env.example` on the first start,
  with a fresh `APP_KEY`. Docker passes the database and Reverb settings in
  from the root `.env`.
- In production, set real values through the hosting platform's secret
  manager.
- Logs are JSON lines in `backend/storage/logs/api.log`. Each line carries the
  request id that the API also returns in the `X-Request-Id` header.

---

## Stop or reset

Stop the app. Your data is kept:

```bash
docker compose down
```

Delete the database and installed packages too, so everything starts fresh
next time:

```bash
docker compose down -v
```
