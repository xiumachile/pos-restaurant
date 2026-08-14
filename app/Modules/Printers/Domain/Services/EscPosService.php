<?php

namespace Modules\Printers\Domain\Services;

/**
 * Servicio para generar comandos ESC/POS raw bytes.
 * Según especificación ESC/POS para impresoras térmicas (Epson, Star, Bixolon, etc.)
 * 
 * Referencia: https://reference.epson-biz.com/doc/pdf/escpos_ref_en.pdf
 */
class EscPosService
{
    // ============================================
    // Comandos ESC/POS estándar
    // ============================================
    
    private const ESC = "\x1B";  // Escape character
    private const GS = "\x1D";   // Group Separator
    private const LF = "\x0A";   // Line Feed (nueva línea)
    private const FF = "\x0C";   // Form Feed (corte de papel)
    
    // Comandos de inicialización
    public const INITIALIZE = self::ESC . "@";
    
    // Comandos de fuente
    public const FONT_A = self::ESC . "M\x00";  // Fuente A (normal)
    public const FONT_B = self::ESC . "M\x01";  // Fuente B (pequeña)
    
    // Comandos de estilo
    public const BOLD_ON = self::ESC . "E\x01";
    public const BOLD_OFF = self::ESC . "E\x00";
    public const UNDERLINE_ON = self::ESC . "-\x01";
    public const UNDERLINE_OFF = self::ESC . "-\x00";
    
    // Comandos de tamaño
    public const SIZE_NORMAL = self::GS . "!\x00";
    public const SIZE_DOUBLE_HEIGHT = self::GS . "!\x01";
    public const SIZE_DOUBLE_WIDTH = self::GS . "!\x10";
    public const SIZE_DOUBLE = self::GS . "!\x11";
    
    // Comandos de alineación
    public const ALIGN_LEFT = self::ESC . "a\x00";
    public const ALIGN_CENTER = self::ESC . "a\x01";
    public const ALIGN_RIGHT = self::ESC . "a\x02";
    
    // Comandos de corte
    public const CUT_FULL = self::GS . "V\x00";
    public const CUT_PARTIAL = self::GS . "V\x01";
    
    // Comandos de cajón de dinero
    public const OPEN_DRAWER = self::ESC . "p\x00\x19\xFF";
    
    // Comandos de código de barras
    public const BARCODE_HEIGHT = self::GS . "h\x50";  // 80 puntos
    public const BARCODE_WIDTH = self::GS . "w\x02";   // 2 puntos
    public const BARCODE_TEXT_BELOW = self::GS . "H\x02";
    
    /**
     * Inicializa la impresora.
     */
    public function initialize(): string
    {
        return self::INITIALIZE;
    }

    /**
     * Agrega texto simple.
     */
    public function text(string $text): string
    {
        return $text . self::LF;
    }

    /**
     * Texto en negrita.
     */
    public function bold(string $text): string
    {
        return self::BOLD_ON . $text . self::BOLD_OFF . self::LF;
    }

    /**
     * Texto subrayado.
     */
    public function underline(string $text): string
    {
        return self::UNDERLINE_ON . $text . self::UNDERLINE_OFF . self::LF;
    }

    /**
     * Texto con tamaño doble (ancho y alto).
     */
    public function doubleSize(string $text): string
    {
        return self::SIZE_DOUBLE . $text . self::SIZE_NORMAL . self::LF;
    }

    /**
     * Texto alineado a la izquierda.
     */
    public function alignLeft(string $text): string
    {
        return self::ALIGN_LEFT . $text . self::LF;
    }

    /**
     * Texto centrado.
     */
    public function alignCenter(string $text): string
    {
        return self::ALIGN_CENTER . $text . self::LF;
    }

    /**
     * Texto alineado a la derecha.
     */
    public function alignRight(string $text): string
    {
        return self::ALIGN_RIGHT . $text . self::LF;
    }

    /**
     * Línea de separación.
     */
    public function separator(int $width = 42): string
    {
        return str_repeat('-', $width) . self::LF;
    }

    /**
     * Línea en blanco.
     */
    public function lineBreak(): string
    {
        return self::LF;
    }

    /**
     * Corte de papel completo.
     */
    public function cut(): string
    {
        return self::LF . self::LF . self::CUT_FULL;
    }

    /**
     * Corte parcial de papel.
     */
    public function partialCut(): string
    {
        return self::LF . self::LF . self::CUT_PARTIAL;
    }

    /**
     * Apertura de cajón de dinero.
     */
    public function openDrawer(): string
    {
        return self::OPEN_DRAWER;
    }

    /**
     * Genera código de barras CODE128.
     */
    public function barcode(string $data): string
    {
        return self::BARCODE_HEIGHT . self::BARCODE_WIDTH . self::BARCODE_TEXT_BELOW .
               self::GS . "k\x49" . chr(strlen($data)) . $data . self::LF;
    }

    /**
     * Genera código QR.
     * Nota: requiere soporte de la impresora para QR.
     */
    public function qrCode(string $data, int $size = 6): string
    {
        $store = self::GS . "(k\x04\x00\x31\x50\x30" . $data;
        $sizeCmd = self::GS . "(k\x03\x00\x31\x43" . chr($size);
        $print = self::GS . "(k\x03\x00\x31\x51\x30";
        
        return $store . $sizeCmd . $print . self::LF;
    }
}
