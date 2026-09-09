import { v4 as uuidv4 } from "uuid";

const TERMINAL_ID_KEY = "pos_terminal_id";

/**
 * Retorna un terminal_id local estable para este dispositivo.
 *
 * P0 Caja Offline:
 * La caja debe quedar ligada inequívocamente a:
 * company + branch + terminal + user/cashier.
 *
 * Como el frontend aún no tiene gestión explícita de terminales,
 * generamos un UUID persistente en localStorage por instalación/dispositivo.
 */
export function getTerminalId(): string {
  try {
    if (typeof localStorage === "undefined") {
      return "terminal-test";
    }

    let terminalId = localStorage.getItem(TERMINAL_ID_KEY);

    if (!terminalId) {
      terminalId = uuidv4();
      localStorage.setItem(TERMINAL_ID_KEY, terminalId);
      console.log(`[terminalIdentity] Nuevo terminal_id generado: ${terminalId}`);
    }

    return terminalId;
  } catch {
    return "terminal-test";
  }
}

/**
 * Solo para tests/debug.
 */
export function resetTerminalIdForTests(): void {
  try {
    localStorage.removeItem(TERMINAL_ID_KEY);
  } catch {
    // noop
  }
}
