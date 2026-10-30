"use client";

import { useRouter } from "next/navigation";
import { useCallback, useEffect, useMemo, useSyncExternalStore } from "react";
import { api } from "@/lib/api/endpoints";
import type { User, UserRole } from "@/lib/api/types";
import { clearSession, getStoredUser, getToken, homePathFor, subscribeToSession } from "@/lib/auth/session";
import { disconnectRealtime } from "@/lib/realtime";

// The stored user as a plain string, so React can compare snapshots cheaply.
// On the server there is no localStorage, so it returns undefined ("not known yet").
function readSnapshot(): string | null {
  return getToken() ? JSON.stringify(getStoredUser()) : null;
}

/**
 * The signed-in user. Sends visitors who are not signed in to /login, and
 * users of the wrong role to their own home page.
 */
export function useSession(requiredRole: UserRole) {
  const router = useRouter();
  const snapshot = useSyncExternalStore(subscribeToSession, readSnapshot, () => undefined);

  const user = useMemo<User | null>(() => (snapshot ? (JSON.parse(snapshot) as User | null) : null), [snapshot]);

  useEffect(() => {
    if (snapshot === undefined) return;

    if (!user) {
      // Signed out here, in another tab, or the token expired.
      disconnectRealtime();
      router.replace("/login");
    } else if (user.role !== requiredRole) {
      router.replace(homePathFor(user));
    }
  }, [snapshot, user, requiredRole, router]);

  const logout = useCallback(async () => {
    try {
      await api.logout();
    } catch {
      // Signing out locally still works if the server can't be reached.
    }
    // The effect above notices the change and goes to the sign-in page.
    clearSession();
  }, []);

  const ready = user !== null && user.role === requiredRole;

  return { user: ready ? user : null, ready, logout };
}
