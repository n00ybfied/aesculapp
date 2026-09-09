export const statusMessages = {
  unreadableQrImage: $localize`:@@status.error.qr-image-unreadable:Im gewählten Bild wurde kein lesbarer QR-Code gefunden.`,
  unsupportedReceiptQr: $localize`:@@status.error.receipt-qr-unsupported:Dieser QR-Code enthält keinen unterstützten Kassenbeleg.`,
  cameraUnavailable: $localize`:@@status.error.camera-unavailable:Die Kamera konnte nicht geöffnet werden. Bitte erlauben Sie den Kamerazugriff.`,
  receiptAlreadyImported: $localize`:@@status.error.receipt-already-imported:Dieser Beleg wurde bereits eingelöst.`,
  receiptImported: (points: number) => $localize`:@@status.success.receipt-imported:${points}:points: Punkte wurden gutgeschrieben.`,
  rewardRedeemed: (rewardTitle: string) => $localize`:@@status.success.reward-redeemed:${rewardTitle}:rewardTitle: wurde eingelöst.`,
  notEnoughPoints: () => $localize`:@@status.error.not-enough-points:Für diese Auswahl reichen Ihre Punkte nicht aus.`,
  pointsReset: () => $localize`:@@status.info.points-reset:Punktestand wurde zurückgesetzt.`,
  redemptionCancelled: () => $localize`:@@status.error.redemption-cancelled:Die Einlösung wurde vom Apotheken-Team abgebrochen. Ihre Punkte wurden zurückgebucht.`,
} as const;
