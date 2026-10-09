<?php

namespace Modules\Orders\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Domain\Entities\Branch;
use Illuminate\Support\Facades\DB;

class OrderNumberingConfig extends Model
{
    protected $table = 'order_numbering_config';

    protected $fillable = [
        'branch_id',
        'is_enabled',
        'prefix',
        'reset_frequency',
        'current_sequence',
        'last_reset_date',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'current_sequence' => 'integer',
        'last_reset_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Verifica si debe resetear la secuencia
     */
    public function shouldReset(): bool
    {
        if (!$this->last_reset_date) {
            return true;
        }

        $today = now()->toDateString();

        if ($this->reset_frequency === 'daily') {
            return $this->last_reset_date->toDateString() !== $today;
        }

        if ($this->reset_frequency === 'monthly') {
            return $this->last_reset_date->format('Y-m') !== now()->format('Y-m');
        }

        return false;
    }

    /**
     * Genera el próximo número de orden
     */
    public function generateNextNumber(): string
    {
        // P1-011: Usar transacción + lockForUpdate para prevenir race conditions
        return DB::transaction(function () {
            if (!$this->is_enabled) {
                // Formato legacy: ORD-{branchId}-{YYYYMMDD}-{####}
                $date = now()->format('Ymd');
                $lastOrder = Order::where('branch_id', $this->branch_id)
                    ->whereDate('created_at', today())
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

                $seq = $lastOrder ? (intval(substr($lastOrder->order_number, -4)) + 1) : 1;

                return sprintf('ORD-%03d-%s-%04d', $this->branch_id, $date, $seq);
            }

            // Recargar con lockForUpdate para prevenir race conditions
            $lockedConfig = self::where('branch_id', $this->branch_id)
                ->lockForUpdate()
                ->first();

            if (!$lockedConfig) {
                throw new \RuntimeException("OrderNumberingConfig not found for branch {$this->branch_id}");
            }

            // Resetear si es necesario
            if ($lockedConfig->shouldReset()) {
                $lockedConfig->current_sequence = 1;
                $lockedConfig->last_reset_date = now();
            } else {
                $lockedConfig->current_sequence++;
            }

            $lockedConfig->save();

            // Actualizar la instancia actual
            $this->current_sequence = $lockedConfig->current_sequence;
            $this->last_reset_date = $lockedConfig->last_reset_date;

            // Formato personalizado
            if ($lockedConfig->reset_frequency === 'daily') {
                $date = now()->format('Ymd');
                return sprintf('%s-%s-%04d', $lockedConfig->prefix, $date, $lockedConfig->current_sequence);
            }

            $date = now()->format('Ym');
            return sprintf('%s-%s-%04d', $lockedConfig->prefix, $date, $lockedConfig->current_sequence);
        });
    }
}
