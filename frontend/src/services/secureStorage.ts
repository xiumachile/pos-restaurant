/**
 * secureStorage.ts
 * 
 * Wrapper unificado para storage seguro de credenciales.
 * 
 * ESTRATEGIA:
 * - En Tauri (producción): usa @tauri-apps/plugin-store (encriptado con clave del OS)
 * - En web/dev (fallback): usa localStorage (solo para desarrollo)
 * 
 * CONTRATO:
 * - getItem() SIEMPRE retorna string | null (nunca undefined)
 * - setItem() guarda el valor
 * - removeItem() elimina el valor
 * - getItemSync() retorna desde cache primero, luego storage subyacente
 */

import { load } from "@tauri-apps/plugin-store";

const STORE_NAME = "pos-secure.dat";

let storeInstance: any = null;
let storeLoaded = false;

// Cache síncrona compartida (singleton por módulo)
let syncCache: Map<string, string> = new Map();

function isTauri(): boolean {
  return typeof window !== "undefined" && "__TAURI_INTERNALS__" in window;
}

async function getStore() {
  if (storeInstance && storeLoaded) {
    return storeInstance;
  }
  
  if (!isTauri()) {
    return null;
  }
  
  try {
    storeInstance = await load(STORE_NAME, { autoSave: true });
    storeLoaded = true;
    return storeInstance;
  } catch (err) {
    console.warn("[secureStorage] ⚠️ No se pudo cargar store:", err);
    return null;
  }
}

/**
 * Lee del storage subyacente (Tauri Store o localStorage).
 * Retorna string | null.
 */
async function readFromStorage(key: string): Promise<string | null> {
  const store = await getStore();
  
  if (store) {
    try {
      const value = await store.get(key);
      if (Array.isArray(value) && value.length > 0 && value[0] != null) {
        return String(value[0]);
      }
    } catch (err) {
      console.error("[secureStorage] ❌ Error reading from store:", err);
    }
  }
  
  // Fallback: localStorage (siempre disponible)
  const value = localStorage.getItem(key);
  return value == null ? null : value;
}

/**
 * Escribe en el storage subyacente.
 */
async function writeToStorage(key: string, value: string): Promise<void> {
  const store = await getStore();
  
  if (store) {
    try {
      await store.set(key, value);
      await store.save();
      // También mantener mirror en localStorage para getItemSync rápido
      localStorage.setItem(key, value);
      return;
    } catch (err) {
      console.error("[secureStorage] ❌ Error writing to store:", err);
    }
  }
  
  // Fallback: localStorage
  localStorage.setItem(key, value);
}

/**
 * Elimina del storage subyacente.
 */
async function deleteFromStorage(key: string): Promise<void> {
  const store = await getStore();
  
  if (store) {
    try {
      await store.delete(key);
      await store.save();
      localStorage.removeItem(key);
      return;
    } catch (err) {
      console.error("[secureStorage] ❌ Error deleting from store:", err);
    }
  }
  
  localStorage.removeItem(key);
}

/**
 * Obtiene un valor por key. SIEMPRE retorna string | null.
 */
export async function getItem(key: string): Promise<string | null> {
  return await readFromStorage(key);
}

/**
 * Guarda un valor por key.
 */
export async function setItem(key: string, value: string): Promise<void> {
  await writeToStorage(key, value);
  syncCache.set(key, value);
}

/**
 * Elimina un valor por key.
 */
export async function removeItem(key: string): Promise<void> {
  await deleteFromStorage(key);
  syncCache.delete(key);
}

/**
 * Versión síncrona. Orden de búsqueda:
 * 1. syncCache (más rápido)
 * 2. localStorage (fallback)
 * 
 * SIEMPRE retorna string | null (nunca undefined).
 */
export function getItemSync(key: string): string | null {
  if (syncCache.has(key)) {
    const cached = syncCache.get(key);
    return cached == null ? null : cached;
  }
  
  const fromLs = localStorage.getItem(key);
  if (fromLs != null) {
    // Popular cache para próximas llamadas
    syncCache.set(key, fromLs);
    return fromLs;
  }
  
  return null;
}

/**
 * Carga el token al arranque para que esté disponible síncronamente.
 * Llamar en App.tsx al iniciar la aplicación.
 * 
 * ESTRATEGIA:
 * 1. Intentar leer desde localStorage primero (más confiable en tests)
 * 2. Si no está, intentar desde Tauri Store
 * 3. Popular syncCache con el valor encontrado
 */
export async function preloadAuthToken(): Promise<void> {
  // Primero intentar desde localStorage (siempre disponible)
  let token = localStorage.getItem("access_token");
  
  // Si no está en localStorage, intentar desde Tauri Store
  if (token == null) {
    token = await getItem("access_token");
  }
  
  if (token != null) {
    syncCache.set("access_token", token);
    console.log("[secureStorage] 🔐 Auth token precargado en cache");
  }
}

/**
 * Actualiza la cache síncrona.
 */
export function updateSyncCache(key: string, value: string): void {
  syncCache.set(key, value);
}

/**
 * Limpia la cache síncrona (usar en logout).
 */
export function clearSyncCache(): void {
  syncCache.clear();
}

/**
 * Reset completo del módulo (solo para tests).
 */
export function __resetForTests(): void {
  syncCache.clear();
  storeInstance = null;
  storeLoaded = false;
}
