<?php

namespace Modules\Orders\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Domain\Entities\Branch;

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
        if (!$this->is_enabled) {
            // Formato legacy: ORD-{branchId}-{YYYYMMDD}-{####}
            $date = now()->format('Ymd');
            $lastOrder = Order::where('branch_id', $this->branch_id)
                ->whereDate('created_at', today())
                ->orderBy('id', 'desc')
                ->first();

            $seq = $lastOrder ? (intval(substr($lastOrder->order_number, -4)) + 1) : 1;

            return sprintf('ORD-%03d-%s-%04d', $this->branch_id, $date, $seq);
        }

        // Resetear si es necesario
        if ($this->shouldReset()) {
            $this->current_sequence = 1;
            $this->last_reset_date = now();
        } else {
            $this->current_sequence++;
        }

        $this->save();

        // Formato personalizado: {PREFIX}-{YYYYMM}-{####} o {PREFIX}-{YYYYMMDD}-{####}
        if ($this->reset_frequency === 'daily') {
            $date = now()->format('Ymd');
            return sprintf('%s-%s-%04d', $this->prefix, $date, $this->current_sequence);
        }

        $date = now()->format('Ym');
        return sprintf('%s-%s-%04d', $this->prefix, $date, $this->current_sequence);
    }
}
