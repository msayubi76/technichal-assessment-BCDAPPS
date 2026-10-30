import { expect, test, type Browser, type Locator, type Page } from "@playwright/test";

// The full journey across both apps: a rider books, a driver accepts and
// completes, and the rider sees each step. Uses the seeded demo accounts.

const RIDER = "rider2@example.com";
const ECONOMY_DRIVERS = ["driver1@example.com", "driver2@example.com", "driver3@example.com", "driver4@example.com"];
const PICKUP = "Liberty Market";
const DROPOFF = "MM Alam Road";

/** Each option also shows the area, so pick by the place name. */
async function choosePlace(page: Page, field: string, place: string) {
  const select = page.getByLabel(field);
  const value = await select.locator("option", { hasText: place }).getAttribute("value");
  await select.selectOption(value ?? "");
}

async function signIn(page: Page, email: string) {
  await page.goto("/login");
  // The button turns on once the page is interactive; typing earlier is lost.
  await expect(page.getByRole("button", { name: "Sign in" })).toBeEnabled({ timeout: 30_000 });
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
}

/** True if the element shows up within the time limit. (isVisible() alone does not wait.) */
async function appears(locator: Locator, timeout: number): Promise<boolean> {
  return locator
    .first()
    .waitFor({ state: "visible", timeout })
    .then(() => true)
    .catch(() => false);
}

/** Drivers move around after each trip, so find one who can see this request. */
async function signInDriverWhoSees(browser: Browser, rideText: string): Promise<Page> {
  for (const email of ECONOMY_DRIVERS) {
    const page = await (await browser.newContext()).newPage();
    await signIn(page, email);
    await page.waitForURL("**/driver");

    // Wait for the dashboard to load before deciding anything.
    const goOnline = page.getByRole("button", { name: "Go online" });
    const goOffline = page.getByRole("button", { name: "Go offline" });
    await expect(goOnline.or(goOffline)).toBeVisible({ timeout: 30_000 });
    if (await goOnline.isVisible()) await goOnline.click();

    const card = page.getByRole("listitem").filter({ hasText: rideText });
    if (await appears(card, 20_000)) return page;

    await page.context().close();
  }
  throw new Error("No seeded Economy driver could see the ride request.");
}

test("rider books, driver accepts and completes", async ({ page, browser }) => {
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
  await page.getByRole("button", { name: "Request ride" }).click();

  await expect(page.getByRole("heading", { name: "Finding you a driver" })).toBeVisible();

  const driverPage = await signInDriverWhoSees(browser, `${PICKUP} → ${DROPOFF}`);
  await driverPage
    .getByRole("listitem")
    .filter({ hasText: `${PICKUP} → ${DROPOFF}` })
    .first()
    .getByRole("button", { name: "Accept" })
    .click();
  await expect(driverPage.getByRole("heading", { name: "Current trip" })).toBeVisible();

  await expect(page.getByRole("heading", { name: "Your driver is on the way" })).toBeVisible();

  await driverPage.getByRole("button", { name: "Complete trip" }).click();
  await expect(driverPage.getByText(/Trip completed/)).toBeVisible();

  await expect(page.getByRole("heading", { name: "You have arrived" })).toBeVisible();
});
