import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { API_URL } from "@/lib/api/client";
import { getToken } from "@/lib/auth/session";

// Live updates over WebSockets (Laravel Reverb).
//
// Messages only say "this ride changed"; screens then reload the ride from
// the API. So if a message is missed or the socket drops, nothing shows wrong
// data: the screens also poll the API, faster while the socket is down.

let echo: Echo<"reverb"> | null = null;
let connected = false;
const connectionListeners = new Set<() => void>();

function getEcho(): Echo<"reverb"> | null {
  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY;
  const token = getToken();

  if (typeof window === "undefined" || !key || !token) return null;

  if (!echo) {
    const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080);

    echo = new Echo({
      broadcaster: "reverb",
      key,
      wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? "localhost",
      wsPort: port,
      wssPort: port,
      forceTLS: process.env.NEXT_PUBLIC_REVERB_SCHEME === "https",
      enabledTransports: ["ws", "wss"],
      authEndpoint: `${API_URL}/api/broadcasting/auth`,
      auth: { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" } },
      Pusher,
    });

    echo.connector.pusher.connection.bind("state_change", (states: { current: string }) => {
      setConnected(states.current === "connected");
    });
  }

  return echo;
}

function setConnected(value: boolean): void {
  if (connected === value) return;
  connected = value;
  connectionListeners.forEach((listener) => listener());
}

/** Calls onChange whenever this ride's status changes. Returns an unsubscribe function. */
export function listenToRide(rideId: string, onChange: () => void): () => void {
  const client = getEcho();
  if (!client) return () => {};

  const channel = `rides.${rideId}`;
  client.private(channel).listen(".ride.status_changed", onChange);

  return () => client.leave(channel);
}

/** Calls onRequest when a new ride near this driver is waiting. Returns an unsubscribe function. */
export function listenForRideRequests(driverId: number, onRequest: () => void): () => void {
  const client = getEcho();
  if (!client) return () => {};

  const channel = `drivers.${driverId}`;
  client.private(channel).listen(".ride.requested", onRequest);

  return () => client.leave(channel);
}

export function isRealtimeConnected(): boolean {
  return connected;
}

export function subscribeToConnection(listener: () => void): () => void {
  connectionListeners.add(listener);
  return () => connectionListeners.delete(listener);
}

export function disconnectRealtime(): void {
  echo?.disconnect();
  echo = null;
  setConnected(false);
}
