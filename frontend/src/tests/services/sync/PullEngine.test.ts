import { describe, it, expect } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

/**
 * P1-008: Validación de que PullEngine usa el número correcto de parámetros
 * en las consultas INSERT OR REPLACE para local_tables.
 * 
 * El bug original tenía 9 columnas pero solo 7 placeholders (?), lo que
 * causaba un error de SQLite "number of parameters mismatch".
 */
describe('P1-008: PullEngine INSERT de mesas con 9 parámetros', () => {
  it('debería tener exactamente 9 placeholders en todos los INSERT de local_tables', () => {
    const pullEnginePath = path.resolve(__dirname, '../../../services/sync/PullEngine.ts');
    const content = fs.readFileSync(pullEnginePath, 'utf-8');

    // Buscar todas las ocurrencias de INSERT en local_tables
    const insertMatches = content.match(/INSERT OR REPLACE INTO local_tables\s+\([^)]+\)\s+VALUES\s+\([^)]+\)/g);
    
    expect(insertMatches).not.toBeNull();
    expect(insertMatches!.length).toBeGreaterThan(0);
    
    insertMatches!.forEach((match, index) => {
      // Contar los signos de interrogación en la cláusula VALUES
      const questionMarks = (match.match(/\?/g) || []).length;
      
      // Debe haber exactamente 9 placeholders
      expect(questionMarks).toBe(9);
      
      // Verificar que las 9 columnas están declaradas
      expect(match).toContain('uuid');
      expect(match).toContain('table_number');
      expect(match).toContain('area_name');
      expect(match).toContain('capacity');
      expect(match).toContain('status');
      expect(match).toContain('current_order_uuid');
      expect(match).toContain('last_updated');
      expect(match).toContain('company_id');
      expect(match).toContain('branch_id');
    });
  });
});
