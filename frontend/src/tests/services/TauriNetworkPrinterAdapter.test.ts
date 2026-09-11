import { describe, it, expect, beforeEach, vi } from "vitest";
import { TauriNetworkPrinterAdapter } from "@/services/printing/adapters/TauriNetworkPrinterAdapter";

// Mock de invoke de Tauri
vi.mock("@tauri-apps/api/core", () => ({
  invoke: vi.fn(),
}));

import { invoke } from "@tauri-apps/api/core";

const mockInvoke = vi.mocked(invoke);

// Tipo local para los args del comando print_raw
interface PrintRawArgs {
  ip: string;
  port: number;
  data: string;
}

// Helper para extraer args de la última llamada
function getLastArgs(): PrintRawArgs {
  return mockInvoke.mock.calls[mockInvoke.mock.calls.length - 1][1] as unknown as PrintRawArgs;
}

describe("TauriNetworkPrinterAdapter", () => {
  let adapter: TauriNetworkPrinterAdapter;

  beforeEach(() => {
    adapter = new TauriNetworkPrinterAdapter();
    vi.clearAllMocks();
    mockInvoke.mockResolvedValue(100);
  });

  describe("print()", () => {
    it("invoca comando print_raw con parámetros correctos", async () => {
      const bytes = new Uint8Array([0x1b, 0x40, 0x41, 0x42, 0x43]);

      await adapter.print(bytes, {
        type: "tcp",
        host: "192.168.1.100",
        port: 9100,
      });

      // El adapter mapea connection.host → parámetro 'ip' del comando Rust
      expect(mockInvoke).toHaveBeenCalledWith("print_raw", {
        ip: "192.168.1.100",
        port: 9100,
        data: expect.any(String),
      });
    });

    it("convierte bytes a base64 correctamente", async () => {
      // "ABC" en ASCII = [65, 66, 67]
      // Base64 de "ABC" = "QUJD"
      const bytes = new Uint8Array([65, 66, 67]);

      await adapter.print(bytes, {
        type: "tcp",
        host: "192.168.1.100",
        port: 9100,
      });

      expect(getLastArgs().data).toBe("QUJD");
    });

    it("usa puerto por defecto 9100 si no se especifica", async () => {
      const bytes = new Uint8Array([1, 2, 3]);

      await adapter.print(bytes, {
        type: "tcp",
        host: "192.168.1.100",
      });

      expect(getLastArgs().port).toBe(9100);
    });

    it("falla si connection no tiene host", async () => {
      const bytes = new Uint8Array([1, 2, 3]);

      await expect(
        adapter.print(bytes, { type: "tcp" })
      ).rejects.toThrow("requiere 'host'");
    });

    it("falla si tipo de conexión no es tcp", async () => {
      const bytes = new Uint8Array([1, 2, 3]);

      await expect(
        adapter.print(bytes, { type: "bluetooth" } as any)
      ).rejects.toThrow("solo soporta conexiones TCP");
    });

    it("propaga errores del comando Rust", async () => {
      mockInvoke.mockRejectedValue(new Error("Timeout conectando"));
      const bytes = new Uint8Array([1, 2, 3]);

      await expect(
        adapter.print(bytes, {
          type: "tcp",
          host: "192.168.1.100",
          port: 9100,
        })
      ).rejects.toThrow("Error de impresión: Timeout conectando");
    });

    it("usa host para la dirección IP", async () => {
      const bytes = new Uint8Array([1, 2, 3]);

      await adapter.print(bytes, {
        type: "tcp",
        host: "192.168.1.200",
        port: 9100,
      });

      expect(getLastArgs().ip).toBe("192.168.1.200");
    });
  });

  describe("constructor options", () => {
    it("acepta defaultPort personalizado", async () => {
      const customAdapter = new TauriNetworkPrinterAdapter({ defaultPort: 9200 });
      const bytes = new Uint8Array([1, 2, 3]);

      await customAdapter.print(bytes, {
        type: "tcp",
        host: "192.168.1.100",
      });

      expect(getLastArgs().port).toBe(9200);
    });
  });

  describe("ping()", () => {
    it("retorna true si invoke no falla", async () => {
      mockInvoke.mockResolvedValue(0);

      const result = await adapter.ping("192.168.1.100", 9100);
      expect(result).toBe(true);
    });

    it("retorna false si invoke falla", async () => {
      mockInvoke.mockRejectedValue(new Error("Connection refused"));

      const result = await adapter.ping("192.168.1.100", 9100);
      expect(result).toBe(false);
    });
  });

  describe("bytesToBase64", () => {
    it("maneja bytes ESC/POS con valores altos (0x80-0xFF)", async () => {
      // ñ = 0xF1 (241) en Latin1
      const bytes = new Uint8Array([0x1b, 0x40, 0xf1, 0xf3]);

      await adapter.print(bytes, {
        type: "tcp",
        host: "192.168.1.100",
        port: 9100,
      });

      const base64 = getLastArgs().data;

      // Debe ser decodificable
      expect(() => atob(base64)).not.toThrow();

      // Al decodificar debe recuperar los bytes originales
      const decoded = atob(base64);
      expect(decoded.charCodeAt(0)).toBe(0x1b);
      expect(decoded.charCodeAt(1)).toBe(0x40);
      expect(decoded.charCodeAt(2)).toBe(0xf1);
      expect(decoded.charCodeAt(3)).toBe(0xf3);
    });
  });
});
