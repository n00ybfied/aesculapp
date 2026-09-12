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
    path: 'e-mail-bestaetigen',
    canMatch: [guestGuard],
    loadComponent: () => import('./features/auth/pages/email-verification/email-verification.page').then((module) => module.EmailVerificationPage),
    title: 'E-Mail-Adresse bestätigen | Aesculapp',
  },
  {
    path: 'bestaetigung-erneut-senden',
    canMatch: [guestGuard],
    loadComponent: () => import('./features/auth/pages/email-verification-resend/email-verification-resend.page').then((module) => module.EmailVerificationResendPage),
    title: 'Bestätigungs-E-Mail erneut senden | Aesculapp',
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
      { path: 'chat', loadComponent: () => import('./features/chat/chat.page').then(m => m.ChatPage), title: 'Chat | Aesculapp' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./features/dashboard/dashboard.page').then((module) => module.DashboardPage),
        title: 'Übersicht | Aesculapp',
      },
      {
        path: 'news/:id',
        loadComponent: () => import('./features/news/news-detail.page').then((module) => module.NewsDetailPage),
        title: 'Apotheken-News | Aesculapp',
      },
      {
        path: 'punkte/einloesung',
        loadComponent: () =>
          import('./features/rewards/active-redemption.page').then((module) => module.ActiveRedemptionPage),
        title: 'Einlösung vorzeigen | Aesculapp',
      },
      {
        path: 'punkte/historie',
        loadComponent: () =>
          import('./features/rewards/points-history.page').then((module) => module.PointsHistoryPage),
        title: 'Alle Buchungen | Aesculapp',
      },
      {
        path: 'punkte',
        loadComponent: () =>
          import('./features/rewards/rewards.page').then((module) => module.RewardsPage),
        title: 'Punkte & Prämien | Aesculapp',
      },
      {
        path: 'punkte/praemien/:id',
        loadComponent: () => import('./features/rewards/reward-detail.page').then(module => module.RewardDetailPage),
        title: 'Gutschein | Aesculapp',
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
          import('./features/profile/profile.page').then((module) => module.ProfilePage),
        title: 'Mein Profil | Aesculapp',
      },
      {
        path: 'webseite',
        loadComponent: () => import('./features/website/website.page').then((module) => module.WebsitePage),
        title: 'Webseite | Aesculapp',
      },
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
    ],
  },
  { path: '**', redirectTo: 'dashboard' },
];
