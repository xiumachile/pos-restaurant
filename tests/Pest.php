<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Limpia el estado de autenticación y tenant entre requests en el mismo test.
 *
 * Necesario porque Pest reutiliza la misma instancia de aplicación entre
 * múltiples llamadas $this->postJson()/getJson() dentro de un mismo test.
 * En producción cada request HTTP bootea una app nueva, pero en tests no.
 *
 * Sin esta limpieza, el guard JWT cachea el usuario del request anterior
 * y requests siguientes usan el usuario cacheado aunque envíen un token distinto.
 *
 * Uso:
 *   $this->withHeaders(['Authorization' => "Bearer $tokenA"])->postJson('/...');
 *   switchJwtUser(); // limpiar estado
 *   $this->withHeaders(['Authorization' => "Bearer $tokenB"])->postJson('/...');
 */
function switchJwtUser(): void
{
    try {
        auth()->guard('api')->logout();
    } catch (\Throwable $e) {
        // Ignorar si no hay token activo o ya está logueado
    }

    app('auth')->forgetGuards();

    // Limpiar TenantContext para evitar contaminación entre tenants
    try {
        app(\App\Shared\Application\TenantContext::class)->clear();
    } catch (\Throwable $e) {
        // Ignorar si el service no está disponible
    }
}

function something()
{
    // ..
}
