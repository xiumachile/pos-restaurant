export interface User {
  id: number;
  uuid: string;
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'waiter' | 'cashier' | 'kitchen';
  company_id: number;
  branch_id: number;
  company?: {
    id: number;
    uuid: string;  // ← Agregado para capabilities
    trade_name: string;
  };
  branch?: {
    id: number;
    name: string;
    code: string;
  };
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginPosRequest {
  branch_id: number;
  pin: string;
}

export interface LoginResponse {
  access_token: string;
  token_type: string;
  expires_in: number;
  user: User;
}

export interface ApiError {
  message: string;
  error?: string;
  errors?: Record<string, string[]>;
}
