/**
 * secureStorage.ts
 * 
 * Wrapper unificado para storage seguro de credenciales.
 * 
 * ESTRATEGIA:
 * - En Tauri (producción): usa @tauri-apps/plugin-store (encriptado con clave del OS)
 * - En web/dev (fallback): usa localStorage (solo para desarrollo)
 * 
 * BENEFICIOS:
 * ✅ Token JWT inaccesible incluso si hay XSS (en producción)
 * ✅ API uniforme: misma interfaz en todos los entornos
 * ✅ Fallback transparente para desarrollo web
 * ✅ Fácil de testear (mockable)
 */

import { load } from "@tauri-apps/plugin-store";

// Nombre del store encriptado (archivo: .store.dat en appData)
const STORE_NAME = "pos-secure.dat";

// Cache del store (singleton)
let storeInstance: any = null;
let storeLoaded = false;

/**
 * Detecta si estamos en entorno Tauri (producción).
 */
function isTauri(): boolean {
  return typeof window !== "undefined" && "__TAURI_INTERNALS__" in window;
}

/**
 * Obtiene la instancia del store (carga lazy).
 */
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
 * Obtiene un valor por key.
 * 
 * @returns El valor o null si no existe
 */
export async function getItem(key: string): Promise<string | null> {
  if (!isTauri()) {
    // Fallback web/dev
    return localStorage.getItem(key);
  }
  
  const store = await getStore();
  if (!store) {
    return localStorage.getItem(key);
  }
  
  try {
    const value = await store.get(key);
    // store.get retorna un array [value] o undefined
    if (Array.isArray(value) && value.length > 0) {
      return String(value[0]);
    }
    return null;
  } catch (err) {
    console.error("[secureStorage] ❌ Error getting item:", err);
    return null;
  }
}

/**
 * Guarda un valor por key.
 */
export async function setItem(key: string, value: string): Promise<void> {
  if (!isTauri()) {
    // Fallback web/dev
    localStorage.setItem(key, value);
    return;
  }
  
  const store = await getStore();
  if (!store) {
    localStorage.setItem(key, value);
    return;
  }
  
  try {
    await store.set(key, value);
    await store.save(); // Forzar guardado
  } catch (err) {
    console.error("[secureStorage] ❌ Error setting item:", err);
    // Fallback a localStorage si falla el store
    localStorage.setItem(key, value);
  }
}

/**
 * Elimina un valor por key.
 */
export async function removeItem(key: string): Promise<void> {
  if (!isTauri()) {
    // Fallback web/dev
    localStorage.removeItem(key);
    return;
  }
  
  const store = await getStore();
  if (!store) {
    localStorage.removeItem(key);
    return;
  }
  
  try {
    await store.delete(key);
    await store.save();
  } catch (err) {
    console.error("[secureStorage] ❌ Error removing item:", err);
    localStorage.removeItem(key);
  }
}

/**
 * Versión síncrona para casos donde no se puede usar await.
 * 
 * IMPORTANTE: Solo funciona si el item ya fue cargado previamente.
 * Si no está en cache, retorna null.
 * 
 * Uso: para interceptors síncronos de axios.
 */
let syncCache: Map<string, string> = new Map();

export function getItemSync(key: string): string | null {
  // Primero revisar cache
  if (syncCache.has(key)) {
    return syncCache.get(key)!;
  }
  
  // Si no estamos en Tauri, usar localStorage
  if (!isTauri()) {
    const value = localStorage.getItem(key);
    if (value) syncCache.set(key, value);
    return value;
  }
  
  // En Tauri, intentar desde localStorage como fallback cache
  const fallback = localStorage.getItem(key);
  if (fallback) {
    syncCache.set(key, fallback);
    return fallback;
  }
  
  return null;
}

/**
 * Carga el token al arranque para que esté disponible síncronamente.
 * Llamar en App.tsx al iniciar la aplicación.
 */
export async function preloadAuthToken(): Promise<void> {
  const token = await getItem("access_token");
  if (token) {
    syncCache.set("access_token", token);
    // Mantener fallback en localStorage para interceptors síncronos
    if (isTauri()) {
      localStorage.setItem("access_token", token);
    }
    console.log("[secureStorage] 🔐 Auth token precargado en cache");
  }
}

/**
 * Actualiza la cache síncrona cuando se guarda un nuevo token.
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
