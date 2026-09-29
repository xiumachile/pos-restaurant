import apiClient from "./apiClient";
import type { User } from "@/types/auth";

export interface CreateUserRequest {
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'waiter' | 'cashier' | 'kitchen';
  pin?: string;
}

export interface UpdateUserRequest {
  name?: string;
  email?: string;
  role?: 'admin' | 'manager' | 'waiter' | 'cashier' | 'kitchen';
  pin?: string;
}

export const userService = {
  async getUsers(): Promise<User[]> {
    const response = await apiClient.get<User[]>("/users");
    return response.data;
  },

  async createUser(data: CreateUserRequest): Promise<User> {
    const response = await apiClient.post<User>("/users", data);
    return response.data;
  },

  async updateUser(uuid: string, data: UpdateUserRequest): Promise<User> {
    const response = await apiClient.put<User>(`/users/${uuid}`, data);
    return response.data;
  },

  async deleteUser(uuid: string): Promise<void> {
    await apiClient.delete(`/users/${uuid}`);
  },
};
