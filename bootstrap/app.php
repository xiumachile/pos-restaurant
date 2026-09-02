<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use App\Shared\Http\Middleware\IdempotencyKeyMiddleware;
use Modules\Accounting\Domain\Exceptions\UnbalancedJournalEntryException;
use Modules\Payments\Domain\Exceptions\InvalidRefundException;
use Modules\Payments\Domain\Exceptions\PaymentException;
use Modules\Orders\Domain\Exceptions\OrderNotModifiableException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:api']]
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Shared\Http\Middleware\CheckRole::class,
            'capability' => \App\Shared\Http\Middleware\CheckCompanyCapability::class,
            'idempotent' => IdempotencyKeyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // AuthenticationException → 401 JSON
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'unauthenticated',
                    'message' => 'Token de autenticación inválido o ausente.',
                ], 401);
            }
        });

        // AuthorizationException → 403 JSON
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'forbidden',
                    'message' => $e->getMessage() ?: 'No tienes permisos para realizar esta acción.',
                ], 403);
            }
        });
        // OrderNotModifiableException → 422 JSON
        $exceptions->render(function (OrderNotModifiableException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $e->render();
            }
        });

        // PaymentException → 422 JSON (errores de dominio de pagos)
        $exceptions->render(function (PaymentException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'payment_failed',
                    'message' => $e->getMessage(),
                ], 422);
            }
        });

        // InvalidRefundException → 422 JSON (errores de dominio de refunds)
        $exceptions->render(function (InvalidRefundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'refund_failed',
                    'message' => $e->getMessage(),
                ], 422);
            }
        });

        // UnbalancedJournalEntryException → 422 JSON (errores de contabilidad)
        $exceptions->render(function (UnbalancedJournalEntryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'journal_entry_unbalanced',
                    'message' => $e->getMessage(),
                ], 422);
            }
        });
    })->create();
