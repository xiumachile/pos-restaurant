# ADR-006: Token de Sesión POS Efímero (Refactor Login PIN)

**Fecha**: 02 Septiembre 2026  
**Estado**: ✅ Aceptado  
**Contexto**: Pre-Frontend Gate — Punto #1 (Login PIN O(n) + timing attack)

## Contexto

El método `loginWithPin()` original era vulnerable:

```php
// CÓDIGO VULNERABLE (O(n) + timing attack)
$users = User::where('branch_id', $branchId)->get();  // SELECT *
$matchedUser = $users->first(function ($user) use ($pin) {
    return $user->verifyPosPin($pin);  // password_verify en CADA usuario
});
Problemas identificados:
O(n) usuarios: itera todos los usuarios activos de la sucursal
Timing attack: password_verify tarda diferente según el hash
Exposición de datos: revela cuántos usuarios hay por sucursal
PIN expuesto en red: cada request envía el PIN
Decisión
Implementar flujo dual backwards compatible:
Flujo Nuevo (O(1) con token efímero)
┌─────────────────────────────────────────────────────┐
│ PASO 1: Setup del POS (una vez por sesión)          │
├─────────────────────────────────────────────────────┤
│ POST /api/v1/auth/pos-session                       │
│ Body: { branch_id, pin }                            │
│ → Valida PIN (O(n) aceptable en setup)              │
│ → Genera token efímero: bin2hex(random_bytes(16))   │
│ → Cache: SETEX pos_session:{branch}:{token} 300     │
│ → Retorna: { session_token, expires_in: 300 }       │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ PASO 2: Login real (múltiples veces, O(1))          │
├─────────────────────────────────────────────────────┤
│ POST /api/v1/auth/login/pos                         │
│ Body: { branch_id, session_token }                  │
│ → Cache GET pos_session:{branch}:{token}  ← O(1)    │
│ → Genera JWT estándar                               │
│ → Cache DEL token (one-time use)                    │
│ → Retorna: { access_token, user }                   │
└─────────────────────────────────────────────────────┘
Flujo Legacy (mantenido para compatibilidad)
POST /api/v1/auth/login/pos
Body: { branch_id, pin }  ← Flujo O(n) original
Diseño Detallado
Clave de Cache
pos_session:{branch_id}:{session_token}
Incluye branch_id para aislar tokens por sucursal
Token no puede usarse en otra sucursal
TTL: 300 segundos (5 minutos)
Ventana de ataque mínima
Suficiente para setup del POS + primer login
Token expira automáticamente
Token: 32 caracteres hex
$token = bin2hex(random_bytes(16));  // 128 bits de entropía
Resistente a fuerza bruta (2^128 combinaciones)
Legible y fácil de transmitir entre dispositivos
One-Time Use
Cache::forget($cacheKey);  // Después de login exitoso
Previene replay attacks
Token no puede reutilizarse
Consecuencias
Positivas
✅ Login O(1): lookup directo en cache
✅ Zero timing attack: operación de tiempo constante
✅ PIN no expuesto: solo se envía en setup (una vez)
✅ Token efímero: 5 minutos de ventana
✅ One-time use: previene replay attacks
✅ Backwards compatible: tests legacy siguen pasando
✅ Rate limiting: throttle:3,1 en ambos endpoints
Negativas
⚠️ Requiere cache (Redis/file/database) disponible
⚠️ Flujo dual agrega complejidad inicial (2 paths en controller)
⚠️ Frontend POS debe actualizar a flujo nuevo eventualmente
Neutrales
ℹ️ Setup del POS sigue siendo O(n) (aceptable, es una vez por sesión)
Seguridad
Mitigaciones Implementadas
Rate Limiting: throttle:3,1 en /auth/pos-session y /auth/login/pos
TTL corto: 5 minutos de ventana de ataque
One-time use: token eliminado después de login
Branch isolation: token vinculado a sucursal específica
Random tokens: 128 bits de entropía (bin2hex(random_bytes(16)))
PIN hashing: bcrypt en BD (password_hash/verify)
Riesgos Residuales
Cache comprometido: si Redis es accesible, tokens pueden leerse
Mitigación: Redis con password + firewall
Setup del POS es O(n): aceptable porque es una vez por sesión
Mitigación: rate limiting previene enumeración
Tests Implementados
7 tests en tests/Feature/PosSessionTest.php:
✅ Genera token con PIN válido
✅ Rechaza PIN inválido
✅ Login con token O(1) + one-time use
✅ Token expira después del TTL
✅ Token de otra sucursal no funciona
✅ Flujo legacy con PIN sigue funcionando
✅ Validación: requiere pin o session_token
Migración del Frontend
Fase 1 (inmediata)
Frontend puede usar flujo legacy (PIN directo)
Backend mantiene compatibilidad
Fase 2 (próximo sprint)
Frontend actualiza a flujo nuevo:
Al iniciar sesión: llamar /auth/pos-session con PIN
Almacenar session_token en memoria
Para login real: usar /auth/login/pos con session_token
Si token expira: repetir paso 1
Fase 3 (opcional, 6+ meses)
Eliminar flujo legacy si no hay clientes legacy
Simplificar controller
Alternativas Descartadas
❌ Eliminar loginWithPin completamente: rompe compatibilidad
❌ Token permanente: riesgo de replay attack
❌ TOTP (2FA): complejidad innecesaria para POS
❌ Biometría: hardware no disponible en terminales estándar
Referencias
OWASP Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
NIST SP 800-63B: Guías de autenticación digital
Pre-Frontend Gate: documento original del equipo
Decisión tomada por: Arquitecto + Desarrollador
Fecha: 02 Septiembre 2026
