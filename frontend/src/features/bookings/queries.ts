import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, idempotencyKey, unwrap } from '../../api/client';
import type { components } from '../../api/schema';

export type Booking = components['schemas']['BookingResource'];
export type NewBooking = components['schemas']['CreateBookingRequest'];

export function useMyBookings() {
  return useQuery({
    queryKey: ['bookings'],
    queryFn: async () => (await unwrap(api.GET('/bookings'))).data,
  });
}

/** Anything that changes a booking also changes the calendar and the list. */
function useInvalidateBookings() {
  const client = useQueryClient();

  return () => Promise.all([
    client.invalidateQueries({ queryKey: ['bookings'] }),
    client.invalidateQueries({ queryKey: ['availability'] }),
  ]);
}

export function useCreateBooking() {
  const invalidate = useInvalidateBookings();

  return useMutation({
    mutationFn: async (body: NewBooking) =>
      (await unwrap(api.POST('/bookings', { body, headers: idempotencyKey() }))).data,
    onSuccess: invalidate,
  });
}

export function usePayBooking() {
  const invalidate = useInvalidateBookings();

  return useMutation({
    mutationFn: async ({ id, paymentMethod }: { id: string; paymentMethod: string }) =>
      (await unwrap(api.POST('/bookings/{booking}/payment', {
        params: { path: { booking: id } },
        body: { payment_method: paymentMethod },
        headers: idempotencyKey(),
      }))).data,
    onSettled: invalidate,
  });
}

export function useCancelBooking() {
  const invalidate = useInvalidateBookings();

  return useMutation({
    mutationFn: async (id: string) =>
      (await unwrap(api.POST('/bookings/{booking}/cancellation', {
        params: { path: { booking: id } },
        headers: idempotencyKey(),
      }))).data,
    onSuccess: invalidate,
  });
}
