import type { ReactNode } from "react";
import type { User } from "@/lib/api/types";
import { Button } from "@/components/ui/Button";

interface AppShellProps {
  user: User | null;
  onLogout?: () => void;
  children: ReactNode;
}

export function AppShell({ user, onLogout, children }: AppShellProps) {
  return (
    <div className="flex min-h-dvh flex-col bg-slate-100">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
          <div className="flex items-center gap-2">
            <span className="grid size-8 place-items-center rounded-lg bg-emerald-600 text-sm font-bold text-white">R</span>
            <span className="font-semibold text-slate-900">RideLahore</span>
          </div>
          {user && (
            <div className="flex items-center gap-3">
              <span className="hidden text-sm text-slate-600 sm:inline">
                {user.name} · <span className="capitalize">{user.role}</span>
              </span>
              <Button variant="ghost" className="px-3 py-2" onClick={onLogout}>
                Sign out
              </Button>
            </div>
          )}
        </div>
      </header>
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-6">{children}</main>
    </div>
  );
}
