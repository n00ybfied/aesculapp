import { Routes } from '@angular/router';
import { adminAuthGuard } from './core/auth/admin-auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./features/login/admin-login.component').then((module) => module.AdminLoginComponent),
  },
  {
    path: 'passwort-vergessen',
    loadComponent: () => import('./features/login/admin-password-reset-request.component').then((module) => module.AdminPasswordResetRequestComponent),
  },
  {
    path: 'passwort-zuruecksetzen',
    loadComponent: () => import('./features/login/admin-password-reset-confirm.component').then((module) => module.AdminPasswordResetConfirmComponent),
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
      { path: 'chat', loadComponent: () => import('./features/chat/chat.page').then(m => m.ChatPage) },
      {
        path: 'dashboard',
        loadComponent: () => import('./features/dashboard/admin-dashboard.component').then((module) => module.AdminDashboardComponent),
      },
      { path: 'slider', loadComponent: () => import('./features/dashboard-slides/dashboard-slides.component').then((module) => module.DashboardSlidesComponent) },
      { path: 'statistik', loadComponent: () => import('./features/statistics/admin-statistics.component').then((module) => module.AdminStatisticsComponent) },
      { path: 'praemien/neu', loadComponent: () => import('./features/rewards/reward-editor.component').then(m => m.RewardEditorComponent) },
      { path: 'praemien/:id', loadComponent: () => import('./features/rewards/reward-editor.component').then(m => m.RewardEditorComponent) },
      { path: 'praemien', loadComponent: () => import('./features/rewards/admin-rewards.component').then((module) => module.AdminRewardsComponent) },
      { path: 'gutscheine/neu', loadComponent: () => import('./features/coupons/coupon-editor.component').then(m => m.CouponEditorComponent) },
      { path: 'gutscheine/:id', loadComponent: () => import('./features/coupons/coupon-editor.component').then(m => m.CouponEditorComponent) },
      { path: 'gutscheine', loadComponent: () => import('./features/coupons/admin-coupons.component').then(m => m.AdminCouponsComponent) },
      { path: 'termine', loadComponent: () => import('./features/appointments/admin-appointments.component').then(m => m.AdminAppointmentsComponent) },
      { path: 'inhalte', loadComponent: () => import('./features/news/news-list.component').then((module) => module.NewsListComponent) },
      { path: 'inhalte/neu', loadComponent: () => import('./features/news/news-editor.component').then((module) => module.NewsEditorComponent) },
      { path: 'inhalte/:id', loadComponent: () => import('./features/news/news-editor.component').then((module) => module.NewsEditorComponent) },
      { path: 'medien', loadComponent: () => import('./features/media/media-library.component').then((module) => module.MediaLibraryComponent) },
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
