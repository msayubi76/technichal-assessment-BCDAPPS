"use client";

import { useSyncExternalStore } from "react";
import { isRealtimeConnected, subscribeToConnection } from "@/lib/realtime";

/** True while the WebSocket connection is up. */
export function useRealtimeConnected(): boolean {
  return useSyncExternalStore(subscribeToConnection, isRealtimeConnected, () => false);
}

/**
 * How often to check the API for changes. Live updates usually arrive over
 * the WebSocket, so we only poll slowly as a safety net. If the socket is
 * down we poll every few seconds instead.
 */
export function pollInterval(connected: boolean): number {
  return connected ? 30_000 : 4_000;
}
