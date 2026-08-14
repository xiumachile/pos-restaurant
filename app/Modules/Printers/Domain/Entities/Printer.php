<?php

namespace Modules\Printers\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Printers\Domain\ValueObjects\ConnectionType;
use Modules\Printers\Domain\ValueObjects\PrinterType;

/**
 * Impresora configurada para una sucursal.
 * Soporta TCP/IP (socket raw), USB y Bluetooth.
 */
class Printer extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'type',
        'connection_type',
        'host',
        'port',
        'device_path',
        'paper_width',
        'auto_cut',
        'open_drawer_on_print',
        'is_active',
        'last_printed_at',
        'print_count',
    ];

    protected $casts = [
        'type' => PrinterType::class,
        'connection_type' => ConnectionType::class,
        'port' => 'integer',
        'paper_width' => 'integer',
        'auto_cut' => 'boolean',
        'open_drawer_on_print' => 'boolean',
        'is_active' => 'boolean',
        'last_printed_at' => 'datetime',
        'print_count' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stationMappings(): HasMany
    {
        return $this->hasMany(PrinterStationMapping::class);
    }

    /**
     * Valida que la configuración de conexión sea correcta.
     */
    public function validateConnection(): bool
    {
        if ($this->connection_type->requiresHostAndPort()) {
            return !empty($this->host) && $this->port > 0;
        }

        if ($this->connection_type->requiresDevicePath()) {
            return !empty($this->device_path);
        }

        return true;
    }

    /**
     * Verifica si es una impresora de cocina (kitchen o bar).
     */
    public function isKitchenPrinter(): bool
    {
        return $this->type->isKitchenPrinter();
    }

    /**
     * Incrementa el contador de impresiones.
     */
    public function recordPrint(): void
    {
        $this->increment('print_count');
        $this->last_printed_at = now();
        $this->save();
    }

    /**
     * Scope: impresoras activas.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: impresoras de un tipo específico.
     */
    public function scopeOfType($query, PrinterType $type)
    {
        return $query->where('type', $type);
    }
}
