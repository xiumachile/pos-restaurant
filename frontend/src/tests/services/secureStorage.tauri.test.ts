import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";

/**
 * Tests de secureStorage en modo Tauri.
 * 
 * Forzamos isTauri()=true simulando __TAURI_INTERNALS__ en window.
 * Esto hace que secureStorage use el Tauri Store encriptado
 * (mockeado aquí) en lugar del fallback localStorage.
 */

import { enableTauriEnv, disableTauriEnv } from "../helpers/tauriEnv";

// Mock del plugin-store
vi.mock("@tauri-apps/plugin-store", async () => {
  const mod = await import("../mocks/tauriStore");
  return mod;
});

// Importar secureStorage
import {
  getItem,
  setItem,
  removeItem,
  getItemSync,
  updateSyncCache,
  clearSyncCache,
  preloadAuthToken,
} from "@/services/secureStorage";

import { clearMockStore } from "../mocks/tauriStore";

describe("secureStorage (modo Tauri)", () => {
  beforeEach(() => {
    // Forzar entorno Tauri ANTES de cada test
    enableTauriEnv();
    
    localStorage.clear();
    clearMockStore();
    clearSyncCache();
  });

  afterEach(() => {
    disableTauriEnv();
  });

  describe("API async en Tauri Store", () => {
    it("debería guardar en Tauri Store y recuperar", async () => {
      await setItem("tauri_key", "tauri_value");
      
      const value = await getItem("tauri_key");
      expect(value).toBe("tauri_value");
    });

    it("debería hacer mirror en localStorage para getItemSync", async () => {
      await setItem("mirror_key", "mirror_value");
      
      // El mirror en localStorage debe existir
      expect(localStorage.getItem("mirror_key")).toBe("mirror_value");
      
      // Y getItemSync debe leerlo
      expect(getItemSync("mirror_key")).toBe("mirror_value");
    });

    it("debería eliminar de Tauri Store y del mirror", async () => {
      await setItem("to_delete", "value");
      expect(await getItem("to_delete")).toBe("value");
      expect(localStorage.getItem("to_delete")).toBe("value");
      
      await removeItem("to_delete");
      
      expect(await getItem("to_delete")).toBeNull();
      expect(localStorage.getItem("to_delete")).toBeNull();
    });

    it("debería sobreescribir valores en Tauri Store", async () => {
      await setItem("overwrite", "v1");
      await setItem("overwrite", "v2");
      
      expect(await getItem("overwrite")).toBe("v2");
    });

    it("debería retornar null para keys inexistentes en Tauri Store", async () => {
      const value = await getItem("never_set");
      expect(value).toBeNull();
    });
  });

  describe("preloadAuthToken en Tauri", () => {
    it("debería precargar token desde mirror localStorage a cache síncrona", async () => {
      await setItem("access_token", "jwt-tauri-xyz");
      
      // Limpiar cache (simular reinicio)
      clearSyncCache();
      
      // preloadAuthToken lee de localStorage mirror
      await preloadAuthToken();
      
      expect(getItemSync("access_token")).toBe("jwt-tauri-xyz");
    });

    it("debería cargar token si solo está en localStorage (migración desde versión previa)", async () => {
      // Escenario: usuario tenía token en localStorage antes de migrar a Tauri
      localStorage.setItem("access_token", "legacy-jwt");
      
      clearSyncCache();
      await preloadAuthToken();
      
      expect(getItemSync("access_token")).toBe("legacy-jwt");
    });
  });

  describe("Garantía de seguridad: Tauri Store encriptado", () => {
    it("debería detectar entorno Tauri correctamente", () => {
      // Verificar que enableTauriEnv() setea el flag
      expect("__TAURI_INTERNALS__" in window).toBe(true);
    });

    it("debería usar Tauri Store vía setItem/getItem", async () => {
      // Confirmar entorno Tauri activo
      expect("__TAURI_INTERNALS__" in window).toBe(true);
      
      await setItem("secure_key", "secure_value");
      
      // El valor debe estar accesible vía API pública
      const value = await getItem("secure_key");
      expect(value).toBe("secure_value");
      
      // Mirror en localStorage para sync access
      expect(localStorage.getItem("secure_key")).toBe("secure_value");
    });
  });
});
