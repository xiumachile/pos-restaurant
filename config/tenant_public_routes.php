<?php

/**
 * Rutas públicas que NO requieren autenticación ni contexto de tenant.
 * 
 * Estas rutas pueden ser accedidas por:
 * - Usuarios no autenticados (login, registro)
 * - Webhooks de servicios externos (SII, Transbank, MercadoPago)
 * - Health checks y monitoring
 * - Documentación API (Swagger/OpenAPI)
 * 
 * NOTA: El orden de los patrones es importante. Se evalúan en orden
 * y el primer match gana.
 */
return [
    'routes' => [
        // ============================================
        // Autenticación
        // ============================================
        'api/v1/auth/login',
        'api/v1/auth/login/pin',
        'api/v1/auth/register',
        'api/v1/auth/forgot-password',
        'api/v1/auth/reset-password',
        
        // ============================================
        // Webhooks de integraciones externas
        // ============================================
        'api/v1/webhooks/sii/*',      // Notificaciones del SII Chile
        'api/v1/webhooks/transbank/*', // Notificaciones de pagos
        'api/v1/webhooks/mercadopago/*',
        'api/v1/webhooks/stripe/*',
        
        // ============================================
        // Health checks y monitoring
        // ============================================
        'api/health',
        'api/health/*',
        'api/status',
        'horizon/*',  // Dashboard de Horizon (con su propia auth)
        
        // ============================================
        // Documentación API
        // ============================================
        'api/docs',
        'api/docs/*',
        'docs/*',
        'swagger/*',
        'openapi/*',
        
        // ============================================
        // Menús QR (acceso público desde mesas)
        // ============================================
        'api/v1/menus/public/*',
        'api/v1/branches/*/menu',
        'api/v1/qrcode/*',
        
        // ============================================
        // Rutas de desarrollo/testing (solo en local)
        // ============================================
        'api/dev/*',
        'telescope/*',
    ],
    
    /**
     * Patrones de rutas que SIEMPRE requieren autenticación,
     * sin importar si están en la lista pública.
     * 
     * Esto previene que alguien agregue accidentalmente una ruta
     * sensible a la lista pública.
     */
    'force_auth_routes' => [
        'api/v1/orders/*',
        'api/v1/payments/*',
        'api/v1/users/*',
        'api/v1/companies/*',
        'api/v1/branches/*',
        'api/v1/cashier/*',
        'api/v1/fiscal/*',
        'api/v1/printers/*',
    ],
    
    /**
     * Headers permitidos para establecer contexto en rutas públicas.
     * 
     * Solo estos headers pueden usarse para setear tenant sin auth.
     * Cualquier otro header será ignorado.
     */
    'allowed_public_headers' => [
        'X-Company-ID',
        'X-Branch-ID',
        'X-Locale',
    ],
];
