import { API_BASE_URL } from './config';

export type User = {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  company: string | null;
  role: string;
  is_moderator: boolean;
  is_professional: boolean;
};

export type MerchantAccount = {
  id: number;
  label: string;
  currency: string;
};

export type PosStatus = {
  active: boolean;
  reason: string;
  banned: boolean;
  ban_reason: string;
  can_operate: boolean;
  accounts: MerchantAccount[];
};

export type LoginResponse = {
  success: true;
  token: string;
  user: User;
  pos: PosStatus;
};

export type StatusResponse = {
  success: true;
  user: User;
  pos: PosStatus;
};

export type TransactionResponse = {
  success: true;
  message: string;
  transaction: {
    type: 'debit' | 'credit';
    amount: number;
    currency: string;
    card: string;
    label: string;
    merchant_tx_id: number | null;
    customer_tx_id: number | null;
    at: string;
  };
};

export type ApiError = { success: false; message: string };

export class ApiException extends Error {
  status: number;
  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

async function request<T>(
  path: string,
  options: { method?: string; body?: unknown; token?: string | null } = {}
): Promise<T> {
  const { method = 'GET', body, token } = options;
  const headers: Record<string, string> = {
    Accept: 'application/json',
  };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers['Authorization'] = `Bearer ${token}`;

  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (e: any) {
    throw new ApiException(
      `Impossible de joindre le serveur (${API_BASE_URL}). ${e?.message ?? ''}`,
      0
    );
  }

  const text = await response.text();
  let json: any = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    throw new ApiException(`Réponse non JSON (HTTP ${response.status}).`, response.status);
  }

  if (!response.ok || (json && json.success === false)) {
    const msg = (json && json.message) || `Erreur HTTP ${response.status}`;
    throw new ApiException(msg, response.status);
  }
  return json as T;
}

export const api = {
  login: (email: string, password: string) =>
    request<LoginResponse>('/api/v1/mobile/login', {
      method: 'POST',
      body: { email, password },
    }),
  status: (token: string) =>
    request<StatusResponse>('/api/v1/mobile/status', { token }),
  transaction: (
    token: string,
    payload: {
      type: 'debit' | 'credit';
      card_number: string;
      label: string;
      amount: number;
      account_id?: number;
    }
  ) =>
    request<TransactionResponse>('/api/v1/mobile/transaction', {
      method: 'POST',
      token,
      body: payload,
    }),
};
