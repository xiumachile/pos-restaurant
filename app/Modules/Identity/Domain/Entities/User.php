<?php

namespace Modules\Identity\Domain\Entities;

use App\Shared\Domain\Traits\HasUuid;
use App\Shared\Domain\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasUuid;
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'branch_id',
        'pos_pin_hash',
        'role',
        'locale',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'pos_pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relación: un usuario pertenece a una empresa.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación: un usuario pertenece a una sucursal.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Verificar si el usuario es administrador.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Verificar si el usuario es encargado.
     */
    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    /**
     * Verificar PIN POS.
     */
    public function verifyPosPin(string $pin): bool
    {
        if (empty($this->pos_pin_hash)) {
            return false;
        }
        return password_verify($pin, $this->pos_pin_hash);
    }

    /**
     * Establecer PIN POS.
     */
    public function setPosPin(string $pin): void
    {
        $this->pos_pin_hash = password_hash($pin, PASSWORD_BCRYPT);
    }
}
