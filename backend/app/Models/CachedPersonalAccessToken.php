<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum por defecto hace 2 queries en cada request autenticado:
 *   1) SELECT * FROM personal_access_tokens WHERE token = hash(...)
 *   2) SELECT * FROM staff_accounts WHERE id = tokenable_id  (relacion tokenable)
 *
 * Contra DB remota (~280ms/query) eso son ~560ms de overhead fijo por
 * request autenticado, antes de que el controller haga su propio trabajo.
 *
 * Este modelo extiende el de Sanctum y cachea findToken() en Redis por 60s,
 * incluyendo eager load del tokenable (StaffAccount + profile). Asi todas
 * las peticiones autenticadas dentro de la misma ventana de 60s comparten
 * el lookup -> ~0 queries de auth en hits warm.
 *
 * Tradeoff de seguridad: si revocas un token o desactivas un staff, el
 * cache mantiene el acceso hasta 60s. Para logout explicito, el hook
 * `deleted()` invalida la key inmediatamente. Para "deshabilitar staff"
 * urgente, hay que limpiar el cache manualmente o esperar el TTL.
 *
 * Registrado en AppServiceProvider::boot() con
 * `Sanctum::usePersonalAccessTokenModel(CachedPersonalAccessToken::class)`.
 */
class CachedPersonalAccessToken extends PersonalAccessToken
{
    /**
     * Eloquent infiere el nombre de tabla del nombre de clase hijo, por lo
     * tanto sobrescribimos para apuntar a la tabla real de Sanctum. Sin
     * esto, los INSERT/SELECT fallan con "relation cached_personal_access_tokens does not exist".
     */
    protected $table = 'personal_access_tokens';

    /**
     * TTL del cache de tokens en segundos. 60s = balance entre rendimiento
     * (la mayoria del trafico del mismo usuario cae en cache) y seguridad
     * (un token revocado vuelve a verificarse contra DB dentro de 1 min).
     */
    public const CACHE_TTL = 60;

    protected static function booted(): void
    {
        // Cuando un token se borra (logout, revoke), invalidar inmediatamente
        // su entrada en cache. Sin esto, el token revocado seguiria valido
        // hasta el TTL.
        static::deleted(function (self $token) {
            static::forgetCacheFor($token);
        });

        // NO invalidar en `updated`. Sanctum actualiza `last_used_at` en
        // CADA request autenticado para tracking. Si invalidamos en update,
        // el cache se borra inmediatamente despues de poblarse y nunca
        // persiste -> cada request paga el lookup completo + load profile
        // (medido: 1.7s por request en lugar de 900ms con cache caliente).
        //
        // El tradeoff: si manualmente actualizas un token (ej. cambiar
        // abilities/name), tarda hasta CACHE_TTL en reflejarse. Para revoke
        // total, usa delete().
    }

    /**
     * Override del lookup de token. Sanctum llama a este metodo en cada
     * request con un Bearer token. Lo cacheamos con eager load del
     * tokenable.
     */
    public static function findToken($token)
    {
        $hash = static::hashForCache($token);
        if ($hash === null) {
            return null;
        }

        $cacheKey = "sanctum:token:{$hash}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($token) {
            /** @var static|null $found */
            $found = parent::findToken($token);
            if ($found) {
                // Pre-carga el tokenable. Solo StaffAccount tiene la relacion
                // `profile`; ApplicantAccount no, asi que usamos morphWith
                // para eager-load profile solo cuando el tokenable_type lo
                // soporta. Sin esto, requests autenticadas de aplicantes
                // revientan con RelationNotFoundException.
                $found->load(['tokenable' => function ($morphTo) {
                    $morphTo->morphWith([
                        StaffAccount::class => ['profile'],
                    ]);
                }]);
            }
            return $found;
        });
    }

    /**
     * Calcula la clave de cache para un token plano (formato Sanctum:
     * "{id}|{plainTextToken}").
     */
    protected static function hashForCache(string $token): ?string
    {
        $plainText = $token;
        if (str_contains($token, '|')) {
            $parts = explode('|', $token, 2);
            $plainText = $parts[1] ?? $token;
        }
        if ($plainText === '') {
            return null;
        }
        return hash('sha256', $plainText);
    }

    /**
     * Limpia la entrada de cache de un token guardado. Sanctum guarda el
     * hash sha256 en la columna `token` — ese es el mismo valor que usa
     * para lookup. Para invalidar no necesitamos el plain text.
     */
    protected static function forgetCacheFor(self $token): void
    {
        // La columna `token` en DB ya es el sha256 del plain text.
        Cache::forget("sanctum:token:{$token->token}");
    }
}
