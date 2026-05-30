import * as SecureStore from 'expo-secure-store';

const KEY = 'bankapp_tpe_token';

export const tokenStore = {
  async get(): Promise<string | null> {
    try {
      return await SecureStore.getItemAsync(KEY);
    } catch {
      return null;
    }
  },
  async set(token: string): Promise<void> {
    try {
      await SecureStore.setItemAsync(KEY, token);
    } catch {
      // ignore (web fallback)
    }
  },
  async clear(): Promise<void> {
    try {
      await SecureStore.deleteItemAsync(KEY);
    } catch {
      // ignore
    }
  },
};
