import type { components } from '../api/schema';

type Money = components['schemas']['MoneyResource'];

/** Formats minor units for display, e.g. { amount: 12050, currency: "BRL" } → "R$ 120,50". */
export function formatMoney(money: Money, locale = 'pt-BR'): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency: money.currency }).format(money.amount / 100);
}
