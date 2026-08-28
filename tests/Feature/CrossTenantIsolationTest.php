<?php

use App\Shared\Application\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Branches\Application\Services\BranchQueryService;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;
use Modules\Tables\Domain\Entities\RestaurantTable;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear Company A
    $this->companyA = Company::create([
        'tax_id' => 'EMP-A-' . uniqid(),
        'legal_name' => 'Empresa A Test',
        'trade_name' => 'Restaurante A Test',
    ]);

    // Crear Company B
    $this->companyB = Company::create([
        'tax_id' => 'EMP-B-' . uniqid(),
        'legal_name' => 'Empresa B Test',
        'trade_name' => 'Restaurante B Test',
    ]);

    // Crear Branch A
    $this->branchA = Branch::create([
        'company_id' => $this->companyA->id,
        'code' => 'BRA-' . uniqid(),
        'name' => 'Sucursal A Test',
    ]);

    // Crear Branch B
    $this->branchB = Branch::create([
        'company_id' => $this->companyB->id,
        'code' => 'BRB-' . uniqid(),
        'name' => 'Sucursal B Test',
    ]);

    // Crear User A
    $this->userA = User::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'name' => 'Usuario A',
        'email' => 'user-a-' . uniqid() . '@test.cl',
        'password' => 'password',
        'role' => 'admin',
    ]);

    // Crear User B
    $this->userB = User::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'name' => 'Usuario B',
        'email' => 'user-b-' . uniqid() . '@test.cl',
        'password' => 'password',
        'role' => 'admin',
    ]);

    // Crear mesas en Branch A (2 mesas en MAIN, 1 en TERRACE)
    RestaurantTable::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón A', 'zh' => '厅A'],
        'table_number' => 'A-MAIN-01',
        'capacity' => 4,
    ]);

    RestaurantTable::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón A', 'zh' => '厅A'],
        'table_number' => 'A-MAIN-02',
        'capacity' => 2,
    ]);

    RestaurantTable::create([
        'company_id' => $this->companyA->id,
        'branch_id' => $this->branchA->id,
        'area_code' => 'TERRACE',
        'area_name_translations' => ['es' => 'Terraza A', 'zh' => '露台A'],
        'table_number' => 'A-TERR-01',
        'capacity' => 6,
    ]);

    // Crear mesas en Branch B (2 mesas en MAIN)
    RestaurantTable::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón B', 'zh' => '厅B'],
        'table_number' => 'B-MAIN-01',
        'capacity' => 4,
    ]);

    RestaurantTable::create([
        'company_id' => $this->companyB->id,
        'branch_id' => $this->branchB->id,
        'area_code' => 'MAIN',
        'area_name_translations' => ['es' => 'Salón B', 'zh' => '厅B'],
        'table_number' => 'B-MAIN-02',
        'capacity' => 2,
    ]);

    // Generar tokens JWT
    $this->tokenA = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($this->userA);
    $this->tokenB = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($this->userB);
});

describe('THREAT-006: BranchQueryService isolation', function () {
    test('usuario de Company A no puede acceder a Branch de Company B', function () {
        $tenantContext = app(TenantContext::class);
        $tenantContext->setCompany($this->companyA->id, $this->branchA->id, $this->userA->id);

        $branchQueryService = app(BranchQueryService::class);
        $branchB = $branchQueryService->findById($this->branchB->id);

        expect($branchB)->toBeNull();
    });

    test('usuario de Company A puede acceder a su propia Branch', function () {
        $tenantContext = app(TenantContext::class);
        $tenantContext->setCompany($this->companyA->id, $this->branchA->id, $this->userA->id);

        $branchQueryService = app(BranchQueryService::class);
        $branchA = $branchQueryService->findById($this->branchA->id);

        expect($branchA)->not->toBeNull()
            ->and($branchA->id)->toBe($this->branchA->id)
            ->and($branchA->company_id)->toBe($this->companyA->id);
    });

    test('usuario de Company B no puede acceder a Branch de Company A', function () {
        $tenantContext = app(TenantContext::class);
        $tenantContext->setCompany($this->companyB->id, $this->branchB->id, $this->userB->id);

        $branchQueryService = app(BranchQueryService::class);
        $branchA = $branchQueryService->findById($this->branchA->id);

        expect($branchA)->toBeNull();
    });
});

describe('General tenant isolation via API', function () {
    test('usuario de Branch A solo ve mesas de su branch (agrupadas por área)', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenA,
        ])->getJson('/api/v1/tables');

        $response->assertOk();
        
        $areas = $response->json('data');
        
        // Branch A tiene 2 áreas (MAIN y TERRACE)
        expect($areas)->toHaveCount(2);
        
        $areaCodes = collect($areas)->pluck('area_code')->toArray();
        expect($areaCodes)->toContain('MAIN')
            ->and($areaCodes)->toContain('TERRACE');
        
        // Verificar que NO aparezcan mesas de Branch B
        $allTableNumbers = collect($areas)
            ->flatMap(fn($area) => collect($area['tables'])->pluck('table_number'))
            ->toArray();
        
        expect($allTableNumbers)->toContain('A-MAIN-01')
            ->and($allTableNumbers)->toContain('A-MAIN-02')
            ->and($allTableNumbers)->toContain('A-TERR-01')
            ->and($allTableNumbers)->not->toContain('B-MAIN-01')
            ->and($allTableNumbers)->not->toContain('B-MAIN-02');
    });

    test('usuario de Branch B solo ve mesas de su branch (agrupadas por área)', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenB,
        ])->getJson('/api/v1/tables');

        $response->assertOk();
        
        $areas = $response->json('data');
        
        // Branch B tiene solo 1 área (MAIN)
        expect($areas)->toHaveCount(1);
        expect($areas[0]['area_code'])->toBe('MAIN');
        
        // Verificar que solo aparezcan mesas de Branch B
        $tableNumbers = collect($areas[0]['tables'])->pluck('table_number')->toArray();
        
        expect($tableNumbers)->toContain('B-MAIN-01')
            ->and($tableNumbers)->toContain('B-MAIN-02')
            ->and($tableNumbers)->not->toContain('A-MAIN-01')
            ->and($tableNumbers)->not->toContain('A-MAIN-02')
            ->and($tableNumbers)->not->toContain('A-TERR-01');
    });

    test('usuario no autenticado recibe 401', function () {
        $response = $this->getJson('/api/v1/tables');
        $response->assertUnauthorized();
    });
});
