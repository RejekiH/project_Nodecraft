import { STORAGE_KEY, API_BASE_URL } from '../utils/constants';

/**
 * Base fetch wrapper with auth header.
 */
export async function apiFetch<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const token = localStorage.getItem(STORAGE_KEY);
  
  const headers = new Headers(options.headers || {});
  headers.set('Accept', 'application/json');
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }
  
  // Conditionally set Content-Type
  if (!(options.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  const data = await response.json();

  if (!response.ok) {
    const error = new Error(data.message || 'Terjadi kesalahan pada server');
    (error as any).status = response.status;
    (error as any).errors = data.errors;
    throw error;
  }

  return data as T;
}

export const api = {
  get: <T>(endpoint: string, options?: RequestInit) => 
    apiFetch<T>(endpoint, { ...options, method: 'GET' }),
  
  post: <T>(endpoint: string, body?: any, options?: RequestInit) => 
    apiFetch<T>(endpoint, { 
      ...options, 
      method: 'POST', 
      body: body ? JSON.stringify(body) : undefined 
    }),
  
  put: <T>(endpoint: string, body?: any, options?: RequestInit) => 
    apiFetch<T>(endpoint, { 
      ...options, 
      method: 'PUT', 
      body: body ? JSON.stringify(body) : undefined 
    }),
  
  delete: <T>(endpoint: string, options?: RequestInit) => 
    apiFetch<T>(endpoint, { ...options, method: 'DELETE' }),
};
