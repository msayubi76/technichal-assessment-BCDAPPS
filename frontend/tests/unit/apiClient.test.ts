import { afterEach, describe, expect, it, vi } from "vitest";
import { apiRequest, ApiError } from "@/lib/api/client";
import { getToken, saveSession } from "@/lib/auth/session";
import type { User } from "@/lib/api/types";
import { pollInterval } from "@/hooks/useRealtimeConnected";

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });
}

const rider: User = { id: 1, name: "Ali", email: "rider@example.com", phone: null, role: "rider" };

describe("apiRequest", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
  });

  it("retries a network failure with the same idempotency key", async () => {
    vi.useFakeTimers();
    const fetchMock = vi
      .fn()
      .mockRejectedValueOnce(new TypeError("Failed to fetch"))
      .mockResolvedValueOnce(jsonResponse(201, { data: { id: "ride-1" } }));
    vi.stubGlobal("fetch", fetchMock);

    const result = apiRequest<{ id: string }>("/rides", { method: "POST", body: {}, idempotencyKey: "key-12345678", retries: 2 });
    await vi.runAllTimersAsync();

    await expect(result).resolves.toEqual({ id: "ride-1" });
    expect(fetchMock).toHaveBeenCalledTimes(2);
    const keys = fetchMock.mock.calls.map(([, init]) => (init as RequestInit).headers as Record<string, string>);
    expect(keys.map((h) => h["Idempotency-Key"])).toEqual(["key-12345678", "key-12345678"]);
  });

  it("does not retry when the server gave a clear answer", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse(409, { error: { code: "invalid_transition", message: "This ride was cancelled.", request_id: "req-1" } }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const error = await apiRequest("/rides/1/accept", { method: "POST", retries: 2 }).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 409, code: "invalid_transition", requestId: "req-1" });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("forgets the session when the token is rejected", async () => {
    saveSession("old-token", rider);
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(401, { error: { code: "unauthenticated", message: "Please sign in." } })));

    await expect(apiRequest("/rides/current")).rejects.toMatchObject({ status: 401 });
    expect(getToken()).toBeNull();
  });
});

describe("pollInterval", () => {
  it("polls quickly when live updates are down and slowly when they are up", () => {
    expect(pollInterval(false)).toBe(4_000);
    expect(pollInterval(true)).toBe(30_000);
  });
});
