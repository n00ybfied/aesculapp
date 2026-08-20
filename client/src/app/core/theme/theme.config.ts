export interface ThemeDefinition {
  readonly id: string;
  readonly pharmacyName: string;
  readonly logoPath: string;
  readonly faviconPath: string;
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
  logoPath: 'assets/tenants/sta/logo.png',
  faviconPath: 'assets/tenants/sta/favicon.png',
};
