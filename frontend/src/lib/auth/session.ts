import type { User } from "@/lib/api/types";

// The API token is kept in localStorage so the session survives a refresh.
// Trade-off: any script on the page could read it. In production we would
// keep it in an httpOnly cookie set by a small backend-for-frontend instead.

const TOKEN_KEY = "ride.token";
const USER_KEY = "ride.user";

// The browser only fires "storage" events for changes made in other tabs,
// so we fire our own event for changes made in this tab.
const CHANGE_EVENT = "ride:session-changed";

function storage(): Storage | null {
  try {
    return typeof window === "undefined" ? null : window.localStorage;
  } catch {
    return null;
  }
}

export function getToken(): string | null {
  return storage()?.getItem(TOKEN_KEY) ?? null;
}

export function getStoredUser(): User | null {
  const raw = storage()?.getItem(USER_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}

export function saveSession(token: string, user: User): void {
  storage()?.setItem(TOKEN_KEY, token);
  storage()?.setItem(USER_KEY, JSON.stringify(user));
  notifyChange();
}

export function clearSession(): void {
  storage()?.removeItem(TOKEN_KEY);
  storage()?.removeItem(USER_KEY);
  notifyChange();
}

/** Calls onChange when the user signs in or out, in this tab or another one. */
export function subscribeToSession(onChange: () => void): () => void {
  window.addEventListener("storage", onChange);
  window.addEventListener(CHANGE_EVENT, onChange);
  return () => {
    window.removeEventListener("storage", onChange);
    window.removeEventListener(CHANGE_EVENT, onChange);
  };
}

function notifyChange(): void {
  if (typeof window !== "undefined") window.dispatchEvent(new Event(CHANGE_EVENT));
}

export function homePathFor(user: User): string {
  return user.role === "driver" ? "/driver" : "/";
}
