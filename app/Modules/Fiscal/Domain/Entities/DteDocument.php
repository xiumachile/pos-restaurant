<?php

namespace Modules\Fiscal\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Fiscal\Domain\ValueObjects\DteStatus;
use Modules\Fiscal\Domain\ValueObjects\DteType;
use Modules\Orders\Domain\Entities\Order;

/**
 * Documento Tributario Electrónico (DTE) emitido.
 * Representa una boleta, factura, nota de crédito, etc.
 */
class DteDocument extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'dte_documents';

    protected $fillable = [
        'company_id',
        'branch_id',
        'dte_type',
        'folio',
        'order_id',
        'receiver_rut',
        'receiver_business_name',
        'net_amount',
        'tax_amount',
        'exempt_amount',
        'total_amount',
        'sent_xml',
        'timbre_xml',
        'track_id',
        'sii_status',
        'sii_status_description',
        'issue_date',
        'sent_at',
        'accepted_at',
        'referenced_dte_id',
    ];

    protected $casts = [
        'dte_type' => DteType::class,
        'folio' => 'integer',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'exempt_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'track_id' => 'integer',
        'sii_status' => DteStatus::class,
        'issue_date' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Atributos por defecto.
     */
    protected $attributes = [
        'exempt_amount' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function referencedDte(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referenced_dte_id');
    }

    /**
     * Marca el DTE como enviado al SII (con track ID pendiente de procesamiento).
     */
    public function markAsSent(int $trackId, string $sentXml): void
    {
        $this->track_id = $trackId;
        $this->sent_xml = $sentXml;
        $this->sii_status = DteStatus::SENT;
        $this->sent_at = now();
        $this->save();
    }

    /**
     * Marca el DTE como aceptado por el SII (con timbre/TED).
     */
    public function markAsAccepted(string $timbreXml): void
    {
        $this->timbre_xml = $timbreXml;
        $this->sii_status = DteStatus::ACCEPTED;
        $this->accepted_at = now();
        $this->save();
    }

    /**
     * Marca el DTE como rechazado por el SII.
     */
    public function markAsRejected(string $errorDescription): void
    {
        $this->sii_status = DteStatus::REJECTED;
        $this->sii_status_description = $errorDescription;
        $this->save();
    }

    /**
     * Marca el DTE con error técnico.
     */
    public function markAsError(string $errorDescription): void
    {
        $this->sii_status = DteStatus::ERROR;
        $this->sii_status_description = $errorDescription;
        $this->save();
    }

    /**
     * Identificador único del DTE: "T{type}F{folio}"
     * Ejemplo: "T39F1042" (Boleta 1042)
     */
    public function identifier(): string
    {
        return "T{$this->dte_type->value}F{$this->folio}";
    }

    /**
     * Número formateado para impresión: "0001042"
     */
    public function formattedFolio(): string
    {
        return str_pad($this->folio, 7, '0', STR_PAD_LEFT);
    }

    public function scopeOfType($query, DteType $type)
    {
        return $query->where('dte_type', $type);
    }

    public function scopeOfStatus($query, DteStatus $status)
    {
        return $query->where('sii_status', $status);
    }

    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('issue_date', [$startDate, $endDate]);
    }
}
