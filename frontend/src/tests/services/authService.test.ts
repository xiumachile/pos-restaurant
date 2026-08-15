import { describe, it, expect, beforeEach, vi } from "vitest";
import { authService } from "@/services/authService";
import apiClient from "@/services/apiClient";

vi.mock("@/services/apiClient", () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
  },
}));

describe("authService", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const mockResponse = {
    data: {
      access_token: "test-token",
      token_type: "bearer",
      expires_in: 3600,
      user: {
        id: 1,
        uuid: "test-uuid",
        name: "Test User",
        email: "test@example.com",
        role: "admin",
        company_id: 1,
        branch_id: 1,
      },
    },
  };

  it("debería hacer login con email/password correctamente", async () => {
    vi.mocked(apiClient.post).mockResolvedValue(mockResponse);

    const result = await authService.login({
      email: "test@example.com",
      password: "password123",
    });

    expect(apiClient.post).toHaveBeenCalledWith("/auth/login", {
      email: "test@example.com",
      password: "password123",
    });
    expect(result).toEqual(mockResponse.data);
  });

  it("debería hacer login con PIN correctamente", async () => {
    vi.mocked(apiClient.post).mockResolvedValue(mockResponse);

    const result = await authService.loginWithPin({
      branch_id: 1,
      pin: "1234",
    });

    expect(apiClient.post).toHaveBeenCalledWith("/auth/login/pos", {
      branch_id: 1,
      pin: "1234",
    });
    expect(result).toEqual(mockResponse.data);
  });

  it("debería manejar logout sin errores", async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: {} });

    await authService.logout();

    expect(apiClient.post).toHaveBeenCalledWith("/auth/logout");
  });

  it("debería ignorar errores en logout", async () => {
    vi.mocked(apiClient.post).mockRejectedValue(new Error("Network error"));

    // No debería lanzar error
    await expect(authService.logout()).resolves.toBeUndefined();
  });
});
