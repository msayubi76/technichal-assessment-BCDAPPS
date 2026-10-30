"use client";

import { useState } from "react";
import { MapPlaceholder } from "@/components/booking/MapPlaceholder";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { ErrorAlert } from "@/components/ui/ErrorAlert";
import { CardSkeleton } from "@/components/ui/Skeleton";
import { Spinner } from "@/components/ui/Spinner";
import { useRide } from "@/hooks/useRide";
import { api } from "@/lib/api/endpoints";
import type { Ride } from "@/lib/api/types";
import { formatDuration, formatMoney } from "@/lib/format";
import { CancelRideButton } from "./CancelRideButton";
import { DriverCard } from "./DriverCard";
import { FareBreakdown } from "./FareBreakdown";
import { StatusTimeline } from "./StatusTimeline";

interface RideTrackerProps {
  rideId: string;
  initialRide?: Ride;
  onBookAnother: () => void;
}

export function RideTracker({ rideId, initialRide, onBookAnother }: RideTrackerProps) {
  const { data: ride, error, isLoading, mutate } = useRide(rideId, initialRide);

  if (isLoading && !ride) {
    return <CardSkeleton lines={5} />;
  }

  if (!ride) {
    return <ErrorAlert title="Could not load your ride" error={error} onRetry={() => void mutate()} />;
  }

  return (
    <div className="grid gap-6 md:grid-cols-2">
      <Card className="space-y-5">
        <Headline ride={ride} />
        <StatusTimeline ride={ride} />

        {/* Keep showing the last known ride if a refresh fails, with a quiet warning. */}
        {error && <ErrorAlert title="Live updates paused" error={error} onRetry={() => void mutate()} />}

        {ride.driver && ride.status !== "requested" && <DriverCard driver={ride.driver} />}

        {ride.status === "arrived" && <ComingButton ride={ride} onDone={(updated) => void mutate(updated, { revalidate: false })} />}

        <div className="space-y-1 text-sm">
          <p className="text-slate-500">From</p>
          <p className="font-medium text-slate-900">{ride.pickup.name}</p>
          <p className="pt-2 text-slate-500">To</p>
          <p className="font-medium text-slate-900">{ride.dropoff.name}</p>
        </div>

        <FareBreakdown fare={ride.fare} />

        {ride.can_cancel && (
          <CancelRideButton
            ride={ride}
            onCancelled={(cancelled) => void mutate(cancelled, { revalidate: false })}
            onStale={() => void mutate()}
          />
        )}

        {(ride.status === "completed" || ride.status === "cancelled") && (
          <Button className="w-full" onClick={onBookAnother}>
            Book another ride
          </Button>
        )}
      </Card>

      <MapPlaceholder
        pickup={ride.pickup}
        dropoff={ride.dropoff}
        driver={ride.status === "accepted" || ride.status === "arrived" ? ride.driver?.location : null}
      />
    </div>
  );
}

/** Lets the waiting driver know the rider is on their way out. */
function ComingButton({ ride, onDone }: { ride: Ride; onDone: (ride: Ride) => void }) {
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<unknown>(null);

  if (ride.rider_coming_at) {
    return (
      <p role="status" className="rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-900">
        Your driver knows you are coming.
      </p>
    );
  }

  async function send() {
    setSending(true);
    setError(null);
    try {
      onDone(await api.riderComing(ride.id));
    } catch (e) {
      setError(e);
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="space-y-3">
      {error !== null && <ErrorAlert title="Could not tell your driver" error={error} />}
      <Button className="w-full" loading={sending} onClick={send}>
        I&apos;m coming
      </Button>
    </div>
  );
}

function Headline({ ride }: { ride: Ride }) {
  switch (ride.status) {
    case "requested":
      return (
        <div className="flex items-start gap-3">
          <Spinner className="mt-1 size-5 text-emerald-600" />
          <div>
            <h1 className="text-xl font-semibold text-slate-900">Finding you a driver</h1>
            <p className="text-sm text-slate-500">
              We have told the nearest drivers.
              {ride.pickup_eta_seconds !== null && ` The closest is about ${formatDuration(ride.pickup_eta_seconds)} away.`}
            </p>
          </div>
        </div>
      );
    case "accepted":
      return (
        <div>
          <h1 className="text-xl font-semibold text-slate-900">Your driver is on the way</h1>
          {ride.pickup_eta_seconds !== null && (
            <p className="text-sm text-slate-500">Arriving in about {formatDuration(ride.pickup_eta_seconds)}</p>
          )}
        </div>
      );
    case "arrived":
      return (
        <div>
          <h1 className="text-xl font-semibold text-slate-900">Your driver has arrived</h1>
          <p className="text-sm text-slate-500">They are waiting for you at {ride.pickup.name}.</p>
        </div>
      );
    case "in_progress":
      return (
        <div>
          <h1 className="text-xl font-semibold text-slate-900">On the way to your destination</h1>
          <p className="text-sm text-slate-500">About {formatDuration(ride.duration_seconds)} to {ride.dropoff.name}.</p>
        </div>
      );
    case "completed":
      return (
        <div>
          <h1 className="text-xl font-semibold text-slate-900">You have arrived</h1>
          <p className="text-sm text-slate-500">Please pay {formatMoney(ride.fare.total, ride.fare.currency)} to your driver.</p>
        </div>
      );
    case "cancelled":
      return (
        <div>
          <h1 className="text-xl font-semibold text-slate-900">Ride cancelled</h1>
          <p className="text-sm text-slate-500">
            {ride.cancelled_by === "driver" ? "Your driver cancelled this ride." : "You cancelled this ride."}
          </p>
        </div>
      );
  }
}
