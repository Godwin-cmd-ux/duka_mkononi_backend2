import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_BASE_URL } from '../constants/api';
import { fetchWithTimeout } from './network';

/**
 * Shared client for the Huduma (services) API.
 *
 * The two screens (admin service management and seller service sales) need
 * exactly the same shapes, so the request/typing logic lives here once. All
 * business scoping and money maths happen on the server; these helpers only
 * carry the request and shape the response for display.
 */

export interface ServiceConsumable {
  id?: string;
  product_id: string;
  product_name: string | null;
  required_quantity: number;
  buying_price: number | null;
  selling_price?: number | null;
  stock: number | null;
  unit?: string | null;
  is_active?: boolean;
  line_cost?: number;
}

export interface Service {
  id: string;
  business_id?: string;
  name: string;
  description?: string | null;
  status?: string;
  consumables: ServiceConsumable[];
  consumables_count?: number;
  estimated_cost?: number;
}

export interface HudumaResult<T> {
  ok: boolean;
  status: number;
  data: T | null;
  network?: boolean;
}

export async function authToken(): Promise<string> {
  return (await AsyncStorage.getItem('userToken')) || '';
}

export async function hudumaApi<T = any>(path: string, options: RequestInit = {}): Promise<HudumaResult<T>> {
  const token = await authToken();

  try {
    const response = await fetchWithTimeout(
      `${API_BASE_URL}${path}`,
      {
        ...options,
        headers: {
          Authorization: `Bearer ${token}`,
          ...(options.headers || {}),
        },
      },
      20000
    );

    let data: any = null;
    try {
      data = await response.json();
    } catch (e) {
      data = null;
    }

    return { ok: response.ok, status: response.status, data };
  } catch (e) {
    return { ok: false, status: 0, data: null, network: true };
  }
}

export async function fetchServices(): Promise<Service[]> {
  const response = await hudumaApi<Service[]>('/api/services');
  return response.ok && Array.isArray(response.data) ? response.data : [];
}

export async function fetchBusinessProducts(): Promise<any[]> {
  const response = await hudumaApi<any[]>('/api/business/my/all-products');
  return response.ok && Array.isArray(response.data) ? response.data : [];
}

export function money(value: any): string {
  const amount = Number(value || 0);
  return `TZS ${(Number.isFinite(amount) ? amount : 0).toLocaleString('en-TZ', {
    maximumFractionDigits: 0,
  })}`;
}

export function errorMessage(result: HudumaResult<any>, fallback: string): string {
  if (result.network) {
    return 'Mtandao haupatikani. Jaribu tena.';
  }
  return (result.data && (result.data as any).error) || fallback;
}
