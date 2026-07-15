<?php

namespace App\Http\Controllers\Api\V2\Integration;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Models\Application;
use App\Models\Loan;
use App\Services\Webhook\WebhookPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de re-consulta (lectura) para el sistema externo: devuelve el MISMO
 * esquema que el payload del webhook (mismo builder), scoped por tenant.
 * Requiere un token con ability `integration`. Ver docs/integracion/webhooks.md §8.
 */
class IntegrationReadController extends Controller
{
    use ApiResponses;

    public function __construct(private WebhookPayloadBuilder $builder)
    {
    }

    public function application(Request $request, string $id): JsonResponse
    {
        if ($error = $this->guardIntegration($request)) {
            return $error;
        }

        $app = Application::where('id', $id)
            ->where('tenant_id', $this->tenantId($request))
            ->first();
        if (! $app) {
            return $this->notFound('Solicitud no encontrada.');
        }

        return $this->success($this->builder->application($app));
    }

    public function loan(Request $request, string $id): JsonResponse
    {
        if ($error = $this->guardIntegration($request)) {
            return $error;
        }

        $loan = Loan::where('id', $id)
            ->where('tenant_id', $this->tenantId($request))
            ->first();
        if (! $loan) {
            return $this->notFound('Crédito no encontrado.');
        }

        return $this->success($this->builder->loan($loan));
    }

    /** El token debe tener la ability `integration`. */
    private function guardIntegration(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->tokenCan('integration')) {
            return $this->forbidden('El token no tiene permiso de integración.');
        }

        return null;
    }

    private function tenantId(Request $request): string
    {
        return (string) $request->user()->tenant_id;
    }
}
