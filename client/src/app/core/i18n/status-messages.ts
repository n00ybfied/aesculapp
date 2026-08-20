export const statusMessages = {
  unreadableQrImage: $localize`:@@status.error.qr-image-unreadable:Im gewählten Bild wurde kein lesbarer QR-Code gefunden.`,
  receiptImported: (points: number) => $localize`:@@status.success.receipt-imported:${points}:points: Punkte wurden gutgeschrieben.`,
  rewardRedeemed: (rewardTitle: string) => $localize`:@@status.success.reward-redeemed:${rewardTitle}:rewardTitle: wurde eingelöst.`,
  notEnoughPoints: () => $localize`:@@status.error.not-enough-points:Für diese Auswahl reichen Ihre Punkte nicht aus.`,
  pointsReset: () => $localize`:@@status.info.points-reset:Punktestand wurde zurückgesetzt.`,
} as const;
