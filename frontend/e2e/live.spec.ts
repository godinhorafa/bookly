import { expect, test, type Browser, type Page } from '@playwright/test';

/*
 * Two customers looking at the same day: when one books or cancels, the other's
 * calendar changes without a reload. The path is the real one: API → outbox →
 * relay → signed webhook → realtime service → WebSocket → dashboard.
 *
 * Both sign in with the demo account in separate browser contexts (no shared
 * storage), which is all this needs: the slot update goes to every dashboard.
 * 17:00 three days ahead stays clear of the journey in booking.spec.ts.
 */
const DAY = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit' })
  .format(new Date(Date.now() + 3 * 86_400_000));
test.describe.configure({ mode: 'serial' });

let booker: Page;
let watcher: Page;

async function openTennisCourt(browser: Browser): Promise<Page> {
  const page = await (await browser.newContext()).newPage();
  await page.goto('/');
  await page.getByLabel('Password').fill('password');
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.getByLabel(/Tennis court/).check();
  await page.getByLabel('Day', { exact: true }).fill(DAY);
  await expect(page.getByLabel('Live activity')).toContainText('live');

  return page;
}

test.beforeAll(async ({ browser }) => {
  booker = await openTennisCourt(browser);
  watcher = await openTennisCourt(browser);
});

test.afterAll(async () => {
  await booker.context().close();
  await watcher.context().close();
});

test('shows a slot booked by someone else as busy, without reloading', async () => {
  await expect(watcher.getByRole('button', { name: '17:00 free' })).toBeEnabled();

  await booker.getByRole('button', { name: '17:00 free' }).click();
  await booker.getByRole('button', { name: 'Book', exact: true }).click();
  await expect(booker.getByLabel('Booking on hold')).toBeVisible();

  await expect(watcher.getByRole('button', { name: '17:00 busy' })).toBeDisabled();
  const feed = watcher.getByLabel('Live activity').getByRole('listitem').first();
  await expect(feed).toContainText('Tennis court was just booked');
  await expect(feed).toContainText('17:00–18:00');
});

test('frees the slot for everyone when the booking is cancelled', async () => {
  const row = booker.getByLabel('My bookings').locator('tr[data-status="pending"]');
  await row.getByRole('button', { name: 'Cancel' }).click();
  await booker.getByRole('alertdialog').getByRole('button', { name: 'Cancel booking' }).click();
  await expect(booker.getByLabel('My bookings').locator('tr[data-status="cancelled"]')).toHaveCount(1);

  await expect(watcher.getByRole('button', { name: '17:00 free' })).toBeEnabled();
  const feed = watcher.getByLabel('Live activity').getByRole('listitem').first();
  await expect(feed).toContainText('Tennis court is free again');
  await expect(feed).toContainText('17:00–18:00');
});
