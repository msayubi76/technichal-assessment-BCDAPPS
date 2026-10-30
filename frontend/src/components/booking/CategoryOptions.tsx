import type { Estimate, RideCategory } from "@/lib/api/types";
import { formatDuration, formatMoney } from "@/lib/format";

const descriptions: Record<RideCategory, string> = {
  economy: "Affordable everyday rides",
  comfort: "Newer cars with extra space",
};

interface CategoryOptionsProps {
  estimates: Estimate[];
  selected: RideCategory;
  onSelect: (category: RideCategory) => void;
}

export function CategoryOptions({ estimates, selected, onSelect }: CategoryOptionsProps) {
  return (
    <fieldset>
      <legend className="mb-2 text-sm font-medium text-slate-700">Choose a ride</legend>
      <div className="space-y-2">
        {estimates.map((estimate) => {
          const isSelected = estimate.category === selected;
          const unavailable = !estimate.drivers_available;

          return (
            <label
              key={estimate.category}
              className={`flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3 transition-colors has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60 ${
                isSelected && !unavailable ? "border-emerald-600 bg-emerald-50" : "border-slate-200 bg-white hover:border-slate-300"
              }`}
            >
              <input
                type="radio"
                name="category"
                value={estimate.category}
                checked={isSelected}
                disabled={unavailable}
                onChange={() => onSelect(estimate.category)}
                className="sr-only"
              />
              <div>
                <p className="font-semibold text-slate-900">{estimate.label}</p>
                <p className="text-xs text-slate-500">
                  {unavailable
                    ? "No drivers nearby right now"
                    : `${descriptions[estimate.category]} · pickup in ${formatDuration(estimate.pickup_eta_seconds ?? 0)}`}
                </p>
              </div>
              <p className="text-right font-semibold text-slate-900">{formatMoney(estimate.total_fare, estimate.currency)}</p>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
