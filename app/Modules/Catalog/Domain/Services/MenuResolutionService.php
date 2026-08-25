<?php

namespace Modules\Catalog\Domain\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Entities\Menu;
use Modules\Catalog\Domain\Entities\MenuActivation;

/**
 * Resuelve automáticamente qué carta usar según el contexto de venta.
 * 
 * Lógica:
 * 1. Busca cartas activas cuyas reglas de activación matcheen
 *    (canal + día de semana + horario), ordenadas por prioridad.
 * 2. Si ninguna regla matchea, retorna la carta default de la sucursal.
 * 3. Si tampoco hay default, retorna null (el caller decide cómo manejarlo).
 * 
 * Usa JOIN en lugar de whereHas para evitar problemas con BelongsToTenant.
 */
class MenuResolutionService
{
    /**
     * Resuelve la carta activa para el contexto dado.
     *
     * @param int         $branchId    Sucursal actual
     * @param string      $channelType dine_in | delivery | uber_eats | rappi
     * @param Carbon|null $now         Momento de evaluación (default: ahora)
     * @return Menu|null Carta activa o null si no hay ninguna
     */
    public function resolve(int $branchId, string $channelType, ?Carbon $now = null): ?Menu
    {
        $now = $now ?? Carbon::now($this->getBranchTimezone($branchId));
        $dayOfWeek = $now->dayOfWeekIso; // 1=lunes ... 7=domingo
        $time = $now->format('H:i:s');

        // Query directo con JOIN (evita problemas con BelongsToTenant)
        $activation = DB::table('menu_activations as ma')
            ->join('menus as m', 'm.id', '=', 'ma.menu_id')
            ->where('ma.is_active', true)
            ->where('m.branch_id', $branchId)
            ->where('m.is_active', true)
            ->whereNull('m.deleted_at')
            ->where(function ($q) use ($channelType) {
                $q->where('ma.channel_type', $channelType)
                  ->orWhere('ma.channel_type', MenuActivation::CHANNEL_ALL);
            })
            ->where(function ($q) use ($time) {
                $q->where(function ($sub) use ($time) {
                    $sub->whereNull('ma.time_from')->orWhere('ma.time_from', '<=', $time);
                })->where(function ($sub) use ($time) {
                    $sub->whereNull('ma.time_to')->orWhere('ma.time_to', '>=', $time);
                });
            })
            ->where(function ($q) use ($dayOfWeek) {
                // days_of_week null = todos los días; si no, debe contener el día actual
                $q->whereNull('ma.days_of_week')
                  ->orWhereRaw("ma.days_of_week @> to_jsonb(?::int)", [$dayOfWeek]);
            })
            ->orderByDesc('ma.priority')
            ->select('m.id')
            ->first();

        if ($activation) {
            return Menu::find($activation->id);
        }

        // Fallback: carta default de la sucursal (usando DB directo también)
        $defaultMenuId = DB::table('menus')
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->value('id');

        return $defaultMenuId ? Menu::find($defaultMenuId) : null;
    }

    /**
     * Obtiene la timezone de la sucursal (fallback a America/Santiago).
     */
    private function getBranchTimezone(int $branchId): string
    {
        static $cache = [];
        if (!isset($cache[$branchId])) {
            $timezone = DB::table('branches')
                ->where('id', $branchId)
                ->value('timezone');
            $cache[$branchId] = $timezone ?: 'America/Santiago';
        }
        return $cache[$branchId];
    }
}
