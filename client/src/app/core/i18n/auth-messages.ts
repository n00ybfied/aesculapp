export const authMessages = {
  registrationConflict: () => $localize`:@@auth.error.registration-conflict:Für diesen Benutzernamen oder diese E-Mail-Adresse besteht bereits ein Konto.`,
  registrationInvalid: () => $localize`:@@auth.error.registration-invalid:Die Registrierung konnte nicht abgeschlossen werden. Bitte prüfen Sie Ihre Angaben.`,
  resetRequestFailed: () => $localize`:@@auth.error.password-reset-request:Die Anfrage konnte gerade nicht gesendet werden. Bitte versuchen Sie es später erneut.`,
  resetInvalid: () => $localize`:@@auth.error.password-reset-invalid:Dieser Link ist ungültig oder abgelaufen. Fordern Sie bitte einen neuen an.`,
};
