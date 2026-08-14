<?php

namespace Modules\Printers\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\Exceptions\PrinterConnectionException;
use Modules\Printers\Domain\Services\PrintService;
use Modules\Printers\Domain\ValueObjects\ConnectionType;
use Modules\Printers\Domain\ValueObjects\PrinterType;
use Modules\Printers\Interfaces\Requests\CreatePrinterRequest;
use Modules\Printers\Interfaces\Requests\UpdatePrinterRequest;
use Modules\Printers\Interfaces\Resources\PrinterResource;

class PrinterController extends Controller
{
    public function __construct(
        private PrintService $printService
    ) {}

    /**
     * GET /api/v1/printers
     * Lista impresoras de la sucursal actual.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $printers = Printer::where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->withCount('stationMappings')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return PrinterResource::collection($printers)->response();
    }

    /**
     * POST /api/v1/printers
     * Crea una nueva impresora.
     */
    public function store(CreatePrinterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $printer = Printer::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'name' => $validated['name'],
            'type' => PrinterType::from($validated['type']),
            'connection_type' => ConnectionType::from($validated['connection_type']),
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? 9100,
            'device_path' => $validated['device_path'] ?? null,
            'paper_width' => $validated['paper_width'] ?? 80,
            'auto_cut' => $validated['auto_cut'] ?? true,
            'open_drawer_on_print' => $validated['open_drawer_on_print'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return PrinterResource::make($printer)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/printers/{uuid}
     * Obtiene una impresora específica.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $printer = Printer::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->withCount('stationMappings')
            ->firstOrFail();

        return PrinterResource::make($printer)->response();
    }

    /**
     * PUT /api/v1/printers/{uuid}
     * Actualiza una impresora.
     */
    public function update(UpdatePrinterRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $printer = Printer::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $updateData = [];
        foreach ($validated as $key => $value) {
            if ($key === 'type') {
                $updateData[$key] = PrinterType::from($value);
            } elseif ($key === 'connection_type') {
                $updateData[$key] = ConnectionType::from($value);
            } else {
                $updateData[$key] = $value;
            }
        }

        $printer->update($updateData);

        return PrinterResource::make($printer)->response();
    }

    /**
     * DELETE /api/v1/printers/{uuid}
     * Elimina una impresora (soft delete).
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $printer = Printer::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $printer->delete();

        return response()->json(['message' => 'Impresora eliminada correctamente.'], 200);
    }

    /**
     * POST /api/v1/printers/{uuid}/test
     * Prueba la conexión con la impresora.
     */
    public function test(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $printer = Printer::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        if (!$printer->validateConnection()) {
            return response()->json([
                'success' => false,
                'message' => 'Configuración de conexión inválida.',
                'connection_type' => $printer->connection_type?->value,
            ], 422);
        }

        try {
            // Crear un job de prueba con comando de inicialización + texto de prueba
            $testBytes = "\x1B@=== PRUEBA DE IMPRESION ===\n" .
                        "Impresora: " . $printer->name . "\n" .
                        "Fecha: " . now()->format('d/m/Y H:i:s') . "\n" .
                        "Conexion: " . $printer->connection_type?->label() . "\n" .
                        "==========================\n\x0A\x0A\x1DV\x00";

            $job = \Modules\Printers\Domain\Entities\PrintJob::create([
                'company_id' => $printer->company_id,
                'branch_id' => $printer->branch_id,
                'printer_id' => $printer->id,
                'job_type' => 'test',
                'escpos_bytes' => $testBytes,
                'status' => \Modules\Printers\Domain\Entities\PrintJob::STATUS_PENDING,
                'attempts' => 0,
            ]);

            // Intentar enviar el job
            $this->printService->send($job);

            return response()->json([
                'success' => true,
                'message' => 'Prueba de impresión enviada exitosamente.',
                'job_uuid' => $job->uuid,
                'printer' => $printer->name,
            ]);
        } catch (PrinterConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'printer' => $printer->name,
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error en prueba de impresora', [
                'printer_id' => $printer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar prueba: ' . $e->getMessage(),
            ], 500);
        }
    }
}
