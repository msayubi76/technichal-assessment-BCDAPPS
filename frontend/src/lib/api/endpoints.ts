import { apiRequest, newIdempotencyKey } from "./client";
import type { City, Driver, DriverStatus, Estimate, Location, LoginResult, Ride, RideCategory, User } from "./types";

// One function per API endpoint, so screens never build URLs themselves.
// Ride actions create one idempotency key per call and reuse it for retries.

export const api = {
  login: (email: string, password: string) =>
    apiRequest<LoginResult>("/auth/login", { method: "POST", body: { email, password, device_name: "web-app" } }),

  logout: () => apiRequest<{ message: string }>("/auth/logout", { method: "POST" }),

  me: () => apiRequest<User>("/auth/me"),

  cities: () => apiRequest<City[]>("/cities"),

  estimate: (pickup: Location, dropoff: Location) =>
    apiRequest<Estimate[]>("/rides/estimate", { method: "POST", body: { pickup, dropoff } }),

  requestRide: (category: RideCategory, pickup: Location, dropoff: Location) =>
    apiRequest<Ride>("/rides", {
      method: "POST",
      body: { category, pickup, dropoff },
      idempotencyKey: newIdempotencyKey(),
      retries: 2,
    }),

  currentRide: () => apiRequest<Ride | null>("/rides/current"),

  ride: (id: string) => apiRequest<Ride>(`/rides/${id}`),

  cancelRide: (id: string, reason?: string) =>
    apiRequest<Ride>(`/rides/${id}/cancel`, {
      method: "POST",
      body: { reason },
      idempotencyKey: newIdempotencyKey(),
      retries: 2,
    }),

  // Driver
  availableRides: () => apiRequest<Ride[]>("/driver/rides/available"),

  currentDriverRide: () => apiRequest<Ride | null>("/driver/rides/current"),

  acceptRide: (id: string) =>
    apiRequest<Ride>(`/rides/${id}/accept`, { method: "POST", idempotencyKey: newIdempotencyKey(), retries: 2 }),

  completeRide: (id: string) =>
    apiRequest<Ride>(`/rides/${id}/complete`, { method: "POST", idempotencyKey: newIdempotencyKey(), retries: 2 }),

  setDriverStatus: (status: Exclude<DriverStatus, "on_trip">) =>
    apiRequest<Driver>("/driver/status", { method: "PATCH", body: { status } }),
};
