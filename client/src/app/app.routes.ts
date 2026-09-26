import { Routes } from '@angular/router';
import { authGuard, authenticatedGuard, customerSetupGuard, guestGuard } from './core/auth/auth.guards';
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
    path: 'e-mail-aendern',
    loadComponent: () => import('./features/auth/pages/email-change-confirm/email-change-confirm.page').then((module) => module.EmailChangeConfirmPage),
    title: 'Neue E-Mail-Adresse bestätigen | Aesculapp',
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
    path: 'einrichtung',
    canActivate: [authenticatedGuard],
    loadComponent: () => import('./features/setup/customer-setup.page').then((module) => module.CustomerSetupPage),
    title: 'App einrichten | Aesculapp',
  },
  {
    path: '',
    component: AppShellComponent,
    canActivateChild: [authGuard, customerSetupGuard],
    children: [
      { path: 'chat', loadComponent: () => import('./features/chat/chat.page').then(m => m.ChatPage), title: 'Chat | Aesculapp' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./features/dashboard/dashboard.page').then((module) => module.DashboardPage),
        title: 'Übersicht | Aesculapp',
      },
      {
        path: 'news',
        loadComponent: () => import('./features/news/news-list.page').then((module) => module.NewsListPage),
        title: 'Alle Nachrichten | Aesculapp',
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
      { path: 'gutscheine/einloesung', loadComponent: () => import('./features/coupons/active-coupon-redemption.page').then(m => m.ActiveCouponRedemptionPage), title: 'Gutscheine vorzeigen | Aesculapp' },
      { path: 'gutscheine/:id', loadComponent: () => import('./features/coupons/coupon-detail.page').then(m => m.CouponDetailPage), title: 'Gutschein | Aesculapp' },
      { path: 'gutscheine', loadComponent: () => import('./features/coupons/coupons.page').then(m => m.CouponsPage), title: 'Gutscheinheft | Aesculapp' },
      {
        path: 'termine',
        loadComponent: () => import('./features/appointments/appointment-tabs.page').then(m => m.AppointmentTabsPage),
        children: [
          { path: '', loadComponent: () => import('./features/appointments/appointments.page').then(m => m.AppointmentsPage), title: 'Termine | Aesculapp' },
          { path: 'meine', loadComponent: () => import('./features/appointments/my-appointments.page').then(m => m.MyAppointmentsPage), title: 'Meine Termine | Aesculapp' },
        ],
      },
      {
        path: 'punkte/praemien/:id',
        loadComponent: () => import('./features/rewards/reward-detail.page').then(module => module.RewardDetailPage),
        title: 'Prämie | Aesculapp',
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
        path: 'trophaeen',
        loadComponent: () => import('./features/achievements/achievements.page').then((module) => module.AchievementsPage),
        title: 'Trophäen | Aesculapp',
      },
      {
        path: 'medikamente',
        loadComponent: () => import('./features/medications/medications.page').then((module) => module.MedicationsPage),
        title: 'Medikamentenplan | Aesculapp',
      },
      { path: 'familie', loadComponent: () => import('./features/family/family.page').then((module) => module.FamilyPage), title: 'Familienzugang | Aesculapp' },
      { path: 'kontakt', loadComponent: () => import('./features/contact/contact.page').then((module) => module.ContactPage), title: 'Kontakt | Aesculapp' },
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
