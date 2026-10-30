import type { Place } from "@/lib/api/types";

interface PlaceSelectProps {
  id: string;
  label: string;
  places: Place[];
  value: number | null;
  onChange: (placeId: number | null) => void;
  error?: string;
  disabled?: boolean;
}

export function PlaceSelect({ id, label, places, value, onChange, error, disabled }: PlaceSelectProps) {
  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-slate-700">
        {label}
      </label>
      <select
        id={id}
        value={value ?? ""}
        disabled={disabled}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        onChange={(event) => onChange(event.target.value ? Number(event.target.value) : null)}
        className={`w-full rounded-xl border bg-white px-3 py-3 text-sm text-slate-900 focus:outline-2 focus:outline-emerald-600 disabled:bg-slate-50 ${
          error ? "border-rose-400" : "border-slate-300"
        }`}
      >
        <option value="">Select a place</option>
        {places.map((place) => (
          <option key={place.id} value={place.id}>
            {place.name}
            {place.area ? ` · ${place.area}` : ""}
          </option>
        ))}
      </select>
      {error && (
        <p id={`${id}-error`} className="mt-1 text-sm text-rose-600">
          {error}
        </p>
      )}
    </div>
  );
}
