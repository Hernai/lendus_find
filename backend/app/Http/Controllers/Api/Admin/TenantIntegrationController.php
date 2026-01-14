<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantApiConfig;
use App\Services\ExternalApi\TwilioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantIntegrationController extends Controller
{
    /**
     * List all integrations for current tenant
     */
    public function index(): JsonResponse
    {
        $configs = TenantApiConfig::where('tenant_id', app('tenant.id'))
            ->orderBy('provider')
            ->orderBy('service_type')
            ->get();

        return response()->json([
            'data' => $configs->map(fn($config) => $config->toApiArray()),
        ]);
    }

    /**
     * Get available providers and service types
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'providers' => TenantApiConfig::PROVIDERS,
            'service_types' => TenantApiConfig::SERVICE_TYPES,
        ]);
    }

    /**
     * Create or update an integration
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:' . implode(',', array_keys(TenantApiConfig::PROVIDERS)),
            'service_type' => 'required|string|in:' . implode(',', array_keys(TenantApiConfig::SERVICE_TYPES)),
            'account_sid' => 'nullable|string',
            'auth_token' => 'nullable|string',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'from_number' => 'nullable|string|max:20',
            'from_email' => 'nullable|email|max:100',
            'domain' => 'nullable|string|max:200',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:100',
            'extra_config' => 'nullable|array',
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if config already exists
        $config = TenantApiConfig::where('tenant_id', app('tenant.id'))
            ->where('provider', $request->provider)
            ->where('service_type', $request->service_type)
            ->first();

        $data = [
            'tenant_id' => app('tenant.id'),
            'provider' => $request->provider,
            'service_type' => $request->service_type,
            'is_active' => $request->is_active ?? true,
            'is_sandbox' => $request->is_sandbox ?? (app()->environment() !== 'production'),
        ];

        // Only update credentials if provided (don't overwrite with null)
        if ($request->filled('account_sid')) {
            $data['account_sid'] = $request->account_sid;
        }
        if ($request->filled('auth_token')) {
            $data['auth_token'] = $request->auth_token;
        }
        if ($request->filled('api_key')) {
            $data['api_key'] = $request->api_key;
        }
        if ($request->filled('api_secret')) {
            $data['api_secret'] = $request->api_secret;
        }
        if ($request->filled('from_number')) {
            $data['from_number'] = $request->from_number;
        }
        if ($request->filled('from_email')) {
            $data['from_email'] = $request->from_email;
        }
        if ($request->filled('domain')) {
            $data['domain'] = $request->domain;
        }
        if ($request->filled('webhook_url')) {
            $data['webhook_url'] = $request->webhook_url;
        }
        if ($request->filled('webhook_secret')) {
            $data['webhook_secret'] = $request->webhook_secret;
        }
        if ($request->filled('extra_config')) {
            $data['extra_config'] = $request->extra_config;
        }

        if ($config) {
            $config->update($data);
        } else {
            $config = TenantApiConfig::create($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Integración guardada correctamente',
            'data' => $config->toApiArray(),
        ]);
    }

    /**
     * Test an integration
     */
    public function test(Request $request, string $id): JsonResponse
    {
        $config = TenantApiConfig::where('tenant_id', app('tenant.id'))
            ->where('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'test_phone' => 'required_if:service_type,sms,whatsapp|string',
            'test_email' => 'required_if:service_type,email|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = null;

            // Test based on provider and service type
            if ($config->provider === 'twilio' && in_array($config->service_type, ['sms', 'whatsapp'])) {
                $twilioService = new TwilioService(app('tenant.id'));
                $testMessage = 'Prueba de integración desde LendusFind - ' . now()->format('H:i:s');

                if ($config->service_type === 'whatsapp') {
                    $result = $twilioService->sendWhatsApp($request->test_phone, $testMessage);
                } else {
                    $result = $twilioService->sendSms($request->test_phone, $testMessage);
                }

                if ($result['success']) {
                    $config->update([
                        'last_tested_at' => now(),
                        'last_test_success' => true,
                        'last_test_error' => null,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Prueba exitosa - Mensaje enviado',
                        'details' => [
                            'sid' => $result['sid'] ?? null,
                            'status' => $result['status'] ?? null,
                        ],
                    ]);
                } else {
                    $config->update([
                        'last_tested_at' => now(),
                        'last_test_success' => false,
                        'last_test_error' => $result['error'] ?? 'Unknown error',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Prueba fallida',
                        'error' => $result['error'] ?? 'Unknown error',
                    ], 500);
                }
            }

            // Add more provider/service type tests here

            return response()->json([
                'error' => 'Test not implemented for this provider/service type',
            ], 501);
        } catch (\Exception $e) {
            $config->update([
                'last_tested_at' => now(),
                'last_test_success' => false,
                'last_test_error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en la prueba',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an integration
     */
    public function destroy(string $id): JsonResponse
    {
        $config = TenantApiConfig::where('tenant_id', app('tenant.id'))
            ->where('id', $id)
            ->firstOrFail();

        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Integración eliminada correctamente',
        ]);
    }
}
