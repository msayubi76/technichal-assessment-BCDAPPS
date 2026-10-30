import type { Driver } from "@/lib/api/types";

export function DriverCard({ driver }: { driver: Driver }) {
  return (
    <div className="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
      <span className="grid size-11 place-items-center rounded-full bg-slate-800 text-sm font-semibold text-white">
        {driver.name
          .split(" ")
          .map((part) => part[0])
          .join("")
          .slice(0, 2)}
      </span>
      <div className="flex-1">
        <p className="font-semibold text-slate-900">{driver.name}</p>
        <p className="text-sm text-slate-600">
          {driver.vehicle_color} {driver.vehicle_model} · ★ {driver.rating.toFixed(1)}
        </p>
      </div>
      <span className="rounded-lg bg-white px-2 py-1 font-mono text-sm font-semibold text-slate-900 ring-1 ring-slate-300">
        {driver.plate_number}
      </span>
    </div>
  );
}
