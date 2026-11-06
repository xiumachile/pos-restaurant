<?php

namespace Tests\Feature\Platform;

use App\Console\Commands\BootstrapSuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Entities\User;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class BootstrapSuperAdminTest extends TestCase
{
    public function test_creates_global_super_admin_without_tenant(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame(
            'pos_restaurant_bootstrap_test',
            DB::selectOne('SELECT current_database() AS name')->name
        );

        DB::beginTransaction();

        try {
            $command = app(BootstrapSuperAdmin::class);
            $command->setLaravel(app());

            $tester = new CommandTester($command);

            $tester->setInputs([
                'Administrador SaaS',
                'bootstrap-test@example.invalid',
                'ClaveTemporalSegura2026!',
                'ClaveTemporalSegura2026!',
            ]);

            $exitCode = $tester->execute([], [
                'interactive' => true,
            ]);

            $this->assertSame(
                0,
                $exitCode,
                $tester->getDisplay()
            );

            $user = User::withoutGlobalScopes()
                ->where('email', 'bootstrap-test@example.invalid')
                ->firstOrFail();

            $this->assertSame('super_admin', $user->role);
            $this->assertNull($user->company_id);
            $this->assertNull($user->branch_id);
            $this->assertNull($user->pos_pin_hash);
            $this->assertTrue($user->is_active);

            $this->assertTrue(
                Hash::check('ClaveTemporalSegura2026!', $user->password)
            );

            $this->assertSame(0, DB::table('companies')->count());
            $this->assertSame(0, DB::table('branches')->count());
        } finally {
            DB::rollBack();
        }
    }
public function test_rejects_bootstrap_when_super_admin_already_exists(): void
{
    $this->assertSame('testing', app()->environment());
    $this->assertSame(
        'pos_restaurant_bootstrap_test',
        DB::selectOne('SELECT current_database() AS name')->name
    );

    DB::beginTransaction();

    try {
        User::withoutGlobalScopes()->create([
            'name' => 'Administrador existente',
            'email' => 'existing-admin@example.invalid',
            'password' => Hash::make('ClaveTemporalSegura2026!'),
            'role' => 'super_admin',
            'company_id' => null,
            'branch_id' => null,
            'pos_pin_hash' => null,
            'locale' => 'es-CL',
            'is_active' => true,
        ]);

        $command = app(BootstrapSuperAdmin::class);
        $command->setLaravel(app());

        $tester = new CommandTester($command);

        $exitCode = $tester->execute([], [
            'interactive' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(
            1,
            User::withoutGlobalScopes()
                ->where('role', 'super_admin')
                ->count()
        );
    } finally {
        DB::rollBack();
    }
}
public function test_rejects_non_interactive_execution(): void
{
    $this->assertSame('testing', app()->environment());
    $this->assertSame(
        'pos_restaurant_bootstrap_test',
        DB::selectOne('SELECT current_database() AS name')->name
    );

    DB::beginTransaction();

    try {
        $usersBefore = DB::table('users')->count();

        $command = app(BootstrapSuperAdmin::class);
        $command->setLaravel(app());

        $tester = new CommandTester($command);

        $exitCode = $tester->execute([], [
            'interactive' => false,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(
            $usersBefore,
            DB::table('users')->count()
        );

        $this->assertStringContainsString(
            'requiere una consola interactiva',
            $tester->getDisplay()
        );
    } finally {
        DB::rollBack();
    }
}
public function test_rejects_password_shorter_than_16_characters(): void
{
    $this->assertSame('testing', app()->environment());
    $this->assertSame(
        'pos_restaurant_bootstrap_test',
        DB::selectOne('SELECT current_database() AS name')->name
    );

    DB::beginTransaction();

    try {
        $usersBefore = DB::table('users')->count();

        $command = app(BootstrapSuperAdmin::class);
        $command->setLaravel(app());

        $tester = new CommandTester($command);

        $tester->setInputs([
            'Administrador de prueba',
            'short-password@example.invalid',
            'ClaveCorta123!',
            'ClaveCorta123!',
        ]);

        $exitCode = $tester->execute([], [
            'interactive' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $this->assertSame(
            $usersBefore,
            DB::table('users')->count()
        );

        $this->assertSame(
            0,
            User::withoutGlobalScopes()
                ->where('email', 'short-password@example.invalid')
                ->count()
        );
    } finally {
        DB::rollBack();
    }
}
public function test_rejects_password_confirmation_mismatch(): void
{
    $this->assertSame('testing', app()->environment());
    $this->assertSame(
        'pos_restaurant_bootstrap_test',
        DB::selectOne('SELECT current_database() AS name')->name
    );

    DB::beginTransaction();

    try {
        $usersBefore = DB::table('users')->count();

        $command = app(BootstrapSuperAdmin::class);
        $command->setLaravel(app());

        $tester = new CommandTester($command);

        $tester->setInputs([
            'Administrador de prueba',
            'password-mismatch@example.invalid',
            'ClaveTemporalSegura2026!',
            'OtraClaveSegura2026!',
        ]);

        $exitCode = $tester->execute([], [
            'interactive' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $this->assertSame(
            $usersBefore,
            DB::table('users')->count()
        );

        $this->assertSame(
            0,
            User::withoutGlobalScopes()
                ->where('email', 'password-mismatch@example.invalid')
                ->count()
        );
    } finally {
        DB::rollBack();
    }
}
public function test_rejects_email_already_registered(): void
{
    $this->assertSame('testing', app()->environment());
    $this->assertSame(
        'pos_restaurant_bootstrap_test',
        DB::selectOne('SELECT current_database() AS name')->name
    );

    DB::beginTransaction();

    try {
        User::withoutGlobalScopes()->create([
            'name' => 'Usuario existente',
            'email' => 'existing-user@example.invalid',
            'password' => Hash::make('ClaveTemporalSegura2026!'),
            'role' => 'cajero',
            'company_id' => null,
            'branch_id' => null,
            'pos_pin_hash' => null,
            'locale' => 'es-CL',
            'is_active' => true,
        ]);

        $usersBefore = DB::table('users')->count();

        $command = app(BootstrapSuperAdmin::class);
        $command->setLaravel(app());

        $tester = new CommandTester($command);

        $tester->setInputs([
            'Nuevo administrador',
            'existing-user@example.invalid',
            'OtraClaveSegura2026!',
            'OtraClaveSegura2026!',
        ]);

        $exitCode = $tester->execute([], [
            'interactive' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $this->assertSame(
            $usersBefore,
            DB::table('users')->count()
        );

        $this->assertSame(
            0,
            User::withoutGlobalScopes()
                ->where('role', 'super_admin')
                ->count()
        );
    } finally {
        DB::rollBack();
    }
}
public function test_rejects_non_postgresql_database(): void
{
    $this->assertSame('testing', app()->environment());
    $this->assertSame('pgsql', DB::getDriverName());

    $this->assertSame(
        'pos_restaurant_bootstrap_test',
        DB::selectOne('SELECT current_database() AS name')->name
    );

    $originalConnection = DB::getDefaultConnection();

    try {
        config()->set('database.connections.bootstrap_sqlite_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::setDefaultConnection('bootstrap_sqlite_test');

        $command = app(BootstrapSuperAdmin::class);
        $command->setLaravel(app());

        $tester = new CommandTester($command);

        $exitCode = $tester->execute([], [
            'interactive' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $this->assertStringContainsString(
            'Este comando requiere PostgreSQL.',
            $tester->getDisplay()
        );
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('bootstrap_sqlite_test');
        config()->offsetUnset('database.connections.bootstrap_sqlite_test');
    }
}
}
