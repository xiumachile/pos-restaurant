/**
 * EscPosBuilder: Generador de bytes ESC/POS para impresoras térmicas.
 * 
 * ARQUITECTURA OFFLINE-FIRST:
 * - Genera bytes 100% localmente (sin API, sin WebSocket, sin Internet)
 * - API chainable/fluent para construir tickets de forma declarativa
 * - Output: Uint8Array listo para enviar al adapter (MockPrinterAdapter, Tauri, etc.)
 * 
 * COMANDOS ESC/POS SOPORTADOS:
 * - ESC @        (0x1B 0x40)       Initialize
 * - ESC E n      (0x1B 0x45 n)     Bold on/off
 * - ESC a n      (0x1B 0x61 n)     Align left/center/right
 * - ESC d n      (0x1B 0x64 n)     Feed n lines
 * - GS V 0       (0x1D 0x56 0x00)  Cut paper
 * 
 * ANCHO ESTÁNDAR:
 * - 42 caracteres para papel de 80mm (default)
 * - 32 caracteres para papel de 58mm (opcional)
 * 
 * EJEMPLO DE USO:
 * 
 *   const bytes = new EscPosBuilder()
 *     .center()
 *     .bold()
 *     .line("WOK & MESA")
 *     .normal()
 *     .left()
 *     .line("Cuenta #42-1")
 *     .separator()
 *     .leftRight("Subtotal", "$15.000")
 *     .leftRight(`IVA (${IVA_PERCENTAGE}%)`, "$2.850")
 *     .separator()
 *     .bold()
 *     .leftRight("TOTAL", "$17.850")
 *     .emptyLines(3)
 *     .cut()
 *     .build();
 */

type Alignment = "left" | "center" | "right";

// Comandos ESC/POS (bytes)
const ESC = 0x1b;
const GS = 0x1d;

const COMMANDS = {
  INIT: [ESC, 0x40],                  // Initialize printer
  BOLD_ON: [ESC, 0x45, 0x01],         // Bold on
  BOLD_OFF: [ESC, 0x45, 0x00],        // Bold off
  ALIGN_LEFT: [ESC, 0x61, 0x00],      // Left align
  ALIGN_CENTER: [ESC, 0x61, 0x01],    // Center align
  ALIGN_RIGHT: [ESC, 0x61, 0x02],     // Right align
  LINE_FEED: [0x0a],                  // Line feed (newline)
  CUT: [GS, 0x56, 0x00],              // Full cut
  PARTIAL_CUT: [GS, 0x56, 0x01],      // Partial cut
};

export interface EscPosBuilderOptions {
  /** Ancho de caracteres del ticket (default: 42 para 80mm) */
  width?: number;
  /** Si debe agregar comando INIT al inicio (default: true) */
  initialize?: boolean;
  /** Charset para encoding (default: "latin1") */
  charset?: "latin1" | "utf8";
}

import { IVA_PERCENTAGE } from "@/config/tax";

export class EscPosBuilder {
  private buffer: number[] = [];
  private width: number;
  private charset: "latin1" | "utf8";

  constructor(options: EscPosBuilderOptions = {}) {
    this.width = options.width ?? 42;
    this.charset = options.charset ?? "latin1";

    // Initialize printer por defecto
    if (options.initialize !== false) {
      this.buffer.push(...COMMANDS.INIT);
    }
  }

  // ═══════════════════════════════════════════════════════
  // FORMATO DE TEXTO
  // ═══════════════════════════════════════════════════════

  /** Activa negrita */
  bold(): this {
    this.buffer.push(...COMMANDS.BOLD_ON);
    return this;
  }

  /** Desactiva negrita (vuelve a texto normal) */
  normal(): this {
    this.buffer.push(...COMMANDS.BOLD_OFF);
    return this;
  }

  // ═══════════════════════════════════════════════════════
  // ALINEACIÓN
  // ═══════════════════════════════════════════════════════

  /** Alinea texto a la izquierda */
  left(): this {
    this.buffer.push(...COMMANDS.ALIGN_LEFT);
    return this;
  }

  /** Centra texto */
  center(): this {
    this.buffer.push(...COMMANDS.ALIGN_CENTER);
    return this;
  }

  /** Alinea texto a la derecha */
  right(): this {
    this.buffer.push(...COMMANDS.ALIGN_RIGHT);
    return this;
  }

  /** Alineación genérica (izquierda, centro, derecha) */
  align(alignment: Alignment): this {
    const cmd = {
      left: COMMANDS.ALIGN_LEFT,
      center: COMMANDS.ALIGN_CENTER,
      right: COMMANDS.ALIGN_RIGHT,
    }[alignment];
    this.buffer.push(...cmd);
    return this;
  }

  // ═══════════════════════════════════════════════════════
  // LÍNEAS Y TEXTO
  // ═══════════════════════════════════════════════════════

  /**
   * Escribe una línea de texto + salto de línea.
   * Si el texto excede el ancho, lo trunca (no hace wrap automático).
   */
  line(text: string): this {
    const truncated = text.length > this.width 
      ? text.slice(0, this.width - 1) + "~"
      : text;
    
    this.buffer.push(...this.encode(truncated));
    this.buffer.push(...COMMANDS.LINE_FEED);
    return this;
  }

  /**
   * Escribe texto sin salto de línea.
   * Útil para construir líneas compuestas.
   */
  text(text: string): this {
    this.buffer.push(...this.encode(text));
    return this;
  }

  /**
   * Escribe dos textos: uno alineado a la izquierda, otro a la derecha,
   * en la misma línea. Útil para "Item ........ $100".
   * 
   * @param left Texto izquierdo
   * @param right Texto derecho
   * @param separator Carácter de relleno (default: " ")
   */
  leftRight(left: string, right: string, separator: string = " "): this {
    const totalLen = left.length + right.length;
    
    if (totalLen >= this.width) {
      // Si no hay espacio, trunca el izquierdo
      const maxLeft = this.width - right.length - 1;
      const truncatedLeft = maxLeft > 0 
        ? left.slice(0, maxLeft) + "~"
        : left.slice(0, this.width - 1) + "~";
      
      this.buffer.push(...this.encode(truncatedLeft));
      this.buffer.push(...COMMANDS.LINE_FEED);
      this.buffer.push(...this.encode(right));
      this.buffer.push(...COMMANDS.LINE_FEED);
      return this;
    }

    const paddingLen = this.width - totalLen;
    const padding = separator.repeat(paddingLen);
    
    this.buffer.push(...this.encode(left + padding + right));
    this.buffer.push(...COMMANDS.LINE_FEED);
    return this;
  }

  /**
   * Escribe línea separadora (-----).
   */
  separator(char: string = "-"): this {
    const sep = char.repeat(this.width);
    this.buffer.push(...this.encode(sep));
    this.buffer.push(...COMMANDS.LINE_FEED);
    return this;
  }

  /**
   * Escribe línea separadora doble (═════).
   */
  doubleSeparator(): this {
    return this.separator("=");
  }

  // ═══════════════════════════════════════════════════════
  // ESPACIADO
  // ═══════════════════════════════════════════════════════

  /**
   * Agrega n líneas en blanco.
   */
  emptyLines(n: number = 1): this {
    for (let i = 0; i < n; i++) {
      this.buffer.push(...COMMANDS.LINE_FEED);
    }
    return this;
  }

  /**
   * Avanza el papel n líneas (sin escribir nada).
   * Equivalente a emptyLines en la mayoría de impresoras.
   */
  feed(n: number = 1): this {
    this.buffer.push(ESC, 0x64, n);
    return this;
  }

  // ═══════════════════════════════════════════════════════
  // CORTE DE PAPEL
  // ═══════════════════════════════════════════════════════

  /** Corte completo del papel */
  cut(): this {
    // Agregar 4 líneas en blanco antes del corte para asegurar que
    // el texto no quede pegado al borde cortado
    this.emptyLines(4);
    this.buffer.push(...COMMANDS.CUT);
    return this;
  }

  /** Corte parcial (deja punto de unión) */
  partialCut(): this {
    this.emptyLines(4);
    this.buffer.push(...COMMANDS.PARTIAL_CUT);
    return this;
  }

  // ═══════════════════════════════════════════════════════
  // HELPERS
  // ═══════════════════════════════════════════════════════

  /**
   * Convierte string a bytes según el charset configurado.
   */
  private encode(text: string): number[] {
    if (this.charset === "latin1") {
      // Latin1: cada char es 1 byte (0-255)
      const bytes: number[] = [];
      for (let i = 0; i < text.length; i++) {
        const code = text.charCodeAt(i);
        // Reemplazar caracteres fuera de latin1 con "?"
        bytes.push(code <= 255 ? code : 63);
      }
      return bytes;
    }
    
    // UTF-8: encoding completo
    const encoder = new TextEncoder();
    return Array.from(encoder.encode(text));
  }

  /**
   * Devuelve el ancho configurado (útil para formatters externos).
   */
  getWidth(): number {
    return this.width;
  }

  // ═══════════════════════════════════════════════════════
  // BUILD
  // ═══════════════════════════════════════════════════════

  /**
   * Construye el Uint8Array final listo para enviar al adapter.
   * 
   * @returns Uint8Array con todos los bytes ESC/POS
   */
  build(): Uint8Array {
    return new Uint8Array(this.buffer);
  }

  /**
   * Construye y retorna como base64 (útil para persistir en SQLite).
   */
  buildBase64(): string {
    const bytes = this.build();
    let binary = "";
    for (let i = 0; i < bytes.length; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  }

  /**
   * Construye y retorna como string legible (para debugging).
   * 
   * Los bytes imprimibles Latin1 (0x20-0xFF excepto DEL 0x7F) se muestran como caracteres.
   * Los bytes de control (< 0x20 y 0x7F) se muestran como [0xXX].
   * 
   * Esto incluye vocales acentuadas (ó, é, ñ) comunes en tickets en español.
   */
  buildDebug(): string {
    const bytes = this.build();
    const parts: string[] = [];
    
    for (let i = 0; i < bytes.length; i++) {
      const byte = bytes[i];
      // Latin1 imprimible: 0x20-0x7E (ASCII) + 0x80-0xFF (extended)
      // Excluir DEL (0x7F) y caracteres de control (< 0x20)
      if ((byte >= 0x20 && byte <= 0x7E) || byte >= 0x80) {
        parts.push(String.fromCharCode(byte));
      } else {
        parts.push(`[0x${byte.toString(16).padStart(2, "0")}]`);
      }
    }
    
    return parts.join("");
  }

  /**
   * Retorna el tamaño en bytes del ticket (útil para validar).
   */
  size(): number {
    return this.buffer.length;
  }

  /**
   * Reinicia el builder (útil para reutilizar).
   */
  reset(): this {
    this.buffer = [];
    this.buffer.push(...COMMANDS.INIT);
    return this;
  }
}

/**
 * Helper para formatear moneda chilena sin decimales.
 * 
 * @example formatCLP(15000) // "$15.000"
 */
export function formatCLP(amount: number): string {
  return new Intl.NumberFormat("es-CL", {
    style: "currency",
    currency: "CLP",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

/**
 * Helper para formatear fecha/hora local.
 * 
 * @example formatDateTime(new Date()) // "11/09/2026 22:45"
 */
export function formatDateTime(date: Date): string {
  return date.toLocaleString("es-CL", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
}
