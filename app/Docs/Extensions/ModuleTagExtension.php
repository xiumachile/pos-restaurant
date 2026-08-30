<?php

namespace App\Docs\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Consolida tags de Scramble en ~15 categorías coherentes.
 * 
 * ESTRATEGIA:
 * Scramble genera tags automáticamente con el nombre del controller
 * (ej: "DteDocument", "CashSession"), lo que genera 50+ tags fragmentados.
 * 
 * Esta extensión SOBRESCRIBE el array de tags con categorías coherentes
 * basadas en el namespace del controller:
 * - "Fiscal (DTE/SII)" agrupa todos los controllers fiscales
 * - "Payments & Billing" agrupa todos los controllers de pagos
 * - "Cashier (Caja)" agrupa todos los controllers de caja
 * - etc.
 * 
 * Esto pasa de 52 tags fragmentados a ~15 categorías navegables.
 */
class ModuleTagExtension extends OperationExtension
{
    /**
     * Mapeo completo de namespaces a tags consolidados.
     * El orden importa: el primer match gana.
     */
    private const TAG_MAP = [
        // Módulos principales
        'Modules\\Companies' => 'Companies (Multi-tenant)',
        'Modules\\Branches' => 'Companies (Multi-tenant)',
        
        'Modules\\Identity' => 'Identity & Auth',
        
        'Modules\\Orders' => 'Orders',
        'Modules\\Tables' => 'Orders', // Tables es contexto de pedidos dine-in
        
        'Modules\\Catalog' => 'Catalog (Products)',
        'Modules\\Recipes' => 'Catalog (Products)',
        
        'Modules\\Inventory' => 'Inventory',
        
        'Modules\\Kitchen' => 'Kitchen (KDS)',
        
        'Modules\\Payments' => 'Payments & Billing',
        
        'Modules\\Cashier' => 'Cashier (Caja)',
        
        'Modules\\Fiscal' => 'Fiscal (DTE/SII)',
        
        'Modules\\Printers' => 'Printers',
        
        'Modules\\Reports' => 'Reports',
        
        'Modules\\Tax' => 'Tax',
        
        'Modules\\Audit' => 'Audit Logs',
        
        'Modules\\Sync' => 'Sync',
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $controller = $routeInfo->route->getAction('controller') ?? '';
        $className = Str::beforeLast($controller, '::');
        
        foreach (self::TAG_MAP as $namespace => $tag) {
            if (Str::startsWith($className, $namespace)) {
                // SOBRESCRIBIR tags (no añadir) para consolidar
                $operation->tags = [$tag];
                return;
            }
        }
        
        // Fallback: cualquier ruta no mapeada
        $operation->tags = ['General'];
    }
}
