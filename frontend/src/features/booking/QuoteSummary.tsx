import type { components } from '../../api/schema';
import { formatMoney } from '../../lib/money';

type Quote = components['schemas']['QuoteResource'];

/** The price explained line by line, exactly as the API computed it. */
export function QuoteSummary({ quote }: { quote: Quote }) {
  return (
    <table className="quote" aria-label="Price breakdown">
      <tbody>
        <tr>
          <td>Base price</td>
          <td>{formatMoney(quote.base)}</td>
        </tr>
        {quote.adjustments.map((adjustment) => (
          <tr key={adjustment.rule}>
            <td>{adjustment.description}</td>
            <td>
              {adjustment.kind === 'discount' ? '−' : '+'} {formatMoney(adjustment.amount)}
            </td>
          </tr>
        ))}
        <tr className="total">
          <td>Total</td>
          <td>{formatMoney(quote.total)}</td>
        </tr>
      </tbody>
    </table>
  );
}
