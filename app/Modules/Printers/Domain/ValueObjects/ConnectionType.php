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
    case SERIAL = 'serial';     // Puerto serie (COM1, /dev/ttyS0)

    public function label(): string
    {
        return match($this) {
            self::TCP => 'TCP/IP',
            self::USB => 'USB',
            self::BLUETOOTH => 'Bluetooth',
            self::SERIAL => 'Serial',
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
        return in_array($this, [self::USB, self::BLUETOOTH, self::SERIAL]);
    }
}
