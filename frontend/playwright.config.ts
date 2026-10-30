import { defineConfig, devices } from "@playwright/test";

// The end-to-end test runs against the full Docker stack (docker compose up),
// so it needs no web server of its own here.
export default defineConfig({
  testDir: "./tests/e2e",
  // Generous, because the dev server compiles each page on its first visit
  // and Docker on a laptop can be slow while several browsers are open.
  timeout: 180_000,
  expect: { timeout: 15_000 },
  retries: 0,
  reporter: "list",
  use: {
    baseURL: process.env.E2E_BASE_URL ?? "http://localhost:3000",
    // Set PW_BROWSER_CHANNEL=chrome to use an installed Chrome instead of
    // downloading Playwright's own browser (npx playwright install chromium).
    channel: process.env.PW_BROWSER_CHANNEL || undefined,
    trace: "retain-on-failure",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
