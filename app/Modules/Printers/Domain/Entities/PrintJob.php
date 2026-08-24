<?php

namespace Modules\Printers\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Orders\Domain\Entities\Order;

/**
 * Trabajo de impresión en cola.
 * Contiene los bytes ESC/POS listos para enviar a la impresora.
 */
class PrintJob extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'printer_id',
        'job_type',
        'order_id',
        'escpos_bytes',
        'status',
        'claimed_by',
        'claimed_at',
        'attempts',
        'max_attempts',
        'error_message',
        'printed_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'printed_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    // Estados
    public const STATUS_PENDING = 'pending';
    public const STATUS_PRINTING = 'printing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    // Tipos de trabajo
    public const TYPE_KITCHEN_COMMAND = 'kitchen_command';
    public const TYPE_BAR_COMMAND = 'bar_command';
    public const TYPE_RECEIPT = 'receipt';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Verifica si el job está disponible para ser reclamado.
     * Un job está disponible si:
     * - Está en estado pending
     * - No está reclamado, O fue reclamado hace más de 5 minutos (timeout)
     */
    public function isAvailableForClaim(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }
        
        if (!$this->claimed_at) {
            return true;
        }
        
        // Timeout de 5 minutos para reclamar
        return $this->claimed_at->lt(now()->subMinutes(5));
    }

    /**
     * Reclama el trabajo para un cliente específico.
     */
    public function claim(string $clientId): bool
    {
        if (!$this->isAvailableForClaim()) {
            return false;
        }

        $this->claimed_by = $clientId;
        $this->claimed_at = now();
        $this->status = self::STATUS_PRINTING;
        $this->attempts += 1;
        $this->save();

        return true;
    }

    /**
     * Libera el claim (útil si el cliente falla antes de imprimir).
     */
    public function releaseClaim(): void
    {
        $this->claimed_by = null;
        $this->claimed_at = null;
        $this->status = self::STATUS_PENDING;
        $this->save();
    }

    /**
     * Marca el trabajo como "imprimiendo".
     */
    public function markAsPrinting(): void
    {
        $this->status = self::STATUS_PRINTING;
        $this->attempts += 1;
        $this->save();
    }

    /**
     * Marca el trabajo como completado exitosamente.
     */
    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->printed_at = now();
        $this->save();
        
        // Actualizar contador de impresiones de la impresora
        $this->printer->recordPrint();
    }

    /**
     * Marca el trabajo como fallido.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->save();
    }

    /**
     * Verifica si se pueden hacer más intentos.
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    /**
     * Scope: trabajos pendientes.
     */

    /**
     * Accessor para escpos_bytes.
     * PostgreSQL retorna campos binary como resources, los convertimos a string.
     * Rebobinamos el stream antes de leerlo porque puede haber sido leído antes.
     */
    public function getEscposBytesAttribute($value)
    {
        if (is_resource($value)) {
            // Rebobinar el stream en caso de que ya haya sido leído
            if (ftell($value) !== 0) {
                rewind($value);
            }
            return stream_get_contents($value);
        }
        return $value;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: trabajos de una impresora específica.
     */
    public function scopeForPrinter($query, int $printerId)
    {
        return $query->where('printer_id', $printerId);
    }
}
