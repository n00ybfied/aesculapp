import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth/auth.guards';

export const routes: Routes = [
  {
    path: 'login',
    canMatch: [guestGuard],
    loadComponent: () =>
      import('./features/auth/pages/login/login.page').then((module) => module.LoginPage),
    title: 'Anmelden | Aesculapp',
  },
  {
    path: 'dashboard',
    canMatch: [authGuard],
    loadComponent: () =>
      import('./features/dashboard/dashboard.page').then((module) => module.DashboardPage),
    title: 'Übersicht | Aesculapp',
  },
  { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
  { path: '**', redirectTo: 'dashboard' },
];
