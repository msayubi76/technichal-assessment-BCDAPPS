"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { ErrorAlert } from "@/components/ui/ErrorAlert";
import { api } from "@/lib/api/endpoints";
import type { Ride } from "@/lib/api/types";

interface CancelRideButtonProps {
  ride: Ride;
  onCancelled: (ride: Ride) => void;
  /** Called when the cancel failed because the ride changed meanwhile. */
  onStale: () => void;
}

/** Asks once before cancelling, so a stray tap doesn't cancel the ride. */
export function CancelRideButton({ ride, onCancelled, onStale }: CancelRideButtonProps) {
  const [confirming, setConfirming] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const [error, setError] = useState<unknown>(null);

  async function cancel() {
    setCancelling(true);
    setError(null);
    try {
      onCancelled(await api.cancelRide(ride.id));
    } catch (e) {
      setError(e);
      setConfirming(false);
      onStale();
    } finally {
      setCancelling(false);
    }
  }

  return (
    <div className="space-y-3">
      {error !== null && <ErrorAlert title="Could not cancel the ride" error={error} />}

      {confirming ? (
        <div className="rounded-xl bg-rose-50 p-3">
          <p className="mb-3 text-sm font-medium text-rose-900">Cancel this ride?</p>
          <div className="flex gap-2">
            <Button variant="danger" className="flex-1" loading={cancelling} onClick={cancel}>
              Yes, cancel
            </Button>
            <Button variant="secondary" className="flex-1" disabled={cancelling} onClick={() => setConfirming(false)}>
              Keep ride
            </Button>
          </div>
        </div>
      ) : (
        <Button variant="danger" className="w-full" onClick={() => setConfirming(true)}>
          Cancel ride
        </Button>
      )}
    </div>
  );
}
