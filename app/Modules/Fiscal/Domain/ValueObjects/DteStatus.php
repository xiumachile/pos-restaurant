<?php

namespace Modules\Fiscal\Domain\ValueObjects;

/**
 * Estado de un DTE ante el SII (Servicio de Impuestos Internos de Chile).
 * 
 * Estados según respuesta del WebService del SII:
 * - pending: No enviado aún a SII
 * - sent: Enviado, esperando procesamiento
 * - accepted: Aceptado por SII (con Track ID)
 * - rejected: Rechazado por SII (con código de error)
 * - cancelled: Anulado (NC emitida)
 * - error: Error técnico al enviar
 */
enum DteStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case ERROR = 'error';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendiente',
            self::SENT => 'Enviado',
            self::ACCEPTED => 'Aceptado',
            self::REJECTED => 'Rechazado',
            self::CANCELLED => 'Anulado',
            self::ERROR => 'Error',
        };
    }

    /**
     * Indica si el DTE ya fue procesado exitosamente por el SII.
     */
    public function isSuccessfullyIssued(): bool
    {
        return $this === self::ACCEPTED;
    }

    /**
     * Indica si el DTE puede ser reenviado.
     */
    public function canBeResent(): bool
    {
        return in_array($this, [self::PENDING, self::ERROR, self::REJECTED]);
    }

    /**
     * Indica si el DTE puede ser anulado (vía Nota de Crédito).
     */
    public function canBeCancelled(): bool
    {
        return $this === self::ACCEPTED;
    }

    /**
     * Indica si el DTE está en un estado terminal (no se puede cambiar).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::ACCEPTED, self::CANCELLED]);
    }
}
