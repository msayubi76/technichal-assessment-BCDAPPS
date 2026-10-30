"use client";

import { useEffect, useState } from "react";
import useSWR from "swr";
import { AppShell } from "@/components/layout/AppShell";
import { CancelRideButton } from "@/components/ride/CancelRideButton";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorAlert } from "@/components/ui/ErrorAlert";
import { CardSkeleton } from "@/components/ui/Skeleton";
import { pollInterval, useRealtimeConnected } from "@/hooks/useRealtimeConnected";
import { useSession } from "@/hooks/useSession";
import { api } from "@/lib/api/endpoints";
import type { Ride } from "@/lib/api/types";
import { formatDistance, formatMoney } from "@/lib/format";
import { listenForRideRequests, listenToRide } from "@/lib/realtime";
import { OpenRideCard } from "./OpenRideCard";

// The button the driver presses next, for each step of the trip.
const stepLabels: Partial<Record<Ride["status"], string>> = {
  accepted: "Arrived",
  arrived: "On the way to destination",
  in_progress: "Completed",
};

export function DriverDashboard() {
  const { user, ready, logout } = useSession("driver");
  const connected = useRealtimeConnected();
  const refreshInterval = pollInterval(connected);

  const me = useSWR(ready ? "me" : null, api.me);
  const driver = me.data?.driver;
  const online = driver?.status === "available";

  const current = useSWR(ready ? "driver-current" : null, api.currentDriverRide, { refreshInterval });
  const currentRide = current.data ?? null;

  // Only look for new requests while the driver is free to take one.
  const lookingForRides = online && !currentRide;
  const available = useSWR(lookingForRides ? "available-rides" : null, api.availableRides, { refreshInterval });

  const [busyRideId, setBusyRideId] = useState<string | null>(null);
  const [actionError, setActionError] = useState<unknown>(null);
  const [lastTrip, setLastTrip] = useState<Ride | null>(null);
  const [changingStatus, setChangingStatus] = useState(false);

  const refreshAvailable = available.mutate;
  const refreshCurrent = current.mutate;
  const driverId = driver?.id;
  const currentRideId = currentRide?.id;

  // A new ride request nearby: reload the list.
  useEffect(() => {
    if (!driverId || !lookingForRides) return;
    return listenForRideRequests(driverId, () => void refreshAvailable());
  }, [driverId, lookingForRides, refreshAvailable]);

  // The rider may cancel the ride we are driving to.
  useEffect(() => {
    if (!currentRideId) return;
    return listenToRide(currentRideId, () => void refreshCurrent());
  }, [currentRideId, refreshCurrent]);

  async function toggleAvailability() {
    setChangingStatus(true);
    setActionError(null);
    try {
      await api.setDriverStatus(online ? "offline" : "available");
      await me.mutate();
    } catch (e) {
      setActionError(e);
    } finally {
      setChangingStatus(false);
    }
  }

  async function accept(ride: Ride) {
    setBusyRideId(ride.id);
    setActionError(null);
    setLastTrip(null);
    try {
      const accepted = await api.acceptRide(ride.id);
      await current.mutate(accepted, { revalidate: false });
      void me.mutate();
    } catch (e) {
      // Usually another driver was faster. Show why and refresh the list.
      setActionError(e);
      void available.mutate();
    } finally {
      setBusyRideId(null);
    }
  }

  /** Moves the current trip to its next step: arrived, on the way, completed. */
  async function nextStep(ride: Ride) {
    setBusyRideId(ride.id);
    setActionError(null);
    try {
      if (ride.status === "accepted") {
        await current.mutate(await api.arriveAtPickup(ride.id), { revalidate: false });
      } else if (ride.status === "arrived") {
        await current.mutate(await api.startTrip(ride.id), { revalidate: false });
      } else {
        setLastTrip(await api.completeRide(ride.id));
        await current.mutate(null, { revalidate: false });
        void me.mutate();
      }
    } catch (e) {
      // Usually the rider cancelled meanwhile. Show why and reload the trip.
      setActionError(e);
      void current.mutate();
    } finally {
      setBusyRideId(null);
    }
  }

  function afterCancel() {
    void current.mutate(null, { revalidate: false });
    void me.mutate();
  }

  function retryLoad() {
    void me.mutate();
    void current.mutate();
  }

  let content;
  if (!ready || me.isLoading || current.isLoading) {
    content = <CardSkeleton lines={4} />;
  } else if (!driver || current.data === undefined) {
    content = <ErrorAlert title="Could not load your dashboard" error={me.error ?? current.error} onRetry={retryLoad} />;
  } else {
    content = (
      <div className="space-y-6">
        <Card className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-xl font-semibold">{currentRide ? "On a trip" : online ? "You are online" : "You are offline"}</h1>
            <p className="text-sm text-slate-500">
              {driver.vehicle_color} {driver.vehicle_model} · {driver.plate_number} ·{" "}
              <span className="capitalize">{driver.category}</span>
            </p>
          </div>
          {!currentRide && (
            <Button variant={online ? "secondary" : "primary"} loading={changingStatus} onClick={toggleAvailability}>
              {online ? "Go offline" : "Go online"}
            </Button>
          )}
        </Card>

        {actionError !== null && <ErrorAlert title="That did not work" error={actionError} />}

        {lastTrip && (
          <div role="status" className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">
            Trip completed. Collect {formatMoney(lastTrip.fare.total, lastTrip.fare.currency)} from the rider. You earned{" "}
            {formatMoney(lastTrip.fare.driver_earning ?? 0, lastTrip.fare.currency)}.
          </div>
        )}

        {currentRide ? (
          <Card className="space-y-4">
            <h2 className="font-semibold">Current trip</h2>
            <div className="text-sm">
              <p className="text-slate-500">Pick up {currentRide.rider?.name ?? "the rider"} at</p>
              <p className="font-medium">{currentRide.pickup.name}</p>
              <p className="pt-2 text-slate-500">Drop off at</p>
              <p className="font-medium">{currentRide.dropoff.name}</p>
              <p className="pt-2 text-slate-500">
                {formatDistance(currentRide.distance_meters)} · fare {formatMoney(currentRide.fare.total, currentRide.fare.currency)}
              </p>
            </div>
            {currentRide.status === "arrived" && (
              <p role="status" className="rounded-xl bg-slate-50 p-3 text-sm text-slate-700">
                {currentRide.rider_coming_at ? "The rider is coming." : "Waiting for the rider at the pickup."}
              </p>
            )}
            <Button className="w-full" loading={busyRideId === currentRide.id} onClick={() => nextStep(currentRide)}>
              {stepLabels[currentRide.status]}
            </Button>
            {currentRide.can_cancel && (
              <CancelRideButton ride={currentRide} onCancelled={afterCancel} onStale={() => void current.mutate()} />
            )}
          </Card>
        ) : online ? (
          <section className="space-y-3">
            <h2 className="font-semibold">Ride requests near you</h2>
            {available.isLoading ? (
              <CardSkeleton lines={2} />
            ) : available.error ? (
              <ErrorAlert title="Could not load ride requests" error={available.error} onRetry={() => void available.mutate()} />
            ) : available.data?.length ? (
              <ul className="space-y-3">
                {available.data.map((ride) => (
                  <OpenRideCard
                    key={ride.id}
                    ride={ride}
                    accepting={busyRideId === ride.id}
                    disabled={busyRideId !== null}
                    onAccept={() => accept(ride)}
                  />
                ))}
              </ul>
            ) : (
              <EmptyState title="No ride requests right now">New requests near you will appear here automatically.</EmptyState>
            )}
          </section>
        ) : (
          <EmptyState title="You are offline">Go online to start receiving ride requests.</EmptyState>
        )}
      </div>
    );
  }

  return (
    <AppShell user={user} onLogout={logout}>
      {content}
    </AppShell>
  );
}
