<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Lista de excepciones que NO deben reportarse.
     */
    protected $dontReport = [
        //
    ];

    /**
     * Lista de inputs que nunca deben aparecer en logs.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'pin',
        'pos_pin',
        'credit_card_number',
        'cvv',
    ];

    /**
     * Registrar excepciones con contexto enriquecido.
     */
    public function report(Throwable $exception): void
    {
        // Agregar contexto adicional a todos los logs de error
        $context = [
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        // Si hay request activo, agregar más contexto
        if (request()) {
            $context['url'] = request()->fullUrl();
            $context['method'] = request()->method();
            $context['ip'] = request()->ip();
            
            if (request()->user()) {
                $context['user_id'] = request()->user()->id;
                $context['company_id'] = request()->user()->company_id;
            }
        }

        Log::error($exception->getMessage(), array_merge($context, [
            'trace' => $exception->getTraceAsString(),
        ]));

        parent::report($exception);
    }

    /**
     * Renderizar excepciones con formato consistente.
     */
    public function render($request, Throwable $e)
    {
        // AuthenticationException: formato personalizado para API
        if ($e instanceof AuthenticationException && $request->expectsJson()) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'Token de autenticación inválido o ausente.',
            ], 401);
        }

        // Excepciones de dominio con formato específico
        if ($e instanceof InvalidTableStatusTransition && $request->expectsJson()) {
            return response()->json([
                'error' => 'invalid_status_transition',
                'message' => $e->getMessage(),
            ], 422);
        }

        // En producción, no exponer detalles de errores internos
        if (app()->environment('production') && !$request->expectsJson()) {
            return response()->view('errors.500', [], 500);
        }

        return parent::render($request, $e);
    }
}
