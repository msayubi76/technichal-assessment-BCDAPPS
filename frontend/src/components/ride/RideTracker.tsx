"use client";

import { MapPlaceholder } from "@/components/booking/MapPlaceholder";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { ErrorAlert } from "@/components/ui/ErrorAlert";
import { CardSkeleton } from "@/components/ui/Skeleton";
import { Spinner } from "@/components/ui/Spinner";
import { useRide } from "@/hooks/useRide";
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

      <MapPlaceholder pickup={ride.pickup} dropoff={ride.dropoff} driver={ride.status === "accepted" ? ride.driver?.location : null} />
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
