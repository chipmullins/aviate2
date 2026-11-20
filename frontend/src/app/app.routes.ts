import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/guards';
import { Shell } from './layout/shell.component';

export const routes: Routes = [
  { path: 'login', canActivate: [guestGuard], title: 'Log in · Aviate', loadComponent: () => import('./pages/auth/login.page').then((m) => m.LoginPage) },
  { path: 'register', canActivate: [guestGuard], title: 'Join · Aviate', loadComponent: () => import('./pages/auth/register.page').then((m) => m.RegisterPage) },
  {
    path: '',
    component: Shell,
    canActivate: [authGuard],
    children: [
      { path: '', title: 'Aviate', loadComponent: () => import('./pages/feed.page').then((m) => m.FeedPage) },
      { path: 'u/:username', title: 'Profile · Aviate', loadComponent: () => import('./pages/profile.page').then((m) => m.ProfilePage) },
      { path: 'posts/:id', title: 'Post · Aviate', loadComponent: () => import('./pages/post.page').then((m) => m.PostPage) },
      { path: 'friends', title: 'Friends · Aviate', loadComponent: () => import('./pages/friends.page').then((m) => m.FriendsPage) },
      { path: 'messages', title: 'Messages · Aviate', loadComponent: () => import('./pages/messages.page').then((m) => m.MessagesPage) },
      { path: 'messages/:username', title: 'Messages · Aviate', loadComponent: () => import('./pages/messages.page').then((m) => m.MessagesPage) },
      { path: 'notifications', title: 'Notifications · Aviate', loadComponent: () => import('./pages/notifications.page').then((m) => m.NotificationsPage) },
      { path: 'search', title: 'Search · Aviate', loadComponent: () => import('./pages/search.page').then((m) => m.SearchPage) },
      { path: 'settings', title: 'Settings · Aviate', loadComponent: () => import('./pages/settings.page').then((m) => m.SettingsPage) },
      { path: '**', title: 'Not found · Aviate', loadComponent: () => import('./pages/not-found.page').then((m) => m.NotFoundPage) },
    ],
  },
];
