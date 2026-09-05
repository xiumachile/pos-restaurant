/**
 * Genera un UUIDv4 VÁLIDO de forma determinística desde un seed.
 *
 * Usa Web Crypto API (SHA-256) para generar un hash determinístico
 * y luego lo formatea como UUIDv4 válido.
 *
 * PROPÓSITO:
 * Idempotency-Keys que deben ser:
 * - UUIDv4 válido (el backend lo exige)
 * - Determinístico (mismo seed = mismo UUID, para idempotencia)
 *
 * NO USAR para generar IDs únicos aleatorios.
 * SOLO para idempotencia-keys donde necesitamos el mismo valor en reintentos.
 *
 * @param seed - String arbitrario (ej: `confirm-${orderUuid}`)
 * @returns UUIDv4 válido (formato: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx)
 */
export async function deterministicUuidV4(seed: string): Promise<string> {
  // Codificar seed como UTF-8
  const encoder = new TextEncoder();
  const data = encoder.encode(seed);

  // SHA-256 del seed (determinístico)
  const hashBuffer = await crypto.subtle.digest("SHA-256", data);
  const hashArray = new Uint8Array(hashBuffer);

  // Tomar los primeros 16 bytes (128 bits) del hash
  const bytes = Array.from(hashArray.slice(0, 16));

  // Aplicar reglas de UUIDv4 (RFC 4122):
  // - byte 6: version = 4 (0100 en bits 4-7)
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  // - byte 8: variant = 10xx (bits 6-7 = 10)
  bytes[8] = (bytes[8] & 0x3f) | 0x80;

  // Convertir a formato UUID: 8-4-4-4-12
  const hex = bytes.map(b => b.toString(16).padStart(2, "0")).join("");
  return (
    hex.slice(0, 8) +
    "-" +
    hex.slice(8, 12) +
    "-" +
    hex.slice(12, 16) +
    "-" +
    hex.slice(16, 20) +
    "-" +
    hex.slice(20, 32)
  );
}
