<?php

namespace Modules\Cashier\Domain\Exceptions;

/**
 * Se lanza cuando se intenta cobrar una mesa que no tiene pedidos en
 * estado SERVED. Se lanza DENTRO de la transacción de chargeTable() para
 * poder usar lockForUpdate() de forma segura antes de decidir si hay algo
 * que cobrar.
 */
class NoOrdersToChargeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('La mesa no tiene pedidos servidos para cobrar.');
    }
}
