import { clearSession, getToken } from "@/lib/auth/session";

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

const TIMEOUT_MS = 10_000;

/**
 * Every failed request ends up as one of these, so screens only need to
 * handle one error type. `code` matches the backend's error codes, plus
 * "network_error" and "timeout" for problems before the server answers.
 */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly details: Record<string, string[]> = {},
    public readonly requestId?: string,
  ) {
    super(message);
    this.name = "ApiError";
  }

  /** Worth trying again: the request never got a clear answer from the server. */
  get isRetryable(): boolean {
    return this.status === 0 || this.status >= 500;
  }
}

interface RequestOptions {
  method?: "GET" | "POST" | "PATCH";
  body?: unknown;
  /**
   * Required by the API for ride actions. Create one per user action and
   * reuse it for retries, so a retry can never repeat the action.
   */
  idempotencyKey?: string;
  /** Extra attempts after a network failure or timeout. */
  retries?: number;
}

export function newIdempotencyKey(): string {
  return crypto.randomUUID();
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { retries = 0 } = options;

  for (let attempt = 0; ; attempt++) {
    try {
      return await send<T>(path, options);
    } catch (error) {
      // Only retry when we don't know if the server got the request. With an
      // idempotency key that is safe: the server ignores the repeat.
      const canRetry = error instanceof ApiError && error.status === 0 && attempt < retries;
      if (!canRetry) throw error;

      await wait(500 * (attempt + 1));
    }
  }
}

async function send<T>(path: string, { method = "GET", body, idempotencyKey }: RequestOptions): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json" };
  const token = getToken();

  if (token) headers.Authorization = `Bearer ${token}`;
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (idempotencyKey) headers["Idempotency-Key"] = idempotencyKey;

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);

  let response: Response;
  try {
    response = await fetch(`${API_URL}/api${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: controller.signal,
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      throw new ApiError(0, "timeout", "The server is taking too long to answer. Please try again.");
    }
    throw new ApiError(0, "network_error", "Can't reach the server. Check your connection and try again.");
  } finally {
    clearTimeout(timer);
  }

  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    const error = payload?.error;

    // The token has expired or was revoked. Forgetting it sends the user
    // to the sign-in page (useSession watches for this).
    if (response.status === 401 && path !== "/auth/login") {
      clearSession();
    }

    throw new ApiError(
      response.status,
      error?.code ?? "server_error",
      error?.message ?? "Something went wrong. Please try again.",
      error?.details ?? {},
      error?.request_id,
    );
  }

  return payload?.data as T;
}

function wait(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
