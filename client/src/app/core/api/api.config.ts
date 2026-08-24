import { InjectionToken } from '@angular/core';

export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL');

export const localApiBaseUrl = 'http://localhost:6080/api/v1';
