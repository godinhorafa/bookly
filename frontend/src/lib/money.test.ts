import { formatMoney } from './money';

it('formats minor units in the given currency', () => {
  // Intl separates the symbol with a no-break space, so it never wraps onto its own line.
  expect(formatMoney({ amount: 12_050, currency: 'BRL' })).toBe('R$\u00a0120,50');
  expect(formatMoney({ amount: 999, currency: 'EUR' }, 'en-GB')).toBe('€9.99');
});
