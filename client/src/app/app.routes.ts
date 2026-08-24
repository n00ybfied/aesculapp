import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth/auth.guards';
import { AppShellComponent } from './shared/layout/app-shell.component';

export const routes: Routes = [
  {
    path: 'login',
    canMatch: [guestGuard],
    loadComponent: () =>
      import('./features/auth/pages/login/login.page').then((module) => module.LoginPage),
    title: 'Anmelden | Aesculapp',
  },
  {
    path: 'registrierung',
    canMatch: [guestGuard],
    loadComponent: () =>
      import('./features/auth/pages/registration/registration.page').then((module) => module.RegistrationPage),
    title: 'Konto erstellen | Aesculapp',
  },
  {
    path: 'passwort-vergessen',
    canMatch: [guestGuard],
    loadComponent: () =>
      import('./features/auth/pages/password-reset-request/password-reset-request.page').then((module) => module.PasswordResetRequestPage),
    title: 'Passwort vergessen | Aesculapp',
  },
  {
    path: 'passwort-zuruecksetzen',
    canMatch: [guestGuard],
    loadComponent: () =>
      import('./features/auth/pages/password-reset-confirm/password-reset-confirm.page').then((module) => module.PasswordResetConfirmPage),
    title: 'Passwort zurücksetzen | Aesculapp',
  },
  {
    path: '',
    component: AppShellComponent,
    canActivateChild: [authGuard],
    children: [
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./features/dashboard/dashboard.page').then((module) => module.DashboardPage),
        title: 'Übersicht | Aesculapp',
      },
      {
        path: 'punkte/einloesung',
        loadComponent: () =>
          import('./features/rewards/active-redemption.page').then((module) => module.ActiveRedemptionPage),
        title: 'Einlösung vorzeigen | Aesculapp',
      },
      {
        path: 'punkte',
        loadComponent: () =>
          import('./features/rewards/rewards.page').then((module) => module.RewardsPage),
        title: 'Punkte & Prämien | Aesculapp',
      },
      {
        path: 'scanner',
        loadComponent: () =>
          import('./features/scanner/qr-scanner.page').then((module) => module.QrScannerPage),
        title: 'QR-Code scannen | Aesculapp',
      },
      {
        path: 'profil',
        loadComponent: () =>
          import('./features/placeholder/placeholder.page').then((module) => module.PlaceholderPage),
        data: { title: 'Mein Profil', description: 'Ihre persönlichen Einstellungen folgen bald.' },
      },
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
    ],
  },
  { path: '**', redirectTo: 'dashboard' },
];
