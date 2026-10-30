# RideLahore: ride booking for Lahore

A small ride-hailing app. A rider picks a pickup and destination, sees the
fare and pickup time for Economy and Comfort, and requests a ride. Nearby
drivers see the request; one accepts, marks "Arrived" at the pickup, sets off
("On the way to destination") and completes the trip. The rider follows each
step live, can tap "I'm coming" when the driver is waiting, and can cancel
until the trip starts.

- **Backend:** Laravel 13 (PHP 8.4), MySQL 8.4, Sanctum tokens, Reverb WebSockets
- **Frontend:** Next.js 16, React 19, TypeScript, Tailwind CSS 4, SWR
- **Tests:** Pest (unit, feature, real concurrency), Vitest + Testing Library, Playwright

| Document | What's in it |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Diagrams, data model, and answers to the 8 architecture questions |
| [docs/openapi.yaml](docs/openapi.yaml) | API reference (OpenAPI 3.1) |
| [docs/DECISIONS.md](docs/DECISIONS.md) | Main design choices and their trade-offs |
| [docs/LIMITATIONS.md](docs/LIMITATIONS.md) | Known limitations and proposed improvements |
| [docs/AI_DISCLOSURE.md](docs/AI_DISCLOSURE.md) | AI tools used and how their output was checked |

## Run it

You need Docker Desktop (or Docker Engine with Compose v2).

```bash
cp .env.example .env
docker compose up -d --build
```

The first start installs packages, runs migrations and loads demo data, which
takes a few minutes. When `docker compose ps` shows `api` as healthy:

| | URL |
|---|---|
| App | http://localhost:3000 |
| API | http://localhost:8000/api (health: `/api/health`) |
| WebSockets | ws://localhost:8080 |
| MySQL | localhost:3307 |

If a port is taken, change it in `.env` (`WEB_HOST_PORT`, `API_HOST_PORT`, …).

**If the first start fails**, check `docker compose logs api`. On a slow
connection, package downloads can time out. The container retries 3 times;
if it still fails, run `docker compose up -d` again and it continues from
the packages already downloaded.

### Demo accounts

All demo passwords are `password` (local demo data only). The sign-in page
can fill these in for you.

| Role | Email | Notes |
|---|---|---|
| Rider | `rider@example.com`, `rider2@example.com` | |
| Driver | `driver1@example.com` … `driver4@example.com` | Economy, online |
| Driver | `driver5@example.com` … `driver7@example.com` | Comfort, online |
| Driver | `driver8@example.com` | Offline |

**Try the whole flow:** sign in as the rider in one browser and as a driver in
a private window. Book Liberty Market → MM Alam Road (Economy), accept it on
the driver screen, then press Arrived → On the way to destination → Completed.
When the driver has arrived, the rider can tap "I'm coming".

**Drivers only see requests within 6 km of where they are** (and in their own
car type). If a request doesn't show up, set the driver's **Your location** on
the driver screen to the rider's pickup place, and it appears right away.

## Tests

**Backend.** These run against a separate `ride_booking_test` database, which
is created automatically. The test setup refuses to run against any database
whose name doesn't end in `_test`.

```bash
docker compose exec api php artisan test
```

| Suite | Covers |
|---|---|
| `tests/Unit` | Fare formula (minimum fare, rounding, commission split), allowed and rejected state transitions |
| `tests/Feature` | Ride creation, validation errors, service area, driver availability and matching, accept / arrive / "I'm coming" / start / cancel / complete rules, who may see a ride, idempotent retries and key reuse, login and error formats, private channel access |
| `tests/Concurrency` | Separate processes hitting MySQL at the same moment: 5 drivers accept one ride (exactly 1 wins), one driver accepts two rides, a rider requests 3 times at once, cancel races accept |

**Frontend unit tests.** These cover loading and error states, retry, cancel
confirmation, the booking form, and the API client's retry and sign-out
behaviour:

```bash
docker compose exec web npm test
```

**Frontend checks** (on the host, with Node 22):

```bash
cd frontend && npm ci && npm run typecheck && npm run lint && npm run build
```

**End-to-end test.** A rider books, a driver accepts, arrives, drives and
completes, the rider taps "I'm coming", and both screens follow each step. It runs on the host against the running stack:

```bash
cd frontend && npx playwright install chromium && npx playwright test
```

To use an installed Chrome instead of downloading Playwright's browser, set
`PW_BROWSER_CHANNEL=chrome` before `npx playwright test`.

## Try the API with curl

```bash
TOKEN=$(curl -s http://localhost:8000/api/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"email":"rider@example.com","password":"password"}' | sed 's/.*"token":"\([^"]*\)".*/\1/')
```

```bash
curl -s http://localhost:8000/api/rides -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: demo-key-0001" -d '{"category":"economy","pickup":{"name":"Liberty Market","lat":31.5106,"lng":74.3436},"dropoff":{"name":"MM Alam Road","lat":31.5155,"lng":74.3523}}'
```

Send the second command again with the same key: you get the same ride back
with `Idempotent-Replayed: true` (add `-i` to see headers), not a second ride.

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

## Configuration and secrets

- All configuration comes from environment variables. The `.env` files are
  ignored by git; only `.env.example` files with local placeholder values are
  committed.
- `backend/.env` is created from `backend/.env.example` on the first start,
  with a fresh `APP_KEY`. Docker passes the database and Reverb settings in
  from the root `.env`.
- In production, set real values through the hosting platform's secret
  manager.

Logs are JSON lines in `backend/storage/logs/api.log`. Each line carries the
request id that the API also returns in the `X-Request-Id` header.

## Stop or reset

```bash
docker compose down
```

To also delete the database and installed packages (everything starts fresh
next time):

```bash
docker compose down -v
```
