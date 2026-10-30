"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState, useSyncExternalStore, type FormEvent } from "react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { ErrorAlert } from "@/components/ui/ErrorAlert";
import { ApiError } from "@/lib/api/client";
import { api } from "@/lib/api/endpoints";
import { getStoredUser, getToken, homePathFor, saveSession } from "@/lib/auth/session";

type FieldErrors = { email?: string; password?: string };

// Accounts created by the database seeder, so reviewers can try both roles.
const demoAccounts = [
  { label: "Rider", email: "rider@example.com" },
  { label: "Driver (Economy)", email: "driver1@example.com" },
  { label: "Driver (Comfort)", email: "driver5@example.com" },
];

const noSubscription = () => () => {};

/** False in the server HTML, true once React is running in the browser. */
function useHydrated(): boolean {
  return useSyncExternalStore(
    noSubscription,
    () => true,
    () => false,
  );
}

export function LoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [error, setError] = useState<unknown>(null);
  const [submitting, setSubmitting] = useState(false);
  // Before React takes over, a click would submit the form as a plain page
  // reload and lose what was typed. So the button waits until then.
  const hydrated = useHydrated();

  // Someone who is already signed in has nothing to do here.
  useEffect(() => {
    const user = getStoredUser();
    if (getToken() && user) router.replace(homePathFor(user));
  }, [router]);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();

    const errors: FieldErrors = {};
    if (!email.trim()) errors.email = "Enter your email address.";
    if (!password) errors.password = "Enter your password.";
    setFieldErrors(errors);
    setError(null);
    if (errors.email || errors.password) return;

    setSubmitting(true);
    try {
      const result = await api.login(email.trim(), password);
      saveSession(result.token, result.user);
      router.replace(homePathFor(result.user));
    } catch (e) {
      // Show the server's field messages under the fields, anything else above the button.
      if (e instanceof ApiError && e.code === "validation_failed") {
        setFieldErrors({ email: e.details.email?.[0], password: e.details.password?.[0] });
      } else {
        setError(e);
      }
      setSubmitting(false);
    }
  }

  return (
    <main className="grid min-h-dvh place-items-center px-4 py-10">
      <div className="w-full max-w-sm space-y-4">
        <div className="flex items-center justify-center gap-2">
          <span className="grid size-9 place-items-center rounded-lg bg-emerald-600 font-bold text-white">R</span>
          <span className="text-lg font-semibold">RideLahore</span>
        </div>

        <Card>
          <form className="space-y-4" onSubmit={handleSubmit} noValidate>
            <h1 className="text-xl font-semibold">Sign in</h1>

            <Field
              id="email"
              label="Email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={setEmail}
              error={fieldErrors.email}
            />
            <Field
              id="password"
              label="Password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={setPassword}
              error={fieldErrors.password}
            />

            {error !== null && <ErrorAlert title="Could not sign in" error={error} />}

            <Button type="submit" className="w-full" loading={submitting} disabled={!hydrated}>
              {submitting ? "Signing in…" : "Sign in"}
            </Button>
          </form>
        </Card>

        <Card className="text-sm">
          <p className="mb-2 font-medium text-slate-700">Demo accounts (password: password)</p>
          <ul className="space-y-1">
            {demoAccounts.map((account) => (
              <li key={account.email}>
                <button
                  type="button"
                  className="text-emerald-700 underline-offset-2 hover:underline"
                  onClick={() => {
                    setEmail(account.email);
                    setPassword("password");
                  }}
                >
                  {account.label}: {account.email}
                </button>
              </li>
            ))}
          </ul>
        </Card>
      </div>
    </main>
  );
}

interface FieldProps {
  id: string;
  label: string;
  type: string;
  autoComplete: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
}

function Field({ id, label, type, autoComplete, value, onChange, error }: FieldProps) {
  return (
    <div className="space-y-1">
      <label htmlFor={id} className="text-sm font-medium text-slate-700">
        {label}
      </label>
      <input
        id={id}
        type={type}
        autoComplete={autoComplete}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        className={`w-full rounded-xl border bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-600 ${
          error ? "border-rose-400" : "border-slate-300"
        }`}
      />
      {error && (
        <p id={`${id}-error`} className="text-sm text-rose-600">
          {error}
        </p>
      )}
    </div>
  );
}
