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
        path: 'punkte',
        loadComponent: () =>
          import('./features/placeholder/placeholder.page').then((module) => module.PlaceholderPage),
        data: { title: 'Punkte & Prämien', description: 'Ihre Punktewelt entsteht gerade.' },
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
