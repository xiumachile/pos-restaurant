<?php

namespace Modules\Payments\Domain\Contracts;

use Illuminate\Support\Collection;

/**
 * Contrato formal para consultas de pagos desde otros módulos.
 * 
 * F1.4a: Este contrato permite que módulos como Cashier consulten
 * información de pagos sin acceder directamente a la tabla payments.
 * 
 * Principio: "Tell, Don't Ask" - Payments expone servicios de consulta
 * en lugar de permitir acceso directo a sus datos internos.
 */
interface PaymentQueryServiceInterface
{
    /**
     * Obtener resumen de pagos por método de pago en una sesión de caja.
     * 
     * @param int $cashSessionId ID de la sesión de caja
     * @return Collection<string, array{total_amount: float, total_tips: float, count: int}>
     */
    public function getPaymentsByMethodInSession(int $cashSessionId): Collection;

    /**
     * Obtener propinas recibidas por un garzón específico en una sesión.
     * 
     * @param int $cashSessionId ID de la sesión de caja
     * @param int $waiterId ID del garzón
     * @return float Total de propinas
     */
    public function getWaiterTipsInSession(int $cashSessionId, int $waiterId): float;

    /**
     * Obtener propinas agrupadas por método de pago en una sesión.
     * 
     * @param int $cashSessionId ID de la sesión de caja
     * @return Collection<string, array{total_tips: float, count: int}>
     */
    public function getTipsByMethodInSession(int $cashSessionId): Collection;

    /**
     * Obtener propinas agrupadas por garzón y método de pago.
     * 
     * @param int $cashSessionId ID de la sesión de caja
     * @return Collection<int, Collection<string, float>> [waiter_id => [method => total]]
     */
    public function getTipsByWaiterAndMethod(int $cashSessionId): Collection;

    /**
     * Obtener pagos del día para una sucursal.
     * 
     * @param int $branchId ID de la sucursal
     * @param string $dateStart Fecha inicio (Y-m-d H:i:s)
     * @param string $dateEnd Fecha fin (Y-m-d H:i:s)
     * @return Collection<string, array{total_amount: float, total_tips: float, count: int}>
     */
    public function getDailyPaymentsByMethod(int $branchId, string $dateStart, string $dateEnd): Collection;

    /**
     * Obtener todos los pagos de una sesión con detalles.
     * 
     * @param int $cashSessionId ID de la sesión de caja
     * @return Collection
     */
    public function getAllPaymentsInSession(int $cashSessionId): Collection;
}
