/**
 * Parser robusto para dividir SQL en statements individuales.
 * Maneja comentarios, strings y casos edge correctamente.
 */
export function parseSqlStatements(sql: string): string[] {
  const statements: string[] = [];
  let current = "";
  let inString = false;
  let stringChar = "";
  let inLineComment = false;
  let inBlockComment = false;

  for (let i = 0; i < sql.length; i++) {
    const char = sql[i];
    const nextChar = sql[i + 1];

    // Manejo de comentarios de línea (--)
    if (!inString && !inBlockComment && char === "-" && nextChar === "-") {
      inLineComment = true;
      i++; // saltar el segundo '-'
      continue;
    }

    // Fin de comentario de línea
    if (inLineComment) {
      if (char === "\n") {
        inLineComment = false;
        current += char;
      }
      continue;
    }

    // Inicio de comentario de bloque (/* */)
    if (!inString && !inLineComment && char === "/" && nextChar === "*") {
      inBlockComment = true;
      i++; // saltar '*'
      continue;
    }

    // Fin de comentario de bloque
    if (inBlockComment) {
      if (char === "*" && nextChar === "/") {
        inBlockComment = false;
        i++; // saltar '/'
      }
      continue;
    }

    // Manejo de strings (comillas simples o dobles)
    if (!inString && (char === "'" || char === '"')) {
      inString = true;
      stringChar = char;
      current += char;
      continue;
    }

    // Fin de string
    if (inString) {
      current += char;
      if (char === stringChar && sql[i - 1] !== "\\") {
        inString = false;
      }
      continue;
    }

    // Semicolon fuera de string/comentario = fin de statement
    if (char === ";") {
      const trimmed = current.trim();
      if (trimmed.length > 0) {
        statements.push(trimmed);
      }
      current = "";
      continue;
    }

    // Caracter normal
    current += char;
  }

  // Último statement sin semicolon
  const lastTrimmed = current.trim();
  if (lastTrimmed.length > 0) {
    statements.push(lastTrimmed);
  }

  return statements;
}
