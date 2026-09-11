import { describe, it, expect } from "vitest";
import { EscPosBuilder, formatCLP, formatDateTime } from "@/services/printing/EscPosBuilder";

describe("EscPosBuilder", () => {
  describe("constructor", () => {
    it("inicializa con comando INIT por defecto", () => {
      const builder = new EscPosBuilder();
      const bytes = builder.build();
      
      // INIT = ESC @ = 0x1B 0x40
      expect(bytes[0]).toBe(0x1b);
      expect(bytes[1]).toBe(0x40);
    });

    it("puede omitir INIT si se configura", () => {
      const builder = new EscPosBuilder({ initialize: false });
      const bytes = builder.build();
      
      // Buffer vacío si no se agregó nada
      expect(bytes.length).toBe(0);
    });

    it("usa width 42 por defecto", () => {
      const builder = new EscPosBuilder();
      expect(builder.getWidth()).toBe(42);
    });

    it("acepta width personalizado", () => {
      const builder = new EscPosBuilder({ width: 32 });
      expect(builder.getWidth()).toBe(32);
    });
  });

  describe("bold", () => {
    it("agrega comando ESC E 1", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.bold();
      const bytes = builder.build();
      
      expect(bytes[0]).toBe(0x1b);
      expect(bytes[1]).toBe(0x45);
      expect(bytes[2]).toBe(0x01);
    });

    it("normal() agrega ESC E 0", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.normal();
      const bytes = builder.build();
      
      expect(bytes[0]).toBe(0x1b);
      expect(bytes[1]).toBe(0x45);
      expect(bytes[2]).toBe(0x00);
    });
  });

  describe("alineación", () => {
    it("left() agrega ESC a 0", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.left();
      const bytes = builder.build();
      
      expect(bytes[0]).toBe(0x1b);
      expect(bytes[1]).toBe(0x61);
      expect(bytes[2]).toBe(0x00);
    });

    it("center() agrega ESC a 1", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.center();
      const bytes = builder.build();
      
      expect(bytes[2]).toBe(0x01);
    });

    it("right() agrega ESC a 2", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.right();
      const bytes = builder.build();
      
      expect(bytes[2]).toBe(0x02);
    });

    it("align() acepta string", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.align("center");
      const bytes = builder.build();
      
      expect(bytes[2]).toBe(0x01);
    });
  });

  describe("line()", () => {
    it("escribe texto + line feed", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.line("Hola");
      const bytes = builder.build();
      
      // "Hola" = 4 bytes + 0x0a (line feed)
      expect(bytes.length).toBe(5);
      expect(bytes[4]).toBe(0x0a);
    });

    it("trunca texto si excede el ancho", () => {
      const builder = new EscPosBuilder({ initialize: false, width: 10 });
      const longText = "Este texto es muy largo";
      builder.line(longText);
      
      const debug = builder.buildDebug();
      expect(debug).toContain("~"); // Indicador de truncado
    });

    it("no trunca texto que cabe en el ancho", () => {
      const builder = new EscPosBuilder({ initialize: false, width: 10 });
      builder.line("Hola");
      
      const debug = builder.buildDebug();
      expect(debug).toBe("Hola[0x0a]");
    });
  });

  describe("leftRight()", () => {
    it("rellena con espacios por defecto", () => {
      const builder = new EscPosBuilder({ initialize: false, width: 20 });
      builder.leftRight("Item", "$100");
      
      const debug = builder.buildDebug();
      // "Item" + 12 espacios + "$100" + line feed
      expect(debug).toMatch(/^Item\s+\$100\[0x0a\]$/);
    });

    it("usa separator personalizado", () => {
      const builder = new EscPosBuilder({ initialize: false, width: 20 });
      builder.leftRight("Item", "$100", ".");
      
      const debug = builder.buildDebug();
      expect(debug).toContain("............");
    });

    it("trunca izquierdo si no hay espacio", () => {
      const builder = new EscPosBuilder({ initialize: false, width: 15 });
      builder.leftRight("Texto muy muy largo", "$100");
      
      const debug = builder.buildDebug();
      expect(debug).toContain("~");
    });
  });

  describe("separator()", () => {
    it("genera línea de guiones del ancho configurado", () => {
      const builder = new EscPosBuilder({ initialize: false, width: 10 });
      builder.separator();
      
      const debug = builder.buildDebug();
      expect(debug).toBe("----------[0x0a]");
    });

    it("acepta caracter personalizado", () => {
      const builder = new EscPosBuilder({ initialize: false, width: 5 });
      builder.separator("*");
      
      const debug = builder.buildDebug();
      expect(debug).toBe("*****[0x0a]");
    });

    it("doubleSeparator() usa '='", () => {
      const builder = new EscPosBuilder({ initialize: false, width: 5 });
      builder.doubleSeparator();
      
      const debug = builder.buildDebug();
      expect(debug).toBe("=====[0x0a]");
    });
  });

  describe("emptyLines()", () => {
    it("agrega n line feeds", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.emptyLines(3);
      
      const bytes = builder.build();
      expect(bytes.length).toBe(3);
      expect(bytes[0]).toBe(0x0a);
      expect(bytes[1]).toBe(0x0a);
      expect(bytes[2]).toBe(0x0a);
    });

    it("default es 1 línea", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.emptyLines();
      
      const bytes = builder.build();
      expect(bytes.length).toBe(1);
    });
  });

  describe("cut()", () => {
    it("agrega 4 líneas en blanco + comando de corte", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.cut();
      
      const bytes = builder.build();
      // 4 line feeds + GS V 0 (3 bytes) = 7 bytes
      expect(bytes.length).toBe(7);
      expect(bytes[4]).toBe(0x1d); // GS
      expect(bytes[5]).toBe(0x56); // V
      expect(bytes[6]).toBe(0x00); // Full cut
    });

    it("partialCut() usa comando 0x01", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.partialCut();
      
      const bytes = builder.build();
      expect(bytes[6]).toBe(0x01); // Partial cut
    });
  });

  describe("API chainable", () => {
    it("todos los métodos retornan this", () => {
      const builder = new EscPosBuilder();
      
      const result = builder
        .center()
        .bold()
        .line("Título")
        .normal()
        .left()
        .line("Texto")
        .separator()
        .emptyLines(2)
        .cut();
      
      expect(result).toBe(builder);
    });

    it("construye ticket completo con chain", () => {
      const bytes = new EscPosBuilder({ width: 30 })
        .center()
        .bold()
        .line("WOK & MESA")
        .normal()
        .left()
        .line("Cuenta #42-1")
        .separator()
        .leftRight("Subtotal", "$15.000")
        .leftRight("IVA", "$2.850")
        .separator()
        .bold()
        .leftRight("TOTAL", "$17.850")
        .normal()
        .emptyLines(3)
        .cut()
        .build();
      
      // Debe tener contenido sustancial (no vacío)
      expect(bytes.length).toBeGreaterThan(100);
    });
  });

  describe("buildBase64()", () => {
    it("retorna base64 válido", () => {
      const base64 = new EscPosBuilder()
        .line("Test")
        .buildBase64();
      
      expect(typeof base64).toBe("string");
      // Debe ser decodificable
      expect(() => atob(base64)).not.toThrow();
    });

    it("base64 contiene los bytes correctos", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.line("A");
      
      const base64 = builder.buildBase64();
      const decoded = atob(base64);
      
      expect(decoded.charCodeAt(0)).toBe(65); // 'A'
      expect(decoded.charCodeAt(1)).toBe(0x0a); // line feed
    });
  });

  describe("buildDebug()", () => {
    it("muestra bytes imprimibles como caracteres", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.line("ABC");
      
      const debug = builder.buildDebug();
      expect(debug).toBe("ABC[0x0a]");
    });

    it("muestra bytes no imprimibles como [0xXX]", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.bold();
      
      const debug = builder.buildDebug();
      expect(debug).toBe("[0x1b]E[0x01]"); // 0x45 es 'E' (ASCII imprimible)
    });
  });

  describe("size()", () => {
    it("retorna el tamaño del buffer", () => {
      const builder = new EscPosBuilder({ initialize: false });
      expect(builder.size()).toBe(0);
      
      builder.line("A");
      expect(builder.size()).toBe(2); // 'A' + line feed
    });
  });

  describe("reset()", () => {
    it("reinicia el buffer con INIT", () => {
      const builder = new EscPosBuilder();
      builder.line("Algo").bold().cut();
      
      expect(builder.size()).toBeGreaterThan(10);
      
      builder.reset();
      expect(builder.size()).toBe(2); // Solo INIT
    });
  });

  describe("charset", () => {
    it("latin1 es el default", () => {
      const builder = new EscPosBuilder({ initialize: false });
      builder.line("ñ");
      
      const bytes = builder.build();
      // 'ñ' en latin1 = 0xF1 (241)
      expect(bytes[0]).toBe(241);
    });

    it("reemplaza caracteres no-latin1 con '?'", () => {
      const builder = new EscPosBuilder({ initialize: false, charset: "latin1" });
      builder.line("中文"); // Chino (fuera de latin1)
      
      const bytes = builder.build();
      expect(bytes[0]).toBe(63); // '?'
      expect(bytes[1]).toBe(63); // '?'
    });
  });
});

describe("formatCLP", () => {
  it("formatea sin decimales", () => {
    expect(formatCLP(15000)).toBe("$15.000");
  });

  it("redondea decimales", () => {
    expect(formatCLP(15000.99)).toBe("$15.001");
  });

  it("maneja cero", () => {
    expect(formatCLP(0)).toBe("$0");
  });

  it("maneja números grandes", () => {
    expect(formatCLP(1000000)).toBe("$1.000.000");
  });
});

describe("formatDateTime", () => {
  it("formatea fecha con día/mes/año hora:minuto", () => {
    const date = new Date(2026, 8, 11, 22, 45); // 11/09/2026 22:45
    const formatted = formatDateTime(date);
    
    expect(formatted).toContain("11");
    expect(formatted).toContain("09");
    expect(formatted).toContain("2026");
    expect(formatted).toContain("22");
    expect(formatted).toContain("45");
  });
});
