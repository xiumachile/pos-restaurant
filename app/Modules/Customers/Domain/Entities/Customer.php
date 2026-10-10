<?php

namespace Modules\Customers\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Companies\Domain\Entities\Company;
use Modules\Orders\Domain\Entities\Order;

/**
 * Cliente del restaurante (CRM básico).
 * 
 * Reglas de dominio:
 * - phone es el campo prioritario de búsqueda
 * - unique(company_id, phone): un teléfono por empresa
 * - Datos de dirección autocompletan pedidos delivery
 */
class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'branch_id',
        'phone',
        'name',
        'email',
        'address',
        'commune',
        'address_reference',
        'notes',
        'sync_status',
        'version',
        'last_synced_at',
        'offline_id',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->uuid)) {
                $customer->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    // ═══════════════════════════════════════
    // Relaciones
    // ═══════════════════════════════════════

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ═══════════════════════════════════════
    // Métodos de dominio
    // ═══════════════════════════════════════

    /**
     * Dirección completa formateada.
     * Ejemplo: "Av. Providencia 1234, Providencia (Depto 501)"
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->commune,
        ]);
        
        $address = implode(', ', $parts);
        
        if ($this->address_reference) {
            $address .= " ({$this->address_reference})";
        }
        
        return $address;
    }

    /**
     * Buscar cliente por teléfono (campo prioritario).
     * Scope multi-tenant por company_id.
     */
    /**
     * Buscar cliente por teléfono (tolerante a codificación URL).
     *
     * El '+' en query strings se decodifica como espacio, así que
     * buscamos con y sin el prefijo '+' para máxima compatibilidad.
     */
    public static function findByPhone(string $phone, int $companyId): ?self
    {
        // Normalizar: solo dígitos y +
        $normalizedPhone = preg_replace('/[^0-9+]/', '', $phone);
        // Quitar el + para búsqueda alternativa
        $digitsOnly = ltrim($normalizedPhone, '+');
        
        return static::where('company_id', $companyId)
            ->where(function ($q) use ($normalizedPhone, $digitsOnly) {
                $q->where('phone', $normalizedPhone)
                  ->orWhere('phone', $digitsOnly)
                  ->orWhere('phone', '+' . $digitsOnly);
            })
            ->first();
    }

    /**
     * Normalizar teléfono antes de guardar.
     */
    /**
     * Normalizar teléfono antes de guardar: siempre con prefijo + si tiene código país.
     * Ejemplo: "56912345678" -> "+56912345678"
     */
    public function setPhoneAttribute(string $phone): void
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        // Si empieza con 56 sin +, agregarlo
        if (preg_match('/^56\d{8,}$/', $cleaned)) {
            $cleaned = '+' . $cleaned;
        }
        $this->attributes['phone'] = $cleaned;
    }

    /**
     * Total gastado por el cliente (historial).
     */
    public function totalSpent(): int
    {
        return (int) $this->orders()
            ->whereIn('status', ['paid', 'closed'])
            ->sum('total');
    }

    /**
     * Cantidad de pedidos del cliente.
     */
    public function ordersCount(): int
    {
        return $this->orders()->count();
    }
}
