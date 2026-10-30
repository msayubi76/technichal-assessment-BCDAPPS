import { ApiError } from "@/lib/api/client";
import { Button } from "./Button";

interface ErrorAlertProps {
  error: unknown;
  title?: string;
  onRetry?: () => void;
}

/**
 * Shows what went wrong in plain words, with a retry button when it makes
 * sense. The request id helps support find the matching server log line.
 */
export function ErrorAlert({ error, title = "Something went wrong", onRetry }: ErrorAlertProps) {
  const apiError = error instanceof ApiError ? error : null;
  const message = apiError?.message ?? "Please try again.";

  return (
    <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
      <p className="font-semibold">{title}</p>
      <p className="mt-1">{message}</p>
      {apiError?.requestId && <p className="mt-2 text-xs text-rose-700/80">Reference: {apiError.requestId}</p>}
      {onRetry && (
        <Button variant="secondary" className="mt-3 py-2" onClick={onRetry}>
          Try again
        </Button>
      )}
    </div>
  );
}
