import { invoke } from "@tauri-apps/api/core";
import type { PrinterAdapter } from "./MockPrinterAdapter";
import type { PrinterConnection } from "@/services/printJobsApi";

/**
 * TauriNetworkPrinterAdapter: Envía bytes ESC/POS a impresoras térmicas de red.
 * 
 * IMPLEMENTACIÓN:
 * - Usa el comando Rust 'print_raw' registrado en src-tauri/src/lib.rs
 * - Envía bytes por TCP al puerto configurado (default 9100)
 * - Timeout de 5 segundos (configurable)
 * 
 * USO:
 *   const adapter = new TauriNetworkPrinterAdapter();
 *   await adapter.print(bytes, { type: "network", ip: "192.168.1.100", port: 9100 });
 * 
 * COMPATIBILIDAD:
 * - Epson TM-T20/T88 (y similares)
 * - Star TSP100/TSP650
 * - Bixolon SRP-350
 * - Xprinter XP-N160II
 * - Cualquier impresora ESC/POS con interface Ethernet
 * 
 * FALLBACK:
 * Si invoke() falla (ej: ejecutando en navegador sin Tauri),
 * lanza error descriptivo. NO cae a MockAdapter automáticamente
 * para evitar impresiones fantasma.
 */
export class TauriNetworkPrinterAdapter implements PrinterAdapter {
  private defaultPort: number;
  private timeoutMs: number;

  constructor(options: { defaultPort?: number; timeoutMs?: number } = {}) {
    this.defaultPort = options.defaultPort ?? 9100;
    this.timeoutMs = options.timeoutMs ?? 5000;
  }

  async print(bytes: Uint8Array, connection: PrinterConnection): Promise<void> {
    // 1. Validar que es conexión de red
    if (connection.type !== "network" && connection.type !== "usb") {
      throw new Error(
        `TauriNetworkPrinterAdapter solo soporta conexiones de red. Recibido: ${connection.type}`
      );
    }

    // 2. Extraer IP y puerto
    const ip = (connection as any).ip || (connection as any).address;
    const port = (connection as any).port ?? this.defaultPort;

    if (!ip) {
      throw new Error(
        "Conexión de red requiere 'ip' o 'address' en PrinterConnection"
      );
    }

    // 3. Convertir bytes a base64 (lo que espera el comando Rust)
    const base64Data = this.bytesToBase64(bytes);

    // 4. Invocar comando Rust
    try {
      const bytesSent = await invoke<number>("print_raw", {
        ip,
        port,
        data: base64Data,
      });

      console.log(
        `[TauriNetworkPrinterAdapter] ✅ ${bytesSent} bytes enviados a ${ip}:${port}`
      );
    } catch (error: any) {
      const message = error?.message || error?.toString() || "Error desconocido";
      console.error(
        `[TauriNetworkPrinterAdapter] ❌ Error al imprimir en ${ip}:${port}:`,
        message
      );
      throw new Error(`Error de impresión: ${message}`);
    }
  }

  /**
   * Convierte Uint8Array a base64 string (estándar, compatible con Rust base64 crate).
   */
  private bytesToBase64(bytes: Uint8Array): string {
    let binary = "";
    for (let i = 0; i < bytes.length; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  }

  /**
   * Ping rápido a una impresora (útil para validar antes de imprimir).
   * Intenta conectar al puerto y cierra inmediatamente.
   */
  async ping(ip: string, port: number = this.defaultPort): Promise<boolean> {
    try {
      // Enviamos 0 bytes solo para verificar conexión
      await invoke<number>("print_raw", { ip, port, data: "" });
      return true;
    } catch {
      return false;
    }
  }
}

/**
 * Instancia singleton para uso global.
 */
export const tauriNetworkPrinterAdapter = new TauriNetworkPrinterAdapter();
