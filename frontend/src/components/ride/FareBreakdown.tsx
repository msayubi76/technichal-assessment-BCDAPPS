import type { Fare } from "@/lib/api/types";
import { formatMoney } from "@/lib/format";

export function FareBreakdown({ fare }: { fare: Fare }) {
  const rows = [
    ["Base fare", fare.base],
    ["Distance", fare.distance],
    ["Time", fare.time],
  ] as const;

  return (
    <dl className="space-y-1 text-sm">
      {rows.map(([label, amount]) => (
        <div key={label} className="flex justify-between text-slate-600">
          <dt>{label}</dt>
          <dd>{formatMoney(amount, fare.currency)}</dd>
        </div>
      ))}
      <div className="flex justify-between border-t border-slate-200 pt-2 font-semibold text-slate-900">
        <dt>Total</dt>
        <dd>{formatMoney(fare.total, fare.currency)}</dd>
      </div>
      <p className="text-xs text-slate-500">The total includes the minimum fare and is rounded to the nearest rupee.</p>
    </dl>
  );
}
