"use client";

import { useState } from "react";
import useSWR from "swr";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { ErrorAlert } from "@/components/ui/ErrorAlert";
import { CardSkeleton, Skeleton } from "@/components/ui/Skeleton";
import { ApiError } from "@/lib/api/client";
import { api } from "@/lib/api/endpoints";
import type { Location, Place, Ride, RideCategory } from "@/lib/api/types";
import { formatDistance, formatDuration } from "@/lib/format";
import { CategoryOptions } from "./CategoryOptions";
import { MapPlaceholder } from "./MapPlaceholder";
import { PlaceSelect } from "./PlaceSelect";

interface BookingFormProps {
  onBooked: (ride: Ride) => void;
  /** Called when the server says the rider already has a ride in progress. */
  onActiveRideFound: () => void;
}

function toLocation(place: Place): Location {
  return { name: place.name, lat: place.lat, lng: place.lng };
}

export function BookingForm({ onBooked, onActiveRideFound }: BookingFormProps) {
  const cities = useSWR("cities", api.cities, { revalidateOnFocus: false });
  const city = cities.data?.[0];
  const places = city?.places ?? [];

  const [pickupId, setPickupId] = useState<number | null>(null);
  const [dropoffId, setDropoffId] = useState<number | null>(null);
  const [category, setCategory] = useState<RideCategory>("economy");
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<unknown>(null);

  const pickup = places.find((p) => p.id === pickupId);
  const dropoff = places.find((p) => p.id === dropoffId);
  const samePlace = pickup !== undefined && pickup.id === dropoff?.id;

  const estimates = useSWR(
    pickup && dropoff && !samePlace ? ["estimate", pickup.id, dropoff.id] : null,
    () => api.estimate(toLocation(pickup!), toLocation(dropoff!)),
    { revalidateOnFocus: false },
  );

  const selectedEstimate = estimates.data?.find((e) => e.category === category);
  const canRequest = !!selectedEstimate?.drivers_available && !submitting;

  async function handleRequest() {
    if (!pickup || !dropoff) return;

    setSubmitting(true);
    setSubmitError(null);
    try {
      const ride = await api.requestRide(category, toLocation(pickup), toLocation(dropoff));
      onBooked(ride);
    } catch (error) {
      if (error instanceof ApiError && error.code === "active_ride_exists") {
        onActiveRideFound();
        return;
      }
      setSubmitError(error);
    } finally {
      setSubmitting(false);
    }
  }

  if (cities.error) {
    return <ErrorAlert title="Could not load places" error={cities.error} onRetry={() => void cities.mutate()} />;
  }

  if (cities.isLoading || !city) {
    return (
      <div className="grid gap-6 md:grid-cols-2">
        <CardSkeleton lines={4} />
        <Skeleton className="aspect-[4/3] w-full rounded-2xl" />
      </div>
    );
  }

  return (
    <div className="grid gap-6 md:grid-cols-2">
      <Card className="space-y-5">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">Where to?</h1>
          <p className="text-sm text-slate-500">Rides across {city.name}</p>
        </div>

        <PlaceSelect id="pickup" label="Pickup" places={places} value={pickupId} onChange={setPickupId} />
        <PlaceSelect
          id="dropoff"
          label="Destination"
          places={places}
          value={dropoffId}
          onChange={setDropoffId}
          error={samePlace ? "Choose a destination different from the pickup." : undefined}
        />

        {estimates.isLoading && (
          <div role="status" aria-label="Loading fares" className="space-y-2">
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-16 w-full" />
          </div>
        )}

        {estimates.error && (
          <ErrorAlert title="Could not get a price" error={estimates.error} onRetry={() => void estimates.mutate()} />
        )}

        {estimates.data && (
          <>
            <p className="text-sm text-slate-500">
              {formatDistance(estimates.data[0].distance_meters)} · about {formatDuration(estimates.data[0].duration_seconds)}
            </p>
            <CategoryOptions estimates={estimates.data} selected={category} onSelect={setCategory} />
          </>
        )}

        {submitError !== null && <ErrorAlert title="Could not request your ride" error={submitError} />}

        <Button className="w-full" disabled={!canRequest} loading={submitting} onClick={handleRequest}>
          {submitting ? "Booking…" : "Book a ride"}
        </Button>
      </Card>

      <MapPlaceholder pickup={pickup} dropoff={dropoff} />
    </div>
  );
}
