/** Number inputs emit numbers or null; loaded schedules contain strings. */
export function parseMedicationDose(value: string | number | null): number | null {
  if (value === null || (typeof value === 'string' && value.trim() === '')) return null;

  const dose = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(dose) && dose >= 0 && dose <= 99 && Number.isInteger(dose * 2)
    ? dose
    : null;
}
