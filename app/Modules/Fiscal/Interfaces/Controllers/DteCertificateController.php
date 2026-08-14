<?php

namespace Modules\Fiscal\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Fiscal\Domain\Entities\DteCertificate;
use Modules\Fiscal\Interfaces\Requests\UploadCertificateRequest;
use Modules\Fiscal\Interfaces\Resources\DteCertificateResource;

class DteCertificateController extends Controller
{
    /**
     * GET /api/v1/fiscal/certificates
     * Lista certificados de la empresa.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $certificates = DteCertificate::where('company_id', $user->company_id)
            ->orderByDesc('valid_until')
            ->get();

        return DteCertificateResource::collection($certificates)->response();
    }

    /**
     * POST /api/v1/fiscal/certificates
     * Sube un nuevo certificado digital.
     */
    public function store(UploadCertificateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        try {
            $file = $request->file('certificate_file');
            $content = file_get_contents($file->getPathname());

            // En producción: validar el certificado con OpenSSL
            // Por ahora solo guardamos el contenido
            
            $certificate = DteCertificate::create([
                'company_id' => $user->company_id,
                'name' => $validated['name'],
                'certificate_content' => $content,
                'holder_rut' => $user->company->tax_id ?? '76.000.000-0',
                'holder_name' => $user->company->trade_name ?? 'Empresa',
                'valid_from' => now(),
                'valid_until' => now()->addYear(),
                'environment' => $validated['environment'],
                'is_active' => true,
            ]);

            return DteCertificateResource::make($certificate)
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            Log::error('Error subiendo certificado', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al subir certificado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/fiscal/certificates/{uuid}
     * Desactiva un certificado.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $certificate = DteCertificate::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $certificate->update(['is_active' => false]);

        return response()->json(['message' => 'Certificado desactivado correctamente.'], 200);
    }
}
