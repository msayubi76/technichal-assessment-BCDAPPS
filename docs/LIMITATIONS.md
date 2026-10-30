# Known limitations and proposed improvements

These are deliberate simplifications for the time box. Each one has a planned
fix.

## Product and matching

| Limitation | Proposed improvement |
|---|---|
| Driver locations are seeded and only move when a driver completes a trip (to the destination). There is no live GPS. | A driver app sends location every few seconds to Redis GEO; MySQL keeps only the last known position. |
| Distance is straight line × 1.3, and time uses an average city speed. | Road distance and ETA from a routing service (OSRM or Google Distance Matrix), with traffic. |
| Nearby drivers are found with a bounding box plus `ST_Distance_Sphere` in MySQL. Fine for hundreds of drivers, not tens of thousands. | Redis `GEOSEARCH`, or H3/geohash cells per city. |
| Accept checks the driver's city, category and availability, but not how far away they are now. A driver who saw the request could accept after moving away. | Re-check distance on accept, or only let drivers accept rides that were offered to them. |
| Drivers choose from a list. There are no timed offers, no re-offer when nobody accepts, and no expiry for old requests. | Offer waves with timeouts (queued jobs), automatic expiry with a rider notification, and fairness between drivers. |
| Service area is a rectangle. Places are a fixed list; there is no address search. | Polygon service areas, and a geocoding or places API. |
| Surge pricing is stored (`surge_multiplier`) but always 1.0. | Demand and supply per zone, with the multiplier shown to the rider before booking. |
| No payments, receipts, tips, ratings after a trip, or cancellation fees. | A payment provider, fees in the state machine, and rating after completion. |
| No waiting-time limit at the pickup: a driver at `arrived` waits until someone cancels. | A no-show timer that lets the driver cancel with a fee after a few minutes. |
| No real map, only a schematic placeholder. | Google Maps or Mapbox; only `MapPlaceholder` needs replacing. |
| Accounts are seeded; there is no sign-up, password reset or phone verification. | Sign-up with OTP verification, and driver onboarding with document checks. |

## Reliability and operations

| Limitation | Proposed improvement |
|---|---|
| WebSocket notifications are sent after commit, best effort. A failure is logged, and clients still catch up through polling. | Transactional outbox plus a queue worker for guaranteed delivery. |
| Idempotency keys older than 24 hours are only deleted when the same key is used again. | A scheduled job to delete expired keys. |
| PHP's built-in web server (16 workers) runs the API in Docker. It's fine locally; it serves one connection per worker. | php-fpm + nginx, or Laravel Octane. |
| The frontend container runs the Next.js dev server. | `next build` + `next start`, or deploy to Vercel. |
| Logs are local JSON files; there are no metrics, tracing or alerting. | Central logs, Sentry, metrics (latency, 5xx, match rate, time to accept) and alerts. |
| The sample CI workflow has not been run on GitHub. | Run it on the real repository and add image builds and deploy steps. |
| The end-to-end test runs against the dev stack and shared demo data. It cleans up after itself, but isn't isolated. | A dedicated e2e database that's reset before each run. |

## Security

| Limitation | Proposed improvement |
|---|---|
| The API token is stored in `localStorage`, so any script on the page could read it. | httpOnly, Secure, SameSite cookie through a backend-for-frontend, plus a strict Content Security Policy. |
| Login timing is equalised with a decoy hash, but timing over a real network isn't perfectly constant. | Rate limiting is already in place. Add account lockout alerts and CAPTCHA after repeated failures. |
| Demo passwords are simple (`password`) and shown on the sign-in page. | Demo hints only in local builds. Real accounts follow a password policy. |
| No audit of personal data retention (phone numbers, trip locations). | Retention rules, and masking of rider and driver phone numbers. |
