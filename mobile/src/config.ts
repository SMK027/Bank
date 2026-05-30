import Constants from 'expo-constants';

const extra =
  (Constants.expoConfig?.extra as { apiBaseUrl?: string } | undefined) ??
  (Constants.manifest?.extra as { apiBaseUrl?: string } | undefined) ??
  {};

export const API_BASE_URL: string =
  (extra.apiBaseUrl as string | undefined) ?? 'http://10.0.2.2:8083';
