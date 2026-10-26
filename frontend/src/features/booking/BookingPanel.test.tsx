import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Space } from '../spaces/queries';
import { BookingPanel } from './BookingPanel';

const space: Space = {
  id: '0b9e7e4c-2f4b-4c1a-9d3e-7a1b2c3d4e01',
  name: 'Focus room',
  timezone: 'America/Sao_Paulo',
  capacity: 6,
  hourly_rate: { amount: 6000, currency: 'BRL' },
  cancellation_policy: 'moderate',
  opening_hours: [{ weekdays: [1, 2, 3, 4, 5, 6, 7], start: '08:00', end: '10:00' }],
};

function json(body: unknown, status = 200, contentType = 'application/json') {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': contentType } });
}

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={client}>
      <BookingPanel space={space} />
    </QueryClientProvider>,
  );
}

describe('BookingPanel', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('books a picked slot with an idempotency key and shows the quote', async () => {
    const fetch = vi.fn(async (request: Request) => {
      if (request.url.includes('/availability')) return json({ data: { space_id: space.id, from: '', to: '', busy: [] } });

      return json({
        data: {
          id: 'b-1', status: 'pending', space_id: space.id, start: '2026-11-02T11:30:00Z', end: '2026-11-02T12:30:00Z',
          price: { amount: 6000, currency: 'BRL' },
          hold_until: '2026-11-01T10:15:00Z',
          quote: { base: { amount: 6000, currency: 'BRL' }, adjustments: [], total: { amount: 6000, currency: 'BRL' } },
        },
      }, 201);
    });
    vi.stubGlobal('fetch', fetch);
    renderPanel();

    await userEvent.click(await screen.findByRole('button', { name: '08:30 free' }));
    await userEvent.click(screen.getByRole('button', { name: 'Book' }));

    expect(await screen.findByRole('table', { name: 'Price breakdown' })).toHaveTextContent('R$ 60,00');
    // The deadline is shown in the space's time zone (São Paulo, UTC-3).
    expect(screen.getByText('Pay by 07:15, or the slot is released for others.')).toBeInTheDocument();
    const post = fetch.mock.calls.map(([r]) => r).find((r) => r.method === 'POST') as Request;
    expect(post.headers.get('Idempotency-Key')).toMatch(/^[0-9a-f-]{36}$/);
    expect(await post.json()).toMatchObject({ space_id: space.id, coupon: null });
  });

  it('shows every broken rule returned by the API', async () => {
    vi.stubGlobal('fetch', vi.fn(async (request: Request) => {
      if (request.url.includes('/availability')) return json({ data: { space_id: space.id, from: '', to: '', busy: [] } });

      return json({
        type: 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#booking-rules-violated',
        title: 'Booking rules violated', status: 422, detail: 'The booking request breaks one or more rules.',
        violations: ['Bookings must start at least 60 minutes from now.', 'A customer can hold at most 5 upcoming bookings.'],
      }, 422, 'application/problem+json');
    }));
    renderPanel();

    await userEvent.click(await screen.findByRole('button', { name: '08:00 free' }));
    await userEvent.click(screen.getByRole('button', { name: 'Book' }));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Bookings must start at least 60 minutes from now.');
    expect(alert).toHaveTextContent('A customer can hold at most 5 upcoming bookings.');
  });
});
