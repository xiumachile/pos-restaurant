<?php

namespace Modules\Identity\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
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

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'role' => $this->role,
            'locale' => $this->locale,
            'uuid' => $this->uuid,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    public function verifyPosPin(string $pin): bool
    {
        if (empty($this->pos_pin_hash)) {
            return false;
        }
        return password_verify($pin, $this->pos_pin_hash);
    }

    public function setPosPin(string $pin): void
    {
        $this->pos_pin_hash = password_hash($pin, PASSWORD_BCRYPT);
    }
}
