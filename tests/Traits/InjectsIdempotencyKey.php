<?php

namespace Tests\Traits;

use Illuminate\Support\Str;

/**
 * Trait que inyecta automáticamente el header Idempotency-Key en tests.
 * 
 * Permite pasar 'Idempotency-Key' => null para explícitamente NO inyectar
 * (útil para testear el caso "sin header").
 */
trait InjectsIdempotencyKey
{
    /**
     * Determina si debe inyectar el header.
     * 
     * NO inyecta si:
     * - El header ya está en los headers explícitos (incluyendo null)
     * - El header está en defaultHeaders
     */
    protected function shouldInjectIdempotencyKey(array $headers): bool
    {
        // Si se pasó explícitamente (incluso null), respetar
        if (array_key_exists('Idempotency-Key', $headers)) {
            return false;
        }

        // Si está en defaultHeaders (from withHeaders())
        if (property_exists($this, 'defaultHeaders') && array_key_exists('Idempotency-Key', $this->defaultHeaders)) {
            return false;
        }

        return true;
    }

    /**
     * Inyecta el header solo si no está establecido de ninguna forma.
     */
    protected function injectIdempotencyKeyIfNeeded(array &$headers): void
    {
        if ($this->shouldInjectIdempotencyKey($headers)) {
            $headers['Idempotency-Key'] = (string) Str::uuid();
        } else {
            // Si se pasó null explícitamente, removerlo del array para que el middleware lo detecte como faltante
            if (array_key_exists('Idempotency-Key', $headers) && $headers['Idempotency-Key'] === null) {
                unset($headers['Idempotency-Key']);
            }
        }
    }

    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->injectIdempotencyKeyIfNeeded($headers);
        }
        return parent::json($method, $uri, $data, $headers, $options);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->injectIdempotencyKeyIfNeeded($headers);
        return parent::postJson($uri, $data, $headers, $options);
    }

    public function putJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->injectIdempotencyKeyIfNeeded($headers);
        return parent::putJson($uri, $data, $headers, $options);
    }

    public function patchJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->injectIdempotencyKeyIfNeeded($headers);
        return parent::patchJson($uri, $data, $headers, $options);
    }

    public function deleteJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->injectIdempotencyKeyIfNeeded($headers);
        return parent::deleteJson($uri, $data, $headers, $options);
    }
}
