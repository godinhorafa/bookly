import { expect, test, type Page } from '@playwright/test';

/*
 * One customer journey, in order, against the real API and database:
 * log in, book, see the slot become busy, fail a payment, pay, cancel.
 * Seeded "Tennis court": America/Sao_Paulo, 06:00-23:00 daily, BRL 90/h, flexible policy.
 *
 * The slot is 15:00 two days ahead: 15:00 is never peak time (no surcharge on any
 * weekday), and two days ahead is always more than 24h away (full refund),
 * whatever day and time the suite runs.
 */
const DAY = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit' })
  .format(new Date(Date.now() + 2 * 86_400_000));
test.describe.configure({ mode: 'serial' });

let page: Page;

test.beforeAll(async ({ browser }) => {
  page = await browser.newPage();
});

test.afterAll(async () => {
  await page.close();
});

test('logs in with the demo account', async () => {
  await page.goto('/');
  await page.getByLabel('Password').fill('password');
  await page.getByRole('button', { name: 'Log in' }).click();

  await expect(page.getByRole('heading', { name: 'Bookly' })).toBeVisible();
  await expect(page.getByRole('group', { name: 'Spaces' })).toContainText('Tennis court');
});

test('books a free slot and explains the price', async () => {
  await page.getByLabel(/Tennis court/).check();
  await page.getByLabel('Day', { exact: true }).fill(DAY);
  await page.getByRole('button', { name: '15:00 free' }).click();
  await page.getByRole('button', { name: 'Book', exact: true }).click();

  const hold = page.getByLabel('Booking on hold');
  await expect(hold).toContainText('On hold until paid');
  await expect(hold.getByRole('table', { name: 'Price breakdown' })).toContainText('R$ 90,00');

  // The calendar now shows the slot as taken.
  await expect(page.getByRole('button', { name: '15:00 busy' })).toBeDisabled();

  if (process.env.UPDATE_SCREENSHOT) {
    // A tall viewport rather than fullPage: the sticky header and sidebar would be drawn mid-page.
    await page.setViewportSize({ width: 1440, height: 1500 });
    await page.screenshot({ path: '../docs/screenshots/dashboard.png' });
    await page.setViewportSize({ width: 1280, height: 720 });
  }
});

test('keeps the booking on hold when the card is declined', async () => {
  const payment = page.getByRole('form', { name: 'Payment' });
  await payment.getByLabel('Test card').selectOption({ label: 'Insufficient funds' });
  await payment.getByRole('button', { name: 'Pay and confirm' }).click();

  await expect(payment.getByRole('alert')).toContainText('insufficient_funds');
  await expect(page.getByLabel('My bookings').locator('tr[data-status="pending"]')).toHaveCount(1);
});

test('confirms the booking once paid', async () => {
  const payment = page.getByRole('form', { name: 'Payment' });
  await payment.getByLabel('Test card').selectOption({ label: 'Visa (succeeds)' });
  await payment.getByRole('button', { name: 'Pay and confirm' }).click();

  await expect(page.getByRole('status')).toHaveText('Booking confirmed. See you there!');
  await expect(page.getByLabel('My bookings').locator('tr[data-status="confirmed"]')).toHaveCount(1);
});

test('shows every broken rule when a request is invalid', async () => {
  // 22:30 for an hour runs past the 23:00 closing time.
  await page.getByRole('button', { name: '22:30 free' }).click();
  await page.getByLabel('Duration').selectOption({ label: '60 min' });
  await page.getByRole('button', { name: 'Book', exact: true }).click();

  await expect(page.getByRole('form', { name: 'New booking' }).getByRole('alert')).toContainText(
    'Bookings must fall entirely within the opening hours of the space.',
  );
});

test('cancels with a full refund under the flexible policy', async () => {
  const row = page.getByLabel('My bookings').locator('tr[data-status="confirmed"]');
  await row.getByRole('button', { name: 'Cancel' }).click();
  // Cancelling asks for confirmation, with the space's refund policy.
  const dialog = page.getByRole('alertdialog', { name: 'Cancel this booking?' });
  await expect(dialog).toContainText('Flexible policy: full refund up to 24h before');
  await dialog.getByRole('button', { name: 'Cancel booking' }).click();

  const cancelled = page.getByLabel('My bookings').locator('tr[data-status="cancelled"]');
  await expect(cancelled).toContainText('refunded R$ 90,00');
  await expect(page.getByRole('button', { name: '15:00 free' })).toBeEnabled();
});
