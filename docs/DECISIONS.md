# Design decisions

The main choices, why I made them, and what they cost.

| Decision | Why | Trade-off |
|---|---|---|
| **Modular monolith** (one Laravel API, layered Controller → Service → Model) | Simple to run, test and change. Business rules sit in services, not controllers. | Must be split deliberately later. The service folders (`Rides`, `Dispatch`, `Auth`) mark the seams. |
| **Pessimistic row locks** (`SELECT … FOR UPDATE`) for every ride step (accept, arrive, start, cancel, complete) | Simple to reason about. The loser waits a few milliseconds, then gets a clear `409`. Lock order is fixed (ride, then driver) to avoid deadlocks. | Holds a row lock for the length of a short transaction. Fine at this scale; a hot ride only sees a handful of drivers. |
| **Unique indexes on generated columns** (`active_rider_id`, `active_driver_id`) | MySQL has no partial unique index. A stored generated column that's `NULL` for finished rides gives the same guarantee: one active ride per rider and per driver, enforced by the database. | Slightly unusual, so it's commented in the migration. |
| **Idempotency keys stored in MySQL** | Survives restarts and works with several API instances, with no Redis needed yet. The claim row is unique per user and key, so even two identical requests arriving together run once. | One extra write per request. Old keys are deleted when reused after 24 hours; a scheduled cleanup would be added in production. |
| **The server calculates every price** | The client sends only places and a category. Fares can't be tampered with, and the fare is stored on the ride. | Estimate and booking each calculate the fare, so a price change in between is possible (the booked price is what's shown). |
| **Money as integer paisa** | No floating-point rounding errors. The total is rounded up to a whole rupee after the minimum fare. | Formatting happens in the UI (`formatMoney`). |
| **Straight-line distance × 1.3** for road distance; fixed average speed per city | No paid routing API, and still a realistic estimate. | Not real traffic or road routes. See LIMITATIONS. |
| **Drivers pick from a list of nearby requests**, and the nearest 5 get a live notification | Shows availability, eligibility and race safety without a scheduler. | Production would use timed offers (see ARCHITECTURE §8). |
| **WebSocket messages only say "ride X changed"** and the client refetches | The API stays the single source of truth. A missed message can't leave wrong data. Channels are private and authorised per ride or driver. | One extra GET per change; negligible. |
| **Notifications sent after commit, best effort** | A broadcast failure must never undo a ride or return an error to the user. Failures are logged (`broadcast.failed`) and polling covers the gap. | Not guaranteed delivery. An outbox plus a queue would make it reliable. |
| **ULIDs for ride ids** | Can't be guessed or enumerated, and they sort by time. Rides that aren't yours return 404, not 403, so ids can't be probed. | Longer than integers. |
| **Sanctum bearer tokens, stored in `localStorage`** | Simple for an SPA on a different port; no CSRF setup. Tokens expire after 7 days and are revoked on sign-out. | Readable by any script on the page if there's an XSS bug. Production: httpOnly cookie via a backend-for-frontend. |
| **Login takes the same time whether or not the email exists** | Unknown emails are checked against a fixed decoy bcrypt hash, so response time doesn't reveal which emails have accounts. Combined with rate limiting. | The decoy hash must use the same bcrypt cost as real hashes (12). |
| **Multi-city in the schema from day one** (`cities`, `fare_rules` per city and category, `city_id` on drivers and rides) | Adding a city is data, not code. | A little more setup for a single city. |
| **Ride events table** | A full history of who changed what and when. Useful for support, disputes and debugging. | Extra insert per transition. |
| **"I'm coming" is a message, not a status** | A rider who walks straight to the car shouldn't block the driver from starting the trip. It's stored as `rider_coming_at` and recorded in the ride's history. | The driver can't tell from the status alone whether the rider responded; the screen shows it instead. |
| **No cancelling after the trip starts** | Once the rider is in the car, a cancel would mean an unpaid trip. Either side can cancel up to then, including the driver at the pickup when the rider doesn't show up. | Real problems mid-trip (breakdown, safety) would need a support flow, not a cancel button. |
