<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Companies\Domain\Entities\Company;
use Modules\Companies\Domain\Entities\CompanyCapability;
use Modules\Identity\Domain\Entities\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Empresa con capability de printers habilitado
    $this->companyWithPrinters = Company::create([
        'tax_id' => 'PRN-' . uniqid(),
        'legal_name' => 'Company With Printers',
        'trade_name' => 'Printers Co',
    ]);

    $this->branchPrn = Branch::create([
        'company_id' => $this->companyWithPrinters->id,
        'code' => 'BR-PRN',
        'name' => 'Branch Printers',
    ]);

    CompanyCapability::create([
        'company_id' => $this->companyWithPrinters->id,
        'capability_key' => 'can_print_receipts',
        'is_enabled' => true,
    ]);

    $this->adminWithPrinters = User::create([
        'name' => 'Admin Printers',
        'email' => 'admin-prn-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyWithPrinters->id,
        'branch_id' => $this->branchPrn->id,
        'role' => 'admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    // Empresa SIN capability de printers
    $this->companyWithoutPrinters = Company::create([
        'tax_id' => 'NO-PRN-' . uniqid(),
        'legal_name' => 'Company Without Printers',
        'trade_name' => 'No Printers Co',
    ]);

    $this->branchNoPrn = Branch::create([
        'company_id' => $this->companyWithoutPrinters->id,
        'code' => 'BR-NOPRN',
        'name' => 'Branch No Printers',
    ]);

    // NO creamos capability de printers para esta empresa

    $this->adminWithoutPrinters = User::create([
        'name' => 'Admin No Printers',
        'email' => 'admin-noprn-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $this->companyWithoutPrinters->id,
        'branch_id' => $this->branchNoPrn->id,
        'role' => 'admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    $this->tokenWith = JWTAuth::fromUser($this->adminWithPrinters);
    $this->tokenWithout = JWTAuth::fromUser($this->adminWithoutPrinters);
});

function printersCapabilityHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ];
}

// ============================================
// Middleware: can_print_receipts
// ============================================

test('usuario con capability puede acceder a printers', function () {
    $response = $this->withHeaders(printersCapabilityHeaders($this->tokenWith))
        ->getJson('/api/v1/printers');

    $response->assertStatus(200);
});

test('usuario SIN capability NO puede acceder a printers (403)', function () {
    $response = $this->withHeaders(printersCapabilityHeaders($this->tokenWithout))
        ->getJson('/api/v1/printers');

    $response->assertStatus(403)
        ->assertJsonPath('error', 'capability_not_enabled')
        ->assertJsonPath('required_capability', 'can_print_receipts');
});

test('usuario SIN capability NO puede crear impresoras (403)', function () {
    $response = $this->withHeaders(printersCapabilityHeaders($this->tokenWithout))
        ->postJson('/api/v1/printers', [
            'name' => 'Test Printer',
            'type' => 'kitchen',
            'connection_type' => 'tcp',
            'host' => '192.168.1.100',
            'port' => 9100,
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('required_capability', 'can_print_receipts');
});

test('usuario SIN capability NO puede acceder a print-jobs (403)', function () {
    $response = $this->withHeaders(printersCapabilityHeaders($this->tokenWithout))
        ->getJson('/api/v1/print-jobs');

    $response->assertStatus(403)
        ->assertJsonPath('required_capability', 'can_print_receipts');
});

test('deshabilitar capability bloquea acceso a printers (403)', function () {
    // Deshabilitar capability
    $capability = CompanyCapability::where('company_id', $this->companyWithPrinters->id)
        ->where('capability_key', 'can_print_receipts')
        ->first();
    
    $capability->update(['is_enabled' => false]);

    $response = $this->withHeaders(printersCapabilityHeaders($this->tokenWith))
        ->getJson('/api/v1/printers');

    $response->assertStatus(403)
        ->assertJsonPath('error', 'capability_not_enabled')
        ->assertJsonPath('required_capability', 'can_print_receipts');
});

test('super_admin siempre puede acceder a printers (sin capability)', function () {
    $superCompany = Company::create([
        'tax_id' => 'SUPER-PRN-' . uniqid(),
        'legal_name' => 'Super Admin Corp',
        'trade_name' => 'Super Admin',
    ]);

    $superAdmin = User::withoutGlobalScopes()->create([
        'name' => 'Super Admin',
        'email' => 'super-prn-' . uniqid() . '@test.com',
        'password' => 'password123',
        'company_id' => $superCompany->id,
        'branch_id' => null,
        'role' => 'super_admin',
        'locale' => 'es-CL',
        'is_active' => true,
    ]);

    $token = JWTAuth::fromUser($superAdmin);

    $response = $this->withHeaders(printersCapabilityHeaders($token))
        ->getJson('/api/v1/printers');

    $response->assertStatus(200);
});
