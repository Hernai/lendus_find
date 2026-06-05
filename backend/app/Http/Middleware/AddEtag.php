<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agrega un `ETag` debil al response y responde `304 Not Modified` cuando
 * el cliente envia `If-None-Match` con el mismo hash.
 *
 * Para que sirva: el endpoint debe ser idempotente (mismo input => mismo
 * output) y vale solo sobre `GET` exitosos (2xx).
 *
 * Beneficio: clientes que ya tienen el payload en cache local no reciben
 * el body otra vez, solo un 304 pequeno. Para apps moviles que llaman
 * `/v2/config` o `/v2/staff/me/tenants` cada arranque, eso baja varios
 * KB y ~40-60ms a un solo round-trip 304.
 *
 * Combinado con el `Cache::remember` del lado server, la cadena es:
 * - 1er request del usuario: server arma payload, calcula ETag, responde 200
 * - Mismo usuario, mismo payload vigente: server lee Redis, calcula ETag,
 *   compara con If-None-Match, responde 304 (body vacio).
 * - Otro usuario, mismo tenant: el Redis sirve el payload, ETag nuevo
 *   para ese cliente, responde 200 con cache hit.
 *
 * NO usar en endpoints que devuelven datos personalizados por sesion sin
 * que la sesion sea parte del payload (porque el hash colisionaria entre
 * usuarios). Para `/v2/staff/me` u otros con datos del usuario, vale solo
 * si el payload incluye el user_id (que lo hace, el hash difiere).
 */
class AddEtag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo GET / HEAD con 2xx tienen sentido para ETag.
        if (! $request->isMethodCacheable()) {
            return $response;
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }
        // Skip si el endpoint ya seteo su propio ETag.
        if ($response->headers->has('ETag')) {
            return $this->maybe304($request, $response);
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return $response;
        }

        // ETag debil (W/) — basado en hash del body. xxh3 o md5 sirven; md5
        // esta en todas partes y es suficientemente rapido (~1ms para 100KB).
        $etag = 'W/"' . md5($content) . '"';
        $response->headers->set('ETag', $etag);

        return $this->maybe304($request, $response);
    }

    /**
     * Si el cliente mando `If-None-Match` con el mismo ETag, devolvemos
     * 304 sin body. Ahorra ancho de banda y tiempo de serializacion JSON
     * en el cliente.
     */
    private function maybe304(Request $request, Response $response): Response
    {
        $clientEtag = $request->headers->get('If-None-Match');
        if (! $clientEtag) {
            return $response;
        }

        $serverEtag = $response->headers->get('ETag');
        if (! $serverEtag) {
            return $response;
        }

        // Comparacion debil: ignorar W/ prefix.
        $normalize = fn (string $e) => ltrim($e, 'W/');
        if ($normalize($clientEtag) !== $normalize($serverEtag)) {
            return $response;
        }

        // 304 sin body. Mantener headers de cache.
        return response('', 304)
            ->withHeaders(array_filter([
                'ETag' => $serverEtag,
                'Cache-Control' => $response->headers->get('Cache-Control'),
                'Vary' => $response->headers->get('Vary'),
            ]));
    }
}
