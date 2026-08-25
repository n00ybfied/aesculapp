import { InjectionToken } from '@angular/core';

export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL');

const localDevelopmentHosts = new Set(['localhost', '127.0.0.1']);

export const apiBaseUrl = localDevelopmentHosts.has(globalThis.location.hostname)
  ? 'http://localhost:6080/api/v1'
  : 'https://api.aesculapp.floatbox.at/api/v1';
