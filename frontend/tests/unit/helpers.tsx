import { render } from "@testing-library/react";
import type { ReactElement } from "react";
import { SWRConfig } from "swr";
import type { Ride } from "@/lib/api/types";

/** Renders with a fresh SWR cache, so tests never see each other's data. */
export function renderWithSWR(ui: ReactElement) {
  return render(
    <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0, shouldRetryOnError: false }}>{ui}</SWRConfig>,
  );
}

export function makeRide(overrides: Partial<Ride> = {}): Ride {
  return {
    id: "01JRIDE000000000000000000",
    status: "requested",
    category: "economy",
    pickup: { name: "Liberty Market", lat: 31.51, lng: 74.34 },
    dropoff: { name: "Packages Mall", lat: 31.47, lng: 74.36 },
    distance_meters: 6200,
    duration_seconds: 1000,
    pickup_eta_seconds: 240,
    fare: { currency: "PKR", base: 10000, distance: 21700, time: 8300, surge_multiplier: 1, total: 40000 },
    driver: null,
    cancelled_by: null,
    cancellation_reason: null,
    can_cancel: true,
    requested_at: "2026-10-30T10:00:00Z",
    accepted_at: null,
    completed_at: null,
    cancelled_at: null,
    ...overrides,
  };
}
