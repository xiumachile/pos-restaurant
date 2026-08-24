import type { PrinterConnection } from '../../printJobsApi';

export interface PrinterAdapter {
  print(bytes: Uint8Array, connection: PrinterConnection): Promise<void>;
}

/**
 * Adaptador de impresora mock que loguea los bytes ESC/POS recibidos.
 * Útil para desarrollo y validación del flujo sin hardware real.
 */
export class MockPrinterAdapter implements PrinterAdapter {
  async print(bytes: Uint8Array, connection: PrinterConnection): Promise<void> {
    // Decodificar bytes para extraer contenido legible
    const text = new TextDecoder('latin1').decode(bytes);
    const readableText = text.replace(/[^\x20-\x7E]/g, '.');
    
    console.log('🖨️  [MockPrinterAdapter] Imprimiendo job:');
    console.log('   Connection:', connection);
    console.log(`   Bytes: ${bytes.length} bytes`);
    console.log('   Contenido legible:', readableText);
    
    // Simular tiempo de impresión (200ms)
    await new Promise(resolve => setTimeout(resolve, 200));
    
    console.log('   ✅ Impresión simulada completada');
  }
}
