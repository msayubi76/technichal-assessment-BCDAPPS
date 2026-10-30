# AI tools used

## Tool

**Claude Code** (Anthropic), running the Claude Opus model, in the Claude
desktop app. I used it as a pair programmer for the whole project.

## How I used it

- **I made the decisions:** the stack (Laravel in Docker, MySQL, Sanctum,
  Next.js + TypeScript, Tailwind), WebSockets with a polling fallback,
  multi-city support in the schema, the four-phase plan, and the backend
  conventions. Those conventions (FormRequest → Controller → Service → Model
  → Resource, consistent JSON responses) come from my own earlier Laravel
  project.
- **The AI wrote most of the code, tests and documentation** to that plan,
  phase by phase. I reviewed each phase before moving on.
- **I questioned code I couldn't defend and changed it.** Example: the
  login's protection against revealing which emails exist. The first version
  hashed a dummy password on every unknown-email login, so it ran bcrypt twice
  and was actually slower than a real login. After I challenged it, we
  measured the options and I chose a precomputed decoy hash, which makes both
  paths do exactly one bcrypt check. I asked for comments in plain English
  that explain *why*, so anyone can follow the code.

## How the output was verified

AI output was treated as untrusted until it passed checks:

- **Automated tests:** 101 backend tests (unit, feature, and concurrency tests
  that fork real processes against MySQL), 15 frontend unit tests, and one
  Playwright end-to-end test of the full rider and driver journey (accept,
  arrived, "I'm coming", on the way, completed).
- **Checking that tests can fail:** I removed the row lock from ride
  acceptance and confirmed the concurrency test failed, then put it back.
- **Manual end-to-end checks** against the running Docker stack with curl:
  - estimate → request → idempotent replay (same ride id back);
  - a driver accepts, and a second driver gets `409`;
  - complete, then cancelling the completed ride is refused;
  - private WebSocket channels allow the owner (`200`) and refuse others (`403`).
- **Static checks:** TypeScript typecheck, ESLint and a production build with
  zero errors or warnings.
- **Safety checks during development:**
  - Tests are forced onto a separate `_test` database, with a guard that
    refuses to run otherwise. This guard caught a real config mistake early.
  - Generated files containing instructions for AI agents were removed, not
    followed.
  - The repository was scanned for secrets before submission.
- **Problems the checks found and fixed:**
  - a dev server that dropped environment variables;
  - a health check that passed on an error page;
  - a missing request id on 404 responses;
  - a sign-in button that could be clicked before the page was ready;
  - very slow API responses (PHP OPcache was off in the container).
