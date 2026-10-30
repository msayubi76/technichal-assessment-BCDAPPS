"use client";

import { useEffect } from "react";
import useSWR from "swr";
import { api } from "@/lib/api/endpoints";
import type { Ride } from "@/lib/api/types";
import { listenToRide } from "@/lib/realtime";
import { pollInterval, useRealtimeConnected } from "./useRealtimeConnected";

export function isActive(ride: Ride | null | undefined): boolean {
  return ride?.status !== undefined && ride.status !== "completed" && ride.status !== "cancelled";
}

/**
 * One ride, kept up to date: reloaded when a live update arrives, and polled
 * as a fallback while the ride is still in progress.
 */
export function useRide(rideId: string, initialRide?: Ride) {
  const connected = useRealtimeConnected();

  const swr = useSWR(["ride", rideId], () => api.ride(rideId), {
    // A ride we already have (for example the one just booked) shows at once.
    fallbackData: initialRide,
    refreshInterval: (latest) => (isActive(latest) ? pollInterval(connected) : 0),
  });

  const { mutate } = swr;
  const active = isActive(swr.data);

  useEffect(() => {
    if (!active) return;
    return listenToRide(rideId, () => void mutate());
  }, [rideId, active, mutate]);

  return swr;
}
