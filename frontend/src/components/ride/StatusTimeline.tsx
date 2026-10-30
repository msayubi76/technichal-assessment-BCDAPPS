import type { Ride } from "@/lib/api/types";

const steps = [
  { key: "requested", label: "Requested" },
  { key: "accepted", label: "Driver on the way" },
  { key: "completed", label: "Arrived" },
] as const;

export function StatusTimeline({ ride }: { ride: Ride }) {
  if (ride.status === "cancelled") return null;

  const current = steps.findIndex((step) => step.key === ride.status);

  return (
    <ol className="flex items-center gap-2" aria-label="Ride progress">
      {steps.map((step, index) => {
        const done = index <= current;
        return (
          <li key={step.key} className="flex flex-1 flex-col gap-1" aria-current={index === current ? "step" : undefined}>
            <span className={`h-1.5 rounded-full ${done ? "bg-emerald-600" : "bg-slate-200"}`} />
            <span className={`text-xs ${done ? "font-medium text-slate-800" : "text-slate-400"}`}>{step.label}</span>
          </li>
        );
      })}
    </ol>
  );
}
