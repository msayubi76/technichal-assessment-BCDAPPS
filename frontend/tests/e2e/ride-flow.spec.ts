import { expect, test, type Browser, type Page } from "@playwright/test";

// The full journey across both apps: a rider books, a driver accepts and
// completes, and the rider sees each step. Uses the seeded demo accounts.

const RIDER = "rider2@example.com";
const DRIVER = "driver1@example.com";
const PICKUP = "Liberty Market";
const DROPOFF = "MM Alam Road";

/** Each option also shows the area, so pick by the place name. */
async function choosePlace(page: Page, field: string, place: string) {
  const select = page.getByLabel(field);
  const value = await select.locator("option", { hasText: place }).getAttribute("value");
  await select.selectOption(value ?? "");
}

/**
 * Signs in and waits until the app has left the sign-in page. The dev server
 * can reload a page once right after compiling it, which clears what was
 * typed, so this tries again if the first attempt doesn't go through.
 */
async function signIn(page: Page, email: string) {
  await page.goto("/login");

  for (let attempt = 1; attempt <= 3; attempt++) {
    // The button turns on once the page is interactive; typing earlier is lost.
    await expect(page.getByRole("button", { name: "Sign in" })).toBeEnabled({ timeout: 30_000 });
    await page.getByLabel("Email").fill(email);
    await page.getByLabel("Password").fill("password");
    await page.getByRole("button", { name: "Sign in" }).click();

    const left = await page
      .waitForURL((url) => url.pathname !== "/login", { timeout: 20_000 })
      .then(() => true)
      .catch(() => false);
    if (left) return;
  }
  throw new Error(`Could not sign in as ${email}.`);
}

/** The demo driver parked at Liberty Market (see ResetDemoDriversSeeder). */
async function signInDriver(browser: Browser): Promise<Page> {
  const page = await (await browser.newContext()).newPage();
  await signIn(page, DRIVER);

  const goOnline = page.getByRole("button", { name: "Go online" });
  await expect(goOnline.or(page.getByRole("button", { name: "Go offline" }))).toBeVisible({ timeout: 30_000 });
  if (await goOnline.isVisible()) await goOnline.click();

  return page;
}

test("rider books, driver accepts, arrives, drives and completes", async ({ page, browser }) => {
  await signIn(page, RIDER);
  await page.waitForURL((url) => url.pathname === "/");

  // Leave things as we found them if an earlier run stopped half way.
  const leftover = page.getByRole("button", { name: "Cancel ride" });
  await expect(page.getByLabel("Pickup").or(leftover)).toBeVisible({ timeout: 30_000 });
  if (await leftover.isVisible()) {
    await leftover.click();
    await page.getByRole("button", { name: "Yes, cancel" }).click();
    await page.getByRole("button", { name: "Book another ride" }).click();
  }

  await choosePlace(page, "Pickup", PICKUP);
  await choosePlace(page, "Destination", DROPOFF);
  await page.getByText("Economy", { exact: true }).click();
  await page.getByRole("button", { name: "Book a ride" }).click();

  await expect(page.getByRole("heading", { name: "Finding you a driver" })).toBeVisible();

  const driverPage = await signInDriver(browser);
  await driverPage
    .getByRole("listitem")
    .filter({ hasText: `${PICKUP} → ${DROPOFF}` })
    .first()
    .getByRole("button", { name: "Accept" })
    .click();
  await expect(driverPage.getByRole("heading", { name: "Current trip" })).toBeVisible();

  await expect(page.getByRole("heading", { name: "Your driver is on the way" })).toBeVisible();

  // Driver reaches the pickup; the rider says they're coming.
  await driverPage.getByRole("button", { name: "Arrived" }).click();
  await expect(page.getByRole("heading", { name: "Your driver has arrived" })).toBeVisible();
  await page.getByRole("button", { name: "I'm coming" }).click();
  await expect(page.getByText("Your driver knows you are coming.")).toBeVisible();
  await expect(driverPage.getByText("The rider is coming.")).toBeVisible();

  await driverPage.getByRole("button", { name: "On the way to destination" }).click();
  await expect(page.getByRole("heading", { name: "On the way to your destination" })).toBeVisible();
  await expect(page.getByRole("button", { name: "Cancel ride" })).toBeHidden();

  await driverPage.getByRole("button", { name: "Completed" }).click();
  await expect(driverPage.getByText(/Trip completed/)).toBeVisible();

  await expect(page.getByRole("heading", { name: "You have arrived" })).toBeVisible();
});
