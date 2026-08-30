<?php

namespace App\Docs\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Agrupa endpoints por módulo basándose en el namespace del controller.
 */
class ModuleTagExtension extends OperationExtension
{
    private const TAG_MAP = [
        'Modules\\Companies' => 'Companies (Multi-tenant)',
        'Modules\\Branches' => 'Branches',
        'Modules\\Identity' => 'Identity & Auth',
        'Modules\\Orders' => 'Orders',
        'Modules\\Tables' => 'Tables',
        'Modules\\Catalog' => 'Catalog (Products)',
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
                // Acceso directo a la propiedad pública $tags
                $operation->tags[] = $tag;
                return;
            }
        }
        
        $operation->tags[] = 'General';
    }
}
