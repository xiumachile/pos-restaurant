import apiClient from "./apiClient";

export interface PrinterConnection {
  type: 'tcp' | 'usb' | 'bluetooth' | 'serial';
  host?: string;
  port?: number;
  device_path?: string;
}

export interface PrintJob {
  uuid: string;
  job_type: 'kitchen_command' | 'bar_command' | 'receipt';
  printer_uuid: string;
  printer_name: string;
  printer_connection: PrinterConnection;
  order_uuid?: string;
  order_number?: string;
  status: 'pending' | 'printing' | 'completed' | 'failed';
  claimed_by?: string;
  claimed_at?: string;
  attempts: number;
  max_attempts: number;
  error_message?: string;
  bytes_size: number;
  escpos_base64?: string; // Solo si se pide con include_bytes=true
  created_at: string;
}

export const printJobsApi = {
  /**
   * Lista print jobs pendientes de la sucursal del usuario.
   */
  async listPending(limit: number = 20): Promise<PrintJob[]> {
    const response = await apiClient.get<{ data: PrintJob[] }>(
      '/print-jobs',
      {
        params: { status: 'pending', limit },
      }
    );
    return response.data.data;
  },

  /**
   * Obtiene detalle de un job incluyendo bytes ESC/POS en base64.
   */
  async getWithBytes(uuid: string): Promise<PrintJob> {
    const response = await apiClient.get<{ data: PrintJob }>(
      `/print-jobs/${uuid}`,
      {
        params: { include_bytes: 'true' },
      }
    );
    return response.data.data;
  },

  /**
   * Reclama un job para imprimirlo localmente.
   * @param clientId Identificador único del dispositivo Tauri
   */
  async claim(uuid: string, clientId: string): Promise<PrintJob> {
    const response = await apiClient.post<{ job: PrintJob }>(
      `/print-jobs/${uuid}/claim`,
      { client_id: clientId }
    );
    return response.data.job;
  },

  /**
   * Marca un job como completado exitosamente.
   */
  async complete(uuid: string): Promise<void> {
    await apiClient.post(`/print-jobs/${uuid}/complete`);
  },

  /**
   * Reporta fallo de un job con mensaje de error.
   */
  async fail(uuid: string, errorMessage: string): Promise<void> {
    await apiClient.post(`/print-jobs/${uuid}/fail`, {
      error_message: errorMessage,
    });
  },
};
