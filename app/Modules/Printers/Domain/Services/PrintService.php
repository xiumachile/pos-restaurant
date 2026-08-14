<?php

namespace Modules\Printers\Domain\Services;

use Illuminate\Support\Facades\Log;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Entities\PrintJob;
use Modules\Printers\Domain\Exceptions\PrinterConnectionException;
use Modules\Printers\Domain\ValueObjects\ConnectionType;

/**
 * Servicio para enviar bytes ESC/POS a impresoras reales.
 * Soporta TCP/IP (socket raw), USB y Bluetooth.
 */
class PrintService
{
    // Timeout de conexión en segundos
    private const CONNECTION_TIMEOUT = 5;
    private const WRITE_TIMEOUT = 10;

    /**
     * Envía un trabajo de impresión a la impresora configurada.
     */
    public function send(PrintJob $job): void
    {
        $printer = $job->printer;
        
        if (!$printer || !$printer->validateConnection()) {
            throw new PrinterConnectionException("Configuración de impresora inválida");
        }

        $job->markAsPrinting();

        try {
            $bytes = $job->escpos_bytes;
            
            match ($printer->connection_type) {
                ConnectionType::TCP => $this->sendViaTcp($printer, $bytes),
                ConnectionType::USB => $this->sendViaUsb($printer, $bytes),
                ConnectionType::BLUETOOTH => $this->sendViaBluetooth($printer, $bytes),
            };

            $job->markAsCompleted();

            Log::info('PrintJob completado', [
                'job_id' => $job->id,
                'printer' => $printer->name,
                'bytes_sent' => strlen($bytes),
            ]);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            if ($job->canRetry()) {
                $job->status = PrintJob::STATUS_PENDING;
                $job->error_message = "Reintento pendiente: {$errorMessage}";
                $job->save();
                
                Log::warning('PrintJob falló, se reintentará', [
                    'job_id' => $job->id,
                    'attempts' => $job->attempts,
                    'error' => $errorMessage,
                ]);
            } else {
                $job->markAsFailed($errorMessage);
                
                Log::error('PrintJob falló definitivamente', [
                    'job_id' => $job->id,
                    'attempts' => $job->attempts,
                    'error' => $errorMessage,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Envía bytes via TCP socket raw (puerto 9100).
     */
    private function sendViaTcp(Printer $printer, string $bytes): void
    {
        $socket = @fsockopen(
            $printer->host,
            $printer->port,
            $errno,
            $errstr,
            self::CONNECTION_TIMEOUT
        );

        if (!$socket) {
            throw new PrinterConnectionException(
                "No se pudo conectar a {$printer->host}:{$printer->port} - {$errstr} ({$errno})"
            );
        }

        try {
            stream_set_timeout($socket, self::WRITE_TIMEOUT);
            fwrite($socket, $bytes);
            fflush($socket);
        } finally {
            fclose($socket);
        }
    }

    /**
     * Envía bytes via USB (archivo de dispositivo).
     * Nota: en modo testing/dev, solo logea la operación.
     */
    private function sendViaUsb(Printer $printer, string $bytes): void
    {
        $devicePath = $printer->device_path;
        
        // En entorno de testing, solo logear
        if (app()->environment('testing', 'local')) {
            Log::info('USB Print (simulado)', [
                'device' => $devicePath,
                'bytes' => strlen($bytes),
            ]);
            return;
        }

        if (!file_exists($devicePath)) {
            throw new PrinterConnectionException("Dispositivo USB no encontrado: {$devicePath}");
        }

        $handle = @fopen($devicePath, 'wb');
        if (!$handle) {
            throw new PrinterConnectionException("No se pudo abrir dispositivo USB: {$devicePath}");
        }

        try {
            fwrite($handle, $bytes);
            fflush($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Envía bytes via Bluetooth.
     * Nota: implementación placeholder (requiere sistema operativo específico).
     */
    private function sendViaBluetooth(Printer $printer, string $bytes): void
    {
        // Placeholder - requeriría librerías específicas del SO
        Log::info('Bluetooth Print (placeholder)', [
            'device' => $printer->device_path,
            'bytes' => strlen($bytes),
        ]);
        
        // En producción real, aquí iría la implementación específica
        // Ej: rfcomm, bluez, etc.
    }
}
