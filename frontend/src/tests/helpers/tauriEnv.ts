/**
 * Helper para forzar entorno Tauri o Web en tests.
 * 
 * secureStorage.ts usa `isTauri()` que chequea:
 *   "__TAURI_INTERNALS__" in window
 * 
 * Por defecto en jsdom eso es false (modo web fallback).
 * Este helper permite forzar isTauri()=true para probar
 * el path de Tauri Store en tests.
 * 
 * IMPORTANTE: Llamar enableTauri() ANTES de importar secureStorage
 * o resetear el módulo con vi.resetModules() si ya fue importado.
 */

import { vi } from "vitest";

export function enableTauriEnv(): void {
  // Simular que estamos en Tauri
  (window as any).__TAURI_INTERNALS__ = {
    invoke: vi.fn(),
    transformCallback: vi.fn(),
  };
}

export function disableTauriEnv(): void {
  delete (window as any).__TAURI_INTERNALS__;
}

/**
 * Helper para tests que necesitan probar ambos entornos.
 * Uso:
 *   describe.each([
 *     { env: "web", setup: disableTauriEnv },
 *     { env: "tauri", setup: enableTauriEnv },
 *   ])
 */
export const ENVIRONMENTS = [
  { name: "web (fallback localStorage)", setup: disableTauriEnv },
  { name: "tauri (Tauri Store encriptado)", setup: enableTauriEnv },
] as const;
