export interface CashRegisterReceipt {
  readonly receiptNumber: string;
  readonly purchasedAt: string;
  readonly eligibleCents: number;
}

const dateTimePattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/;
const amountPattern = /^\d+(?:,\d{2})$/;

/**
 * Parses the pharmacy's custom loyalty QR format.
 *
 * The fifth monetary field following the timestamp contains the amount that
 * is eligible for loyalty points. The cash register has already excluded
 * prescription-only products when generating this value. The final field is
 * a signature and must be verified by the future server-side integration.
 */
export function parseCashRegisterQr(rawValue: string): CashRegisterReceipt {
  const parts = rawValue.trim().split('_');
  const timestampIndex = parts.findIndex((part) => dateTimePattern.test(part));

  if (timestampIndex < 1 || timestampIndex + 5 >= parts.length) {
    throw new Error('Unsupported cash-register QR code.');
  }

  const eligibleAmount = parts[timestampIndex + 5];
  if (!amountPattern.test(eligibleAmount)) {
    throw new Error('Cash-register QR code contains no valid eligible amount.');
  }

  return {
    receiptNumber: parts[timestampIndex - 1],
    purchasedAt: parts[timestampIndex],
    eligibleCents: parseEuroCents(eligibleAmount),
  };
}

function parseEuroCents(amount: string): number {
  const [euros, cents] = amount.split(',');
  return Number(euros) * 100 + Number(cents);
}
