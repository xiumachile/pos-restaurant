<?php

namespace Modules\Printers\Domain\ValueObjects;

/**
 * Tipo de conexión de la impresora.
 */
enum ConnectionType: string
{
    case TCP = 'tcp';           // TCP/IP socket (puerto 9100)
    case USB = 'usb';           // USB local (/dev/usb/lp0)
    case BLUETOOTH = 'bluetooth'; // Bluetooth (MAC address)

    public function label(): string
    {
        return match($this) {
            self::TCP => 'TCP/IP',
            self::USB => 'USB',
            self::BLUETOOTH => 'Bluetooth',
        };
    }

    /**
     * Verifica si requiere host y port.
     */
    public function requiresHostAndPort(): bool
    {
        return $this === self::TCP;
    }

    /**
     * Verifica si requiere device_path.
     */
    public function requiresDevicePath(): bool
    {
        return in_array($this, [self::USB, self::BLUETOOTH]);
    }
}
