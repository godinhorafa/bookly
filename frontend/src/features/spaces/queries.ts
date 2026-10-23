import { useQuery } from '@tanstack/react-query';
import { api, unwrap } from '../../api/client';
import type { components } from '../../api/schema';
import { dayRange } from '../../lib/time';

export type Space = components['schemas']['SpaceResource'];
export type Availability = components['schemas']['AvailabilityResource'];

export function useSpaces() {
  return useQuery({
    queryKey: ['spaces'],
    queryFn: async () => (await unwrap(api.GET('/spaces'))).data,
  });
}

export function useAvailability(space: Space | undefined, day: string) {
  return useQuery({
    queryKey: ['availability', space?.id, day],
    enabled: space !== undefined,
    queryFn: async () => {
      const { from, to } = dayRange(day, (space as Space).timezone);
      const params = { path: { space: (space as Space).id }, query: { from, to } };

      return (await unwrap(api.GET('/spaces/{space}/availability', { params }))).data;
    },
  });
}
