export interface ThemeDefinition {
  readonly id: string;
  readonly pharmacyName: string;
  readonly logoPath: string;
  readonly squareLogoPath: string;
  readonly faviconPath: string;
  readonly pointsPerEuro: number;
  readonly allowDuplicateReceiptImports: boolean;
  readonly showCustomerDebugOutput: boolean;
  readonly websiteUrl: string | null;
  readonly appNoticeEnabled: boolean;
  readonly appNoticeTitle: string | null;
  readonly appNoticeHtml: string;
  readonly birthdayGreetingEnabled: boolean;
  readonly birthdayGreetingTitle: string | null;
  readonly birthdayGreetingText: string | null;
  readonly birthdayGreetingImageUrl: string | null;
}

/**
 * Development switch: Select the theme folder delivered with this build here.
 *
 * A production installation ships with exactly one selected theme. A later
 * admin interface may update this build configuration, but must not expose a
 * theme switch to app users.
 */
export const activeTheme: ThemeDefinition = {
  id: 'sta',
  pharmacyName: 'Stadt-Apotheke Trofaiach',
  logoPath: 'assets/tenants/sta/logo-fallback.svg',
  squareLogoPath: 'assets/tenants/sta/logo-square-fallback.svg',
  faviconPath: 'assets/tenants/sta/favicon.png',
  pointsPerEuro: 10,
  allowDuplicateReceiptImports: false,
  showCustomerDebugOutput: false,
  websiteUrl: null,
  appNoticeEnabled: false,
  appNoticeTitle: null,
  appNoticeHtml: '',
  birthdayGreetingEnabled: false,
  birthdayGreetingTitle: null,
  birthdayGreetingText: null,
  birthdayGreetingImageUrl: null,
};
