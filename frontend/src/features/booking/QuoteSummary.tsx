import type { components } from '@/api/schema';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type Quote = components['schemas']['QuoteResource'];

/** The price explained line by line, exactly as the API computed it. */
export function QuoteSummary({ quote }: { quote: Quote }) {
  return (
    <table className="w-full text-sm" aria-label="Price breakdown">
      <tbody className="[&_td]:py-1.5">
        <tr>
          <td className="text-muted-foreground">Base price</td>
          <td className="text-right tabular-nums">{formatMoney(quote.base)}</td>
        </tr>
        {quote.adjustments.map((adjustment) => (
          <tr key={adjustment.rule}>
            <td className="text-muted-foreground">{adjustment.description}</td>
            <td className={cn('text-right tabular-nums', adjustment.kind === 'discount' ? 'text-success' : 'text-foreground')}>
              {adjustment.kind === 'discount' ? '−' : '+'} {formatMoney(adjustment.amount)}
            </td>
          </tr>
        ))}
        <tr className="border-t font-semibold [&_td]:pt-3">
          <td>Total</td>
          <td className="text-right text-base tabular-nums">{formatMoney(quote.total)}</td>
        </tr>
      </tbody>
    </table>
  );
}
