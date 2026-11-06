<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\Domain\Entities\User;
use Throwable;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'platform:bootstrap-super-admin';

    protected $description = 'Inicializa de forma segura el primer superadministrador SaaS';

    public function handle(): int
    {
        if (!$this->input->isInteractive()) {
            $this->error('Este comando requiere una consola interactiva.');
            return self::FAILURE;
        }

        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Este comando requiere PostgreSQL.');
            return self::FAILURE;
        }

        try {
            if ($this->superAdminExists()) {
                $this->error('Ya existe un superadministrador. Inicialización rechazada.');
                return self::FAILURE;
            }

            $name = trim((string) $this->ask('Nombre del superadministrador'));
            $email = mb_strtolower(trim((string) $this->ask('Correo electrónico')));

            $password = (string) $this->secret('Contraseña (mínimo 16 caracteres)');
            $confirmation = (string) $this->secret('Confirmar contraseña');

            $validator = Validator::make([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(16),
                ],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $created = DB::transaction(function () use ($name, $email, $password): bool {
                // Bloqueo transaccional exclusivo para el bootstrap.
                DB::select('SELECT pg_advisory_xact_lock(7303, 1)');

                if ($this->superAdminExists()) {
                    return false;
                }

                if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
                    throw new \RuntimeException('El correo ya está registrado.');
                }

                User::withoutGlobalScopes()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => 'super_admin',
                    'company_id' => null,
                    'branch_id' => null,
                    'pos_pin_hash' => null,
                    'locale' => 'es-CL',
                    'is_active' => true,
                ]);

                return true;
            }, 3);

            if (!$created) {
                $this->error('Ya existe un superadministrador. Inicialización rechazada.');
                return self::FAILURE;
            }

            $this->info('Superadministrador SaaS creado correctamente.');
            $this->info('No se crearon empresas, sucursales ni datos de demostración.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            // No mostrar detalles que puedan contener información sensible.
            $this->error('No se pudo completar la inicialización.');
            return self::FAILURE;
        } finally {
            unset($password, $confirmation);
        }
    }

    private function superAdminExists(): bool
    {
        return User::withoutGlobalScopes()
            ->where('role', 'super_admin')
            ->exists();
    }
}
