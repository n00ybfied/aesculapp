import { parseCashRegisterQr } from './cash-register-qr.parser';

describe('parseCashRegisterQr', () => {
  it('reads the receipt number, date and total from the supplied cash-register format', () => {
    const receipt = parseCashRegisterQr('_HA_0_K26/000498_2026-08-05T08:57:36_0,00_0,00_0,00_0,00_26,50_0_0_0_MEUCIGfxx83XX+b8GEjJbjlubGG+v8dk13yz/AgH2DOEGadAAiEAiRFNsdfBxPx4DSaVqOpFjt4IglSldfEh3zIwIaiZVAY=');

    expect(receipt).toEqual({
      receiptNumber: 'K26/000498',
      purchasedAt: '2026-08-05T08:57:36',
      eligibleCents: 2650,
    });
  });

  it('rejects a QR code without a valid receipt total', () => {
    expect(() => parseCashRegisterQr('_HA_0_K26/000498_2026-08-05T08:57:36_0,00')).toThrow();
  });

  it('uses the pharmacy-defined loyalty amount instead of adding tax-rate amounts', () => {
    const receipt = parseCashRegisterQr('_HA_0_K26/000499_2026-08-05T09:00:00_5,00_1,20_0,80_0,00_3,00_counter_certificate_chain_signature');

    expect(receipt.eligibleCents).toBe(300);
  });
});
