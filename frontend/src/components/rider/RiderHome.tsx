"use client";

import { useState } from "react";
import useSWR from "swr";
import { AppShell } from "@/components/layout/AppShell";
import { BookingForm } from "@/components/booking/BookingForm";
import { RideTracker } from "@/components/ride/RideTracker";
import { ErrorAlert } from "@/components/ui/ErrorAlert";
import { CardSkeleton } from "@/components/ui/Skeleton";
import { useSession } from "@/hooks/useSession";
import { api } from "@/lib/api/endpoints";
import type { Ride } from "@/lib/api/types";

/**
 * The rider's screen. If a ride is already in progress (for example after a
 * page refresh) we show it; otherwise we show the booking form.
 */
export function RiderHome() {
  const { user, ready, logout } = useSession("rider");

  // Loaded once. After that the ride tracker keeps the ride itself fresh, so
  // the screen doesn't jump back to the form when the ride finishes.
  const current = useSWR(ready ? "current-ride" : null, api.currentRide, {
    revalidateOnFocus: false,
    revalidateIfStale: false,
  });
  const [booked, setBooked] = useState<Ride | null>(null);

  const ride = booked ?? current.data ?? null;

  function bookAnother() {
    setBooked(null);
    void current.mutate(null);
  }

  return (
    <AppShell user={user} onLogout={logout}>
      {!ready || current.isLoading ? (
        <CardSkeleton lines={5} />
      ) : current.error && !booked ? (
        <ErrorAlert title="Could not load your rides" error={current.error} onRetry={() => void current.mutate()} />
      ) : ride ? (
        <RideTracker key={ride.id} rideId={ride.id} initialRide={ride} onBookAnother={bookAnother} />
      ) : (
        <BookingForm onBooked={setBooked} onActiveRideFound={() => void current.mutate()} />
      )}
    </AppShell>
  );
}
