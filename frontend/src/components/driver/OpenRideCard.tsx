import { Button } from "@/components/ui/Button";
import type { Ride } from "@/lib/api/types";
import { formatDistance, formatMoney } from "@/lib/format";

interface OpenRideCardProps {
  ride: Ride;
  accepting: boolean;
  disabled: boolean;
  onAccept: () => void;
}

/** A ride request waiting for a driver, as shown in the driver's list. */
export function OpenRideCard({ ride, accepting, disabled, onAccept }: OpenRideCardProps) {
  return (
    <li className="flex flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-slate-200 sm:flex-row sm:items-center">
      <div className="flex-1 text-sm">
        <p className="font-semibold text-slate-900">
          {ride.pickup.name} → {ride.dropoff.name}
        </p>
        <p className="text-slate-500">
          {ride.distance_to_pickup_meters !== undefined && `${formatDistance(ride.distance_to_pickup_meters)} to pickup · `}
          {formatDistance(ride.distance_meters)} trip · <span className="capitalize">{ride.category}</span>
        </p>
      </div>
      <div className="flex items-center gap-3">
        <span className="font-semibold text-slate-900">
          {formatMoney(ride.fare.driver_earning ?? ride.fare.total, ride.fare.currency)}
        </span>
        <Button loading={accepting} disabled={disabled} onClick={onAccept}>
          Accept
        </Button>
      </div>
    </li>
  );
}
