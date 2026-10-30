export function Skeleton({ className = "h-4 w-full" }: { className?: string }) {
  return <div aria-hidden="true" className={`animate-pulse rounded-lg bg-slate-200 ${className}`} />;
}

/** Placeholder shaped like a card with a few lines of text. */
export function CardSkeleton({ lines = 3 }: { lines?: number }) {
  return (
    <div role="status" aria-label="Loading" className="space-y-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <Skeleton className="h-5 w-1/3" />
      {Array.from({ length: lines }, (_, i) => (
        <Skeleton key={i} className={i % 2 ? "h-4 w-2/3" : "h-4 w-full"} />
      ))}
    </div>
  );
}
