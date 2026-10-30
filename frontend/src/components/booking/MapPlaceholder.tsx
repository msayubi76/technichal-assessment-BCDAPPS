import type { Location } from "@/lib/api/types";

interface MapPlaceholderProps {
  pickup?: Location;
  dropoff?: Location;
  driver?: { lat: number; lng: number } | null;
}

/**
 * A simple stand-in for a real map: it places the pickup, destination and
 * driver relative to each other, so the rider gets a sense of direction.
 * A real map (Google Maps or Mapbox) would replace this component only.
 */
export function MapPlaceholder({ pickup, dropoff, driver }: MapPlaceholderProps) {
  const points = [pickup, dropoff, driver ?? undefined].filter((p): p is { lat: number; lng: number } => !!p);
  const project = makeProjection(points);

  const a = pickup ? project(pickup) : null;
  const b = dropoff ? project(dropoff) : null;
  const d = driver ? project(driver) : null;

  return (
    <div className="relative aspect-[4/3] w-full overflow-hidden rounded-2xl bg-emerald-50 ring-1 ring-slate-200">
      <svg viewBox="0 0 100 75" className="absolute inset-0 size-full" role="img" aria-label="Map preview">
        <defs>
          <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="#d1fae5" strokeWidth="0.5" />
          </pattern>
        </defs>
        <rect width="100" height="75" fill="url(#grid)" />
        {a && b && <line x1={a.x} y1={a.y} x2={b.x} y2={b.y} stroke="#059669" strokeWidth="1" strokeDasharray="2 1.5" />}
        {d && a && <line x1={d.x} y1={d.y} x2={a.x} y2={a.y} stroke="#94a3b8" strokeWidth="0.6" strokeDasharray="1 1" />}
        {a && <circle cx={a.x} cy={a.y} r="2.4" fill="#059669" stroke="white" strokeWidth="0.8" />}
        {b && <rect x={b.x - 2.2} y={b.y - 2.2} width="4.4" height="4.4" fill="#0f172a" stroke="white" strokeWidth="0.8" />}
        {d && <circle cx={d.x} cy={d.y} r="2" fill="#f59e0b" stroke="white" strokeWidth="0.8" />}
      </svg>

      {!pickup && !dropoff && (
        <p className="absolute inset-0 grid place-items-center text-sm text-emerald-800/70">Choose a pickup and destination</p>
      )}

      <div className="absolute bottom-2 left-2 flex gap-3 rounded-lg bg-white/90 px-2 py-1 text-xs text-slate-600">
        <span className="flex items-center gap-1"><span className="size-2 rounded-full bg-emerald-600" />Pickup</span>
        <span className="flex items-center gap-1"><span className="size-2 bg-slate-900" />Destination</span>
        {driver && <span className="flex items-center gap-1"><span className="size-2 rounded-full bg-amber-500" />Driver</span>}
      </div>
    </div>
  );
}

// Fits the given points into the drawing with some padding around them.
function makeProjection(points: { lat: number; lng: number }[]) {
  if (points.length === 0) return () => ({ x: 50, y: 37 });

  const lats = points.map((p) => p.lat);
  const lngs = points.map((p) => p.lng);
  const minLat = Math.min(...lats);
  const maxLat = Math.max(...lats);
  const minLng = Math.min(...lngs);
  const maxLng = Math.max(...lngs);
  const span = Math.max(maxLat - minLat, maxLng - minLng, 0.01);

  return (p: { lat: number; lng: number }) => ({
    x: 15 + ((p.lng - minLng) / span) * 70,
    y: 62 - ((p.lat - minLat) / span) * 50,
  });
}
