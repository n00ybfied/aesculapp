import { Routes } from '@angular/router';
import { adminAuthGuard } from './core/auth/admin-auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./features/login/admin-login.component').then((module) => module.AdminLoginComponent),
  },
  {
    path: 'einladung-annehmen',
    loadComponent: () => import('./features/users/accept-invitation.component').then((module) => module.AcceptInvitationComponent),
  },
  {
    path: '',
    canActivate: [adminAuthGuard],
    loadComponent: () => import('./layout/admin-shell.component').then((module) => module.AdminShellComponent),
    children: [
      {
        path: 'dashboard',
        loadComponent: () => import('./features/dashboard/admin-dashboard.component').then((module) => module.AdminDashboardComponent),
      },
      { path: 'praemien', loadComponent: () => import('./features/rewards/admin-rewards.component').then((module) => module.AdminRewardsComponent) },
      { path: 'inhalte', loadComponent: () => import('./features/news/news-list.component').then((module) => module.NewsListComponent) },
      { path: 'inhalte/neu', loadComponent: () => import('./features/news/news-editor.component').then((module) => module.NewsEditorComponent) },
      { path: 'inhalte/:id', loadComponent: () => import('./features/news/news-editor.component').then((module) => module.NewsEditorComponent) },
      { path: 'einloesungen', loadComponent: () => import('./features/redemptions/active-redemptions.component').then((module) => module.ActiveRedemptionsComponent) },
      { path: 'buchungen', pathMatch: 'full', redirectTo: 'kunden' },
      { path: 'kunden', loadComponent: () => import('./features/customers/admin-customers.component').then((module) => module.AdminCustomersComponent) },
      { path: 'benutzer', loadComponent: () => import('./features/users/admin-users.component').then((module) => module.AdminUsersComponent) },
      { path: 'einstellungen', loadComponent: () => import('./features/settings/tenant-branding.component').then((module) => module.TenantBrandingComponent) },
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
    ],
  },
  { path: '**', redirectTo: '' },
];
